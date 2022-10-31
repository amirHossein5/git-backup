<?php

function pathable(string $path): string
{
    return str($path)->replace('/', DIRECTORY_SEPARATOR);
}

function getArtisanCommand(string $command): string
{
    if (config('app.env') === 'production') {
        return './builds/backup '.$command;
    }

    return 'php backup '.$command;
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
