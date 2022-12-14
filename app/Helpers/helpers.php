<?php

function pathable(?string $path): ?string
{
    return $path === null
        ? null
        : str($path)->replace('/', DIRECTORY_SEPARATOR);
}

function chunk_iterator(Iterator $it, int $n)
{
    $chunk = [];

    for ($i = 0; $it->valid(); $i++) {
        $chunk[] = $it->current();
        $it->next();
        if (count($chunk) == $n) {
            yield $chunk;
            $chunk = [];
        }
    }

    if (count($chunk)) {
        yield $chunk;
    }
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
        $size_kb = round($size / 1024, 2);

        return "{$size_kb} KB";
    } else {
        $size_mb = round($size / 1000000, 2);
        $size_gb = round($size_mb / 1024, 2);

        if ($size_gb >= 1) {
            return "{$size_gb} GB";
        }

        return "{$size_mb} MB";
    }
}

/**
 * If the given path contains ~ replaces it with user home directory.
 * @param  string $path
 * @return string
 */
function resolvehome(string $path): string
{
    if (! str($path)->startsWith('~')) {
        return $path;
    }

    $home = null;

    if (isset($_SERVER['HOME'])) {
        $home = $_SERVER['HOME'];
    }

    if ($home) {
        return str($path)->replaceFirst('~', $home);
    }

    return $path;
}
