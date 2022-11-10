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

class PutCommand extends Command
{
    use HasForcedOptions;

    private ProgressBar $progressBar;
    private Cursor $cursor;

    private string $dirPathWillBe;
    private int $totalUploadedBytes = 0;
    private string $disk;
    private string $tempUploadedDirName;

    // upload mods
    public const DELETE_FROM_DISK = 'delete dir from disk';
    public const SELECT_NEW_NAME = 'select new name for distination';
    public const REPLACE_IT = 'replace it';
    public const MERGE_IT = 'merge it with uploaded one';
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
        {--merge : When already exists, merge it.}
        {--replace : When already exists, replace it.}
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
            $exists = Storage::disk($disk)->directoryExists($dirPathWillBe);
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return Output::FAILURE;
        }

        $mod = self::UPLOAD_DIRECTLY;

        if ($exists) {
            $mod = $this->getMod();

            if ($mod === self::DELETE_FROM_DISK) {
                $this->task("Deleted {$dirPathWillBe}", fn () => Storage::disk($disk)->deleteDirectory($dirPathWillBe));

                return Output::SUCCESS;
            }

            if ($mod === self::SELECT_NEW_NAME) {
                $toDir = $this->ask('Write path(equilvant of --to-dir option)', $dirPathWillBe.'(1)');

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
            $this->error($dirPath.' Does not have any file or folder.');

            return Output::FAILURE;
        }

        if ($mod === self::REPLACE_IT) {
            $tempUploadedDirName = uniqid().uniqid().'.tmp';
            $this->tempUploadedDirName = $tempUploadedDirName;
            $this->warn("Don't remove {$tempUploadedDirName}, it will be remove after upload.");

            $this->task("<comment>moving {$this->dirPathWillBe}/ to {$tempUploadedDirName}</comment>", fn () => $this->failWhen(
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
        $this->newLine();
        $totalSize = readable_size($this->totalUploadedBytes);

        if ($mod === self::REPLACE_IT) {
            $this->task("<comment>removing {$this->tempUploadedDirName}</comment>", fn () => $this->failWhen(
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

        if ($this->option('merge') === true) {
            $mod = PutCommand::MERGE_IT;
        }
        if ($this->option('replace') === true) {
            $mod = PutCommand::REPLACE_IT;
        }
        if ($mod === PutCommand::UPLOAD_DIRECTLY) {
            $mod = $this->choice(
                "Directory <comment>{$this->dirPathWillBe}</comment> exists in disk <comment>{$this->disk}</comment>",
                [self::DELETE_FROM_DISK, self::SELECT_NEW_NAME, self::REPLACE_IT, self::MERGE_IT]
            );
        }

        return $mod;
    }

    private function manageUpload(string $mod, string $dirPath): void
    {
        if ($mod === self::UPLOAD_DIRECTLY) {
            $this->uploadFolder($dirPath, $dirPath);

            return;
        }
        if ($mod === self::REPLACE_IT) {
            $this->uploadReplaceFolder($dirPath, $dirPath, $this->tempUploadedDirName);

            return;
        }
        if ($mod === self::MERGE_IT) {
            $this->uploadMergeFolder($dirPath, $dirPath);

            return;
        }
    }

    /**
     * Replaces directory with previously uploaded one.
     * Files that are same with previously uploaded ones, won't be upload again(uses uploaded one).
     *
     * @param  string  $dir
     * @param  string  $baseDirPath
     * @param  string  $tempUploadedDirName
     * @return void
     */
    public function uploadReplaceFolder(string $dir, string $baseDirPath, string $tempUploadedDirName): void
    {
        $readableDir = (string) str($dir)->replace(
            str($baseDirPath)->rtrim(DIRECTORY_SEPARATOR), basename($baseDirPath)
        );

        $allFiles = FileManager::allFiles($dir);
        $allDirs = FileManager::allDir($dir);

        if (count($allFiles) === 0 and count($allDirs) === 0) {
            $this->writeMessageNL(" <info>mkdir</info> <comment>$readableDir</comment>");
            $this->uploadEmptyDir($dir, $baseDirPath);
            $this->cursor->clearLine()->moveUp()->clearLine()->moveUp();
            $this->progressBar->advance();
        }

        foreach ($allFiles as $file) {
            $diskFilePath = str($file)->replace(
                str($baseDirPath)->rtrim(DIRECTORY_SEPARATOR),
                $tempUploadedDirName
            );

            $this->uploadFileIfNotExistsOrNotSame($file, $diskFilePath, $baseDirPath, true);

            $this->progressBar->advance();
        }

        foreach ($allDirs as $dir) {
            $this->uploadReplaceFolder($dir, $baseDirPath, $tempUploadedDirName);
        }
    }

    /**
     * Uploads new files, or files that are different with previously uploaded ones.
     * Creates empty directories that aren't exists.
     *
     * @param  string  $dir
     * @param  string  $baseDirPath
     * @return void
     */
    private function uploadMergeFolder(string $dir, string $baseDirPath): void
    {
        $readableDir = (string) str($dir)->replace(
            str($baseDirPath)->rtrim(DIRECTORY_SEPARATOR), basename($baseDirPath)
        );

        $allFiles = FileManager::allFiles($dir);
        $allDirs = FileManager::allDir($dir);

        if (count($allFiles) === 0 and count($allDirs) === 0) {
            $diskPath = str($dir)->replace(
                str($baseDirPath)->rtrim(DIRECTORY_SEPARATOR),
                $this->dirPathWillBe
            );

            $this->writeMessageNL(" Checking dir <comment>$readableDir</comment>");
            $dirExistsInDisk = Storage::disk($this->disk)->directoryExists($diskPath);
            $this->cursor->clearLine()->moveUp()->clearLine()->moveUp();

            if (! $dirExistsInDisk) {
                $this->writeMessageNL(" <info>mkdir</info> <comment>$readableDir</comment>");
                $this->uploadEmptyDir($dir, $baseDirPath);
                $this->cursor->clearLine()->moveUp()->clearLine()->moveUp();
            }

            $this->progressBar->advance();
        }

        foreach ($allFiles as $file) {
            $diskFilePath = str($file)->replace(
                str($baseDirPath)->rtrim(DIRECTORY_SEPARATOR),
                $this->dirPathWillBe
            );

            $this->uploadFileIfNotExistsOrNotSame($file, $diskFilePath, $baseDirPath);

            $this->progressBar->advance();
        }

        foreach ($allDirs as $dir) {
            $this->uploadMergeFolder($dir, $baseDirPath);
        }
    }

    private function uploadFileIfNotExistsOrNotSame(
        string $file,
        string $diskFilePath,
        string $baseDirPath,
        bool $moveDiskFilePathToFilePathWhenFileNotUploaded = false
    ): void {
        $readableFile = (string) str($file)->replace(
            str($baseDirPath)->rtrim(DIRECTORY_SEPARATOR),
            basename($baseDirPath)
        );
        $fileSize = readable_size(filesize($file));

        $this->writeMessageNL(" Checking file <comment>{$readableFile}({$fileSize})</comment>");
        $fileExistsInDisk = Storage::disk($this->disk)->exists($diskFilePath);
        $filesAreSame = true;
        if ($fileExistsInDisk) {
            $filesAreSame = $this->filesAreSame($file, fileOnDisk: $diskFilePath);
        }
        $this->cursor->clearLine()->moveUp()->clearLine()->moveUp();

        if (! $fileExistsInDisk or ! $filesAreSame) {
            $this->writeMessageNL(" <info>Uploading</info> <comment>{$readableFile}({$fileSize})</comment>");
            $this->uploadFile($file, $baseDirPath);
            $this->cursor->clearLine()->moveUp()->clearLine()->moveUp();
        } elseif ($moveDiskFilePathToFilePathWhenFileNotUploaded) {
            $filePath = str($file)->replace(
                str($baseDirPath)->rtrim(DIRECTORY_SEPARATOR),
                $this->dirPathWillBe
            );
            $this->writeMessageNL(" <info>moving</info> <comment>{$diskFilePath} to {$filePath}</comment>");

            $this->failWhen(
                ! Storage::disk($this->disk)->move($diskFilePath, $filePath),
                "Counldn't move file {$diskFilePath} to {$filePath}. Check your connection, or set disk authorization tokens."
            );
            //test working with to-dir
            $this->cursor->clearLine()->moveUp()->clearLine()->moveUp();
        }
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
        if (md5($fileContent) === md5($fileOnDiskContent)) {
            return true;
        }

        return false;
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
        $diskPath = str($dir)->replace(
            str($baseDirPath)->rtrim(DIRECTORY_SEPARATOR),
            $this->dirPathWillBe
        );

        $this->failWhen(
            ! Storage::disk($this->disk)->makeDirectory($diskPath),
            "Counldn't create directory in disk path {$diskPath}. Check your connection, or set disk authorization tokens."
        );
    }

    private function uploadFile(string $file, string $baseDirPath): void
    {
        $filePath = str($file)->replace(
            str($baseDirPath)->rtrim(DIRECTORY_SEPARATOR),
            $this->dirPathWillBe
        );

        $fileContent = file_get_contents($file);

        if ($fileContent === '') {
            $fileContent = ' ';
        }

        $this->failWhen(
            ! Storage::disk($this->disk)->put($filePath, $fileContent),
            "Counldn't create file in disk path {$filePath}. Check your connection, or set disk authorization tokens."
        );

        $this->totalUploadedBytes += filesize($file);
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
            } catch (\Exception $e) {
                $this->error($e->getMessage());

                return false;
            }

            DiskManager::fillTokensOf($disk, $tokens);
        }

        return true;
    }
}
