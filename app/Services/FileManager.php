<?php

namespace App\Services;

use Illuminate\Support\Collection;

class FileManager
{
    /**
     * Returns all (not nested) files of directory.
     * @param  string $dir
     * @return array
     */
    public static function allFiles(string $dir): array
    {
        $dir .= DIRECTORY_SEPARATOR;
        $dir = str($dir)->rtrim(DIRECTORY_SEPARATOR);
        $ffs = scandir($dir);

        unset($ffs[array_search('.', $ffs, true)]);
        unset($ffs[array_search('..', $ffs, true)]);

        // prevent empty ordered elements
        if (count($ffs) < 1)
            return [];

        $allFiles = [];

        foreach($ffs as $ff){
            $ff = $dir.DIRECTORY_SEPARATOR.$ff;

            if (! is_dir($ff) and file_exists($ff)) {
                $allFiles[] = $ff;
            }
        }

        return $allFiles;
    }

    /**
     * Returns all (not nested) directories of directory.
     * @param  string $dir
     * @return array
     */
    public static function allDir(string $dir): array
    {
        $dir .= DIRECTORY_SEPARATOR;
        $dir = str($dir)->rtrim(DIRECTORY_SEPARATOR);
        $ffs = scandir($dir);

        unset($ffs[array_search('.', $ffs, true)]);
        unset($ffs[array_search('..', $ffs, true)]);

        // prevent empty ordered elements
        if (count($ffs) < 1)
            return [];

        $allFolders = [];

        foreach($ffs as $ff){
            $ff = $dir.DIRECTORY_SEPARATOR.$ff;

            if (is_dir($ff) and file_exists($ff)) {
                $allFolders[] = $ff;
            }
        }

        return $allFolders;
    }

    public static function countAllNestedFiles(string $dirPath): int
    {
        $count = 0;

        $count += count(FileManager::allFiles($dirPath));

        foreach (FileManager::allDir($dirPath) as $dir) {
            $count += FileManager::countAllNestedFiles($dir);
        }

        return $count;
    }

    public static function countNestedEmptyFolders(string $dirPath): int
    {
        $count = 0;

        foreach (FileManager::allDir($dirPath) as $dir) {
            if (count(FileManager::allFiles($dir)) === 0 and count(FileManager::allDir($dir)) === 0) {
                $count ++;
            } else {
                $count += FileManager::countNestedEmptyFolders($dir);
            }
        }

        return $count;
    }
}
