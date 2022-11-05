<?php

namespace App\Commands;

use App\Exceptions\JsonDecodeException;
use App\Services\DiskManager;
use App\Services\FileManager;
use App\Services\JsonDecoder;
use App\Traits\HasForcedOptions;
use Illuminate\Support\Facades\Storage;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Command\Command as Output;
use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

class PutCommand extends Command
{
    use HasForcedOptions;

    private ProgressBar $progressBar;
    private Cursor $cursor;

    private string $dirPathWillBe;
    private int $totalUploadedBytes = 0;
    private string $disk;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'put
        {--dir=}
        {--disk= : See disks via, show:disk }
        {--to-dir= : Put in disk path.}
        {--disk-tokens= : Path to json file that contains disk authorization items.}
    ';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Puts directory to specified disk.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->cursor = new Cursor($this->output);

        if (! $this->hasAllOptions('disk', 'dir')) {
            return Output::FAILURE;
        }

        $disk = $this->option('disk');
        $dirPath = pathable($this->option('dir'));
        $toDir = $this->option('to-dir');
        $dirPathWillBe = $toDir ? pathable($toDir) : basename($dirPath);
        $dirPathWillBe = str($dirPathWillBe)->rtrim(DIRECTORY_SEPARATOR);
        $showDiskCommand = getArtisanCommand('show:disk');
        $tokensPath = pathable($this->option('disk-tokens'));

        $this->disk = $disk;
        $this->dirPathWillBe = $dirPathWillBe;

        if (! is_dir($dirPath)) {
            $this->error("Directory not found {$dirPath}");
            return Output::FAILURE;
        }

        if (! config("filesystems.disks.{$disk}")) {
            $this->error("disk {$disk} not found.");
            $this->line("See available disk list via, {$showDiskCommand}");
            return Output::FAILURE;
        }

        if (! $this->manageDiskTokens($tokensPath, $disk)) {
            return Output::FAILURE;
        }

        $this->newLine();
        $this->info('Checking disk...');

        try {
            $exists = Storage::disk($disk)->exists($dirPathWillBe);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return Output::FAILURE;
        }

        if ($exists) {
            $this->error('Directory '.$dirPathWillBe.' exists in disk: '.$disk);

            if ($this->confirm('Do you want to delete '.$dirPathWillBe.' ?')) {
                $this->task("Deleted {$dirPathWillBe}", fn () => Storage::disk($disk)->deleteDirectory($dirPathWillBe));
                $this->info('run command again.');
                return Output::SUCCESS;
            }

            return Output::FAILURE;
        }

        $countSteps = FileManager::countAllNestedFiles($dirPath) + FileManager::countNestedEmptyFolders($dirPath);

        $this->info("Uploading to disk: <comment>{$disk}</comment>, path: <comment>{$dirPathWillBe}/</comment>");
        $this->newLine();

        if ($countSteps === 0) {
            $this->error($dirPath.' Does not have any file or folder.');
            return Output::FAILURE;
        }

        $this->progressBar = $this->editProgressBar($countSteps);
        $this->progressBar->start();

        try {
            $this->uploadFolder($dirPath, $dirPath);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return Output::FAILURE;
        }

        $this->progressBar->finish();
        $this->newLine();
        $totalSize = readable_size($this->totalUploadedBytes);

        $this->info("Uploaded {$dirPath} to {$dirPathWillBe} successfully.");
        $this->info("total uploaded file size: {$totalSize}");

        return Output::SUCCESS;
    }

    private function customTask(string $name, \Closure $closure): bool
    {
        $this->output->write($name);
        $result = $closure();

        if ($result === false) {
            $this->output->write(': <error>failed</error>');
        } else {
            $this->output->write(': <info>✔</info>');
        }

        $this->newLine();

        return $result === false ? false : true;
    }

    private function uploadFolder(string $dir, string $baseDirPath): void
    {
        $readableDir = (string) str($dir)->replace(
            str($baseDirPath)->rtrim(DIRECTORY_SEPARATOR), basename($baseDirPath)
        );

        $allFiles = FileManager::allFiles($dir);
        $allDirs = FileManager::allDir($dir);

        if (count($allFiles) === 0 and count($allDirs) === 0) {
            $this->writeMessageNL(" mkdir <comment>$readableDir</comment>");
            $this->uploadEmptyDir($dir, $baseDirPath);
            $this->cursor->clearLine()->moveUp()->clearLine()->moveUp();
            $this->progressBar->advance();
        }

        foreach ($allFiles as $file) {
            $readableFile = (string) str($file)->replace(
                str($baseDirPath)->rtrim(DIRECTORY_SEPARATOR),
                basename($baseDirPath)
            );
            $fileSize = readable_size(filesize($file));

            $this->writeMessageNL(" Uploading <comment>{$readableFile}({$fileSize})</comment>");
            $this->uploadFile($file, $baseDirPath);
            $this->cursor->clearLine()->moveUp()->clearLine()->moveUp();
            $this->totalUploadedBytes += filesize($file);
            $this->progressBar->advance();
        }

        foreach ($allDirs as $dir) {
            $this->uploadFolder($dir, $baseDirPath);
        }
    }

    private function editProgressBar(int $len): ProgressBar
    {
        $progressBar = new ProgressBar($this->output, $len);
        $progressBar->setFormat(' %current%/%max%  %percent:3s%%    (%elapsed:6s%/%estimated:-6s%)');
        return $progressBar;
    }

    private function writeMessage(string $string): void
    {
        $this->cursor->clearLine()->moveUp();
        $this->line($string);
    }

    private function writeMessageNL(string $string, int $newLines = 2): void
    {
        $this->newLine($newLines);
        $this->writeMessage($string);
    }

    private function failWhen(bool $when, string $message): void
    {
        if ($when) {
            throw new \Exception($message);
        }
    }

    private function uploadEmptyDir(string $dir, string $baseDirPath): void
    {
        $path = str($dir)->replace(
            str($baseDirPath)->rtrim(DIRECTORY_SEPARATOR),
            $this->dirPathWillBe
        );

        $this->failWhen(
            ! Storage::disk($this->disk)->makeDirectory($path),
            "Counldn't create directory in disk path {$path}. Check your connection, or set disk authorization tokens."
        );
    }

    private function uploadFile(string $file, string $baseDirPath): void
    {
        $filePath = str($file)->replace(
            str($baseDirPath)->rtrim(DIRECTORY_SEPARATOR),
            $this->dirPathWillBe
        );

        $fileContent = file_get_contents($file);

        if ($fileContent  === '') {
            $fileContent = ' ';
        }

        $this->failWhen(
            ! Storage::disk($this->disk)->put($filePath, $fileContent),
            "Counldn't create file in disk path {$filePath}. Check your connection, or set disk authorization tokens."
        );
    }

    private function manageDiskTokens(?string $tokensPath, string $disk): bool
    {
        if ($tokensPath) {
            try {
                $tokens = JsonDecoder::decodePath($tokensPath);
            } catch (JsonDecodeException $e) {
                $this->error('Error when decoding json:');
                $this->error($e->getMessage());
                return false;
            } catch(\Exception $e) {
                $this->error($e->getMessage());
                return false;
            }

            DiskManager::fillTokensOf($disk, $tokens);
        }

        return true;
    }
}
