<?php

function pathable(string $path): string
{
    return str($path)->replace('/', DIRECTORY_SEPARATOR);
}
