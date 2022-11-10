<?php

function pathable(?string $path): ?string
{
    return $path === null
        ? null
        : str($path)->replace('/', DIRECTORY_SEPARATOR);
}

function getArtisanCommand(string $command): string
{
    if (config('app.env') === 'production') {
        return './builds/backup ' . $command;
    }

    return 'php backup ' . $command;
}

function rmdir_recursive(string $dir): void
{
    foreach (scandir($dir) as $file) {
        if ('.' === $file || '..' === $file) {
            continue;
        }
        if (is_dir("$dir/$file")) {
            rmdir_recursive("$dir/$file");
        } else {
            unlink("$dir/$file");
        }
    }
    rmdir($dir);
}

function dirsize(string $dir): int
{
    $bytes = 0;
    $dir = realpath($dir);

    if (! is_dir($dir)) {
        return 0;
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $i) {
        $bytes += $i->getSize();
    }
    return $bytes;
}

function readable_size($size): string
{
    if ($size < 1024) {
        return "{$size} bytes";
    } elseif ($size < 1048576) {
        $size_kb = round($size/1024, 1);
        return "{$size_kb} KB";
    } else {
        $size_mb = round($size/1000000, 1);
        $size_gb = round($size_mb/1024, 1);

        if ($size_gb >= 1) {
            return "{$size_gb} GB";
        }

        return "{$size_mb} MB";
    }
}
