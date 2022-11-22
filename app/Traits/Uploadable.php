<?php

namespace App\Traits;

use App\Services\FileManager;
use App\Services\Terminal;
use Illuminate\Support\Facades\Storage;

trait Uploadable
{
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
        if ($mod === self::UPLOAD_REMAINED) {
            $this->uploadRemainedFolder($dirPath, $dirPath);
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
            str($baseDirPath)->rtrim(DIRECTORY_SEPARATOR),
            basename($baseDirPath)
        );

        $allFiles = FileManager::allFiles($dir);
        $allDirs = FileManager::allDir($dir);

        if (count($allFiles) === 0 and count($allDirs) === 0) {
            $fittedReadableDir = Terminal::fitWidth($readableDir, usedWidth: 7);
            $this->writeMessageNL(" <info>mkdir</info> <comment>$fittedReadableDir</comment>");
            $this->uploadEmptyDir($dir, $baseDirPath);
            $this->cursor->clearLine()->moveUp()->clearLine()->moveUp();
            $this->logDir($readableDir);
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
            str($baseDirPath)->rtrim(DIRECTORY_SEPARATOR),
            basename($baseDirPath)
        );

        $allFiles = FileManager::allFiles($dir);
        $allDirs = FileManager::allDir($dir);

        if (count($allFiles) === 0 and count($allDirs) === 0) {
            $diskPath = str($dir)->replace(
                str($baseDirPath)->rtrim(DIRECTORY_SEPARATOR),
                $this->dirPathWillBe
            );

            $fittedReadableDir = Terminal::fitWidth($readableDir, usedWidth: 14);
            $this->writeMessageNL(" Checking dir <comment>$fittedReadableDir</comment>");
            $dirExistsInDisk = Storage::disk($this->disk)->directoryExists($diskPath);
            $this->cursor->clearLine()->moveUp()->clearLine()->moveUp();

            if (! $dirExistsInDisk) {
                $fittedReadableDir = Terminal::fitWidth($readableDir, usedWidth: 7);
                $this->writeMessageNL(" <info>mkdir</info> <comment>$fittedReadableDir</comment>");
                $this->uploadEmptyDir($dir, $baseDirPath);
                $this->cursor->clearLine()->moveUp()->clearLine()->moveUp();
                $this->logDir($readableDir);
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

    /**
     * Uploads new files, or directories.
     *
     * @param  string  $dir
     * @param  string  $baseDirPath
     * @return void
     */
    private function uploadRemainedFolder(string $dir, string $baseDirPath): void
    {
        $readableDir = (string) str($dir)->replace(
            str($baseDirPath)->rtrim(DIRECTORY_SEPARATOR),
            basename($baseDirPath)
        );

        $allFiles = FileManager::allFiles($dir);
        $allDirs = FileManager::allDir($dir);

        if (count($allFiles) === 0 and count($allDirs) === 0) {
            $diskPath = str($dir)->replace(
                str($baseDirPath)->rtrim(DIRECTORY_SEPARATOR),
                $this->dirPathWillBe
            );

            $fittedReadableDir = Terminal::fitWidth($readableDir, usedWidth: 14);
            $this->writeMessageNL(" Checking dir <comment>$fittedReadableDir</comment>");
            $dirExistsInDisk = Storage::disk($this->disk)->directoryExists($diskPath);
            $this->cursor->clearLine()->moveUp()->clearLine()->moveUp();

            if (! $dirExistsInDisk) {
                $fittedReadableDir = Terminal::fitWidth($readableDir, usedWidth: 7);
                $this->writeMessageNL(" <info>mkdir</info> <comment>$fittedReadableDir</comment>");
                $this->uploadEmptyDir($dir, $baseDirPath);
                $this->cursor->clearLine()->moveUp()->clearLine()->moveUp();
                $this->logDir($readableDir);
            }

            $this->progressBar->advance();
        }

        foreach ($allFiles as $file) {
            $diskFilePath = str($file)->replace(
                str($baseDirPath)->rtrim(DIRECTORY_SEPARATOR),
                $this->dirPathWillBe
            );

            $this->uploadFileIfNotExists($file, $diskFilePath, $baseDirPath);

            $this->progressBar->advance();
        }

        foreach ($allDirs as $dir) {
            $this->uploadRemainedFolder($dir, $baseDirPath);
        }
    }

    private function uploadFolder(string $dir, string $baseDirPath): void
    {
        $readableDir = (string) str($dir)->replace(
            str($baseDirPath)->rtrim(DIRECTORY_SEPARATOR),
            basename($baseDirPath)
        );

        $allFiles = FileManager::allFiles($dir);
        $allDirs = FileManager::allDir($dir);

        if (count($allFiles) === 0 and count($allDirs) === 0) {
            $fittedReadableDir = Terminal::fitWidth($readableDir, usedWidth: 7);
            $this->writeMessageNL(" mkdir <comment>$fittedReadableDir</comment>");
            $this->uploadEmptyDir($dir, $baseDirPath);
            $this->cursor->clearLine()->moveUp()->clearLine()->moveUp();
            $this->logDir($readableDir);
            $this->progressBar->advance();
        }

        foreach ($allFiles as $file) {
            $readableFile = (string) str($file)->replace(
                str($baseDirPath)->rtrim(DIRECTORY_SEPARATOR),
                basename($baseDirPath)
            );
            $fileSize = readable_size(filesize($file));

            $fittedMessage = Terminal::fitWidth("{$readableFile}({$fileSize})", usedWidth: 11);
            $this->writeMessageNL(" Uploading <comment>{$fittedMessage}</comment>");
            $this->uploadFile($file, $baseDirPath);
            $this->cursor->clearLine()->moveUp()->clearLine()->moveUp();
            $this->logFile($readableFile, $fileSize);
            $this->progressBar->advance();
        }

        foreach ($allDirs as $dir) {
            $this->uploadFolder($dir, $baseDirPath);
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

    private function deleteDir(): void
    {
        $this->task("Deleted {$this->dirPathWillBe}", fn () => Storage::disk($this->disk)->deleteDirectory($this->dirPathWillBe));
    }

    private function uploadFileIfNotExists(
        string $file,
        string $diskFilePath,
        string $baseDirPath,
    ): void {
        $readableFile = (string) str($file)->replace(
            str($baseDirPath)->rtrim(DIRECTORY_SEPARATOR),
            basename($baseDirPath)
        );
        $fileSize = readable_size(filesize($file));

        $fittedMessage = Terminal::fitWidth("{$readableFile}({$fileSize})", usedWidth: 15);
        $this->writeMessageNL(" Checking file <comment>{$fittedMessage}</comment>");
        $fileExistsInDisk = Storage::disk($this->disk)->exists($diskFilePath);
        $this->cursor->clearLine()->moveUp()->clearLine()->moveUp();

        if (! $fileExistsInDisk) {
            $fittedMessage = Terminal::fitWidth("{$readableFile}({$fileSize})", usedWidth: 11);
            $this->writeMessageNL(" <info>Uploading</info> <comment>{$fittedMessage}</comment>");
            $this->uploadFile($file, $baseDirPath);
            $this->cursor->clearLine()->moveUp()->clearLine()->moveUp();
            $this->logFile($readableFile, $fileSize);
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

        $fittedMessage = Terminal::fitWidth("{$readableFile}({$fileSize})", usedWidth: 15);
        $this->writeMessageNL(" Checking file <comment>{$fittedMessage}</comment>");
        $fileExistsInDisk = Storage::disk($this->disk)->exists($diskFilePath);
        $filesAreSame = true;
        if ($fileExistsInDisk) {
            $filesAreSame = $this->filesAreSame($file, fileOnDisk: $diskFilePath);
        }
        $this->cursor->clearLine()->moveUp()->clearLine()->moveUp();

        if (! $fileExistsInDisk or ! $filesAreSame) {
            $fittedMessage = Terminal::fitWidth("{$readableFile}({$fileSize})", usedWidth: 11);
            $this->writeMessageNL(" <info>Uploading</info> <comment>{$fittedMessage}</comment>");
            $this->uploadFile($file, $baseDirPath);
            $this->cursor->clearLine()->moveUp()->clearLine()->moveUp();
            $this->logFile($readableFile, $fileSize);
        } elseif ($moveDiskFilePathToFilePathWhenFileNotUploaded) {
            $filePath = str($file)->replace(
                str($baseDirPath)->rtrim(DIRECTORY_SEPARATOR),
                $this->dirPathWillBe
            );
            $fittedMessage = Terminal::fitWidth("{$diskFilePath} to {$filePath}", usedWidth: 8);
            $this->writeMessageNL(" <info>moving</info> <comment>{$fittedMessage}</comment>");

            $this->failWhen(
                ! Storage::disk($this->disk)->move($diskFilePath, $filePath),
                "Counldn't move file {$diskFilePath} to {$filePath}. Check your connection, or set disk authorization tokens."
            );

            $this->cursor->clearLine()->moveUp()->clearLine()->moveUp();
        }
    }
}
