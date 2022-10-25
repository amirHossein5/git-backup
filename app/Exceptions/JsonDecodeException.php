<?php

namespace App\Exceptions;

class JsonDecodeException extends \Exception
{
    public function __construct($msg)
    {
        parent::__construct($msg);
    }
}
