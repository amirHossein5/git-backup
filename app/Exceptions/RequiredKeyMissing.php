<?php

namespace App\Exceptions;

class RequiredKeyMissing extends \Exception
{
    public function __construct($msg)
    {
        parent::__construct($msg);
    }
}
