<?php

namespace App\Exceptions;

class FileNotFoundException extends \Exception
{
    public function __construct($msg)
    {
        parent::__construct($msg);
    }
}
