<?php

namespace App\Traits;

use App\Services\Terminal;

trait Loggable
{
    private function logFile(string $filePath, string $fileSize): void
    {
        $fileSize = "($fileSize)";
        $fittedFilePath = Terminal::fitWidth($filePath, $usedWidth = 2 + strlen($fileSize) + 1);
        $logMessage = Terminal::mkTwoColMessage("-<comment> $fittedFilePath</comment>", $fileSize);

        $isOverlapped = str($fittedFilePath)->endsWith('..');

        if ($isOverlapped) {
            $this->log($logMessage, "- $filePath  $fileSize");
        } else {
            $this->log($logMessage, "");
        }
    }

    private function logDir(string $dirPath): void
    {
        $fittedDirPath = Terminal::fitWidth($dirPath, usedWidth: 2);
        $logMessage = "d<comment> $fittedDirPath</comment>";

        $this->log($logMessage, "d $dirPath");
    }

    private function log(string $message, ?string $fileLogMessage = null): void
    {
        $this->progressBar->clear();
        $this->line($message);
        $this->progressBar->display();

        if ($this->logTo) {
            if (! file_exists($this->logTo)) {
                touch($this->logTo);
            }
            if ($fileLogMessage) {
                $message = $fileLogMessage;
            }

            file_put_contents(
                $this->logTo,
                trim(file_get_contents($this->logTo).PHP_EOL.'['.now().'] '.Terminal::clearTags($message))
            );
        }
    }
}
