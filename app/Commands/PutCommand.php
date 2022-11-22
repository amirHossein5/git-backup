<?php

namespace App\Commands;

use App\Services\FileManager;
use App\Traits\HasForcedOptions;
use App\Traits\HasToken;
use App\Traits\Loggable;
use App\Traits\Uploadable;
use Illuminate\Support\Facades\Storage;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Command\Command as Output;
use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Helper\ProgressBar;

class PutCommand extends Command
{
    use HasForcedOptions;
    use HasToken;
    use Uploadable;
    use Loggable;

    private ProgressBar $progressBar;

    private Cursor $cursor;
    private string $dirPathWillBe;
    private int $totalUploadedBytes = 0;
    private string $disk;
    private ?string $logTo = null;
    private string $tempUploadedDirName;

    // upload mods
    public const DELETE_FROM_DISK = 'delete dir from disk';
    public const FRESH_DIR = 'fresh directory';
    public const SELECT_NEW_NAME = 'select new distination name';
    public const REPLACE_IT = 'replace it';
    public const MERGE_IT = 'merge it';
    public const UPLOAD_REMAINED = 'upload remained things';
    public const UPLOAD_DIRECTLY = 'uploads to disk';

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
        {--log-to= : Log to file path.}
        {--merge : When already exists, merge it.}
        {--replace : When already exists, replace it.}
        {--fresh : When already exists, fresh directory.}
        {--upload-remained : When already exists, upload remained things.}
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

        $this->logTo = $this->option('log-to');
        $this->disk = $disk;
        $this->dirPathWillBe = $dirPathWillBe;

        if ($this->logTo) {
            if (! is_file($this->logTo)) {
                $this->error('File for logging not found: ' . $this->logTo);

                return Output::FAILURE;
            }
        }
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
            $exists = Storage::disk($disk)->directoryExists($dirPathWillBe);
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return Output::FAILURE;
        }

        $mod = self::UPLOAD_DIRECTLY;

        if ($exists) {
            $mod = $this->getMod();

            if ($mod === self::DELETE_FROM_DISK) {
                $this->deleteDir();

                return Output::SUCCESS;
            }
            if ($mod === self::FRESH_DIR) {
                $this->deleteDir();
                $mod = self::UPLOAD_DIRECTLY;
            }

            if ($mod === self::SELECT_NEW_NAME) {
                $toDir = $this->ask('Write path(equilvant of --to-dir option)', $dirPathWillBe . '(1)');

                return $this->call('put', [
                    '--dir' => $dirPath,
                    '--to-dir' => $toDir,
                    '--disk' => $this->option('disk'),
                    '--disk-tokens' => $this->option('disk-tokens'),
                ]);
            }
        }

        $countSteps = FileManager::countAllNestedFiles($dirPath) + FileManager::countNestedEmptyFolders($dirPath);

        $this->info("Uploading to disk: <comment>{$disk}</comment>, path: <comment>{$dirPathWillBe}/</comment>");

        if ($countSteps === 0) {
            $this->error($dirPath . ' Does not have any file or folder.');

            return Output::FAILURE;
        }

        if ($mod === self::REPLACE_IT) {
            $tempUploadedDirName = uniqid() . uniqid() . '.tmp';
            $this->tempUploadedDirName = $tempUploadedDirName;
            $this->warn("Don't remove {$tempUploadedDirName}, it will be remove after upload.");

            $this->task(
                "<comment>moving {$this->dirPathWillBe}/ to {$tempUploadedDirName}</comment>",
                fn () => $this->failWhen(
                    ! Storage::disk($this->disk)->move($this->dirPathWillBe, $tempUploadedDirName),
                    "Counldn't create directory in disk path {$tempUploadedDirName}. Check your connection, or set disk authorization tokens."
                )
            );
        }

        $this->newLine();

        $this->progressBar = $this->editProgressBar($countSteps);
        $this->progressBar->start();

        try {
            $this->manageUpload($mod, $dirPath);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return Output::FAILURE;
        }

        $this->progressBar->finish();
        $this->progressBar->clear();
        $this->newLine();
        $totalSize = readable_size($this->totalUploadedBytes);

        if ($mod === self::REPLACE_IT) {
            $this->task(
                "<comment>removing {$this->tempUploadedDirName}</comment>",
                fn () => $this->failWhen(
                    ! Storage::disk($this->disk)->deleteDirectory($this->tempUploadedDirName),
                    "Counldn't delete directory in disk path {$this->tempUploadedDirName}. Check your connection, or set disk authorization tokens."
                )
            );
        }

        $this->info("Uploaded <comment>{$dirPath}</comment> to <comment>{$dirPathWillBe}/</comment> successfully.");
        $this->info("total uploaded file size: <comment>{$totalSize}</comment>");

        return Output::SUCCESS;
    }

    private function getMod(): string
    {
        $mod = PutCommand::UPLOAD_DIRECTLY;

        if ($this->option('upload-remained') === true) {
            $mod = PutCommand::UPLOAD_REMAINED;
        }
        if ($this->option('merge') === true) {
            $mod = PutCommand::MERGE_IT;
        }
        if ($this->option('replace') === true) {
            $mod = PutCommand::REPLACE_IT;
        }
        if ($this->option('fresh') === true) {
            $mod = PutCommand::FRESH_DIR;
        }
        if ($mod === PutCommand::UPLOAD_DIRECTLY) {
            $mod = $this->choice(
                "Directory <comment>{$this->dirPathWillBe}</comment> exists in disk <comment>{$this->disk}</comment>",
                [self::DELETE_FROM_DISK, self::FRESH_DIR, self::SELECT_NEW_NAME, self::REPLACE_IT, self::MERGE_IT, self::UPLOAD_REMAINED]
            );
        }

        return $mod;
    }

    private function filesAreSame(string $file, string $fileOnDisk): bool
    {
        $fileOnDiskSize = Storage::disk($this->disk)->size($fileOnDisk);
        $fileSize = filesize($file);

        if ($fileOnDiskSize === 1) {
            if (trim(Storage::disk($this->disk)->get($fileOnDisk)) === '' and $fileSize === 0) {
                return true;
            }
        }

        if ($fileSize != $fileOnDiskSize) {
            return false;
        }

        $fileContent = file_get_contents($file);
        $fileOnDiskContent = Storage::disk($this->disk)->get($fileOnDisk);

        if (sha1($fileContent) === sha1($fileOnDiskContent)) {
            return true;
        }
        // if (md5($fileContent) === md5($fileOnDiskContent)) {
        //     return true;
        // }

        return false;
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
}
