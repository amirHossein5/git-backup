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
