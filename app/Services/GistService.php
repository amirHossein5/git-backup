<?php

namespace App\Services;

class GistService
{
    public static function createTitleSeparator(int $len = 0, $separator = '-'): string
    {
        $generatedSeparator = '';

        for ($i=1; $i <= $len ; $i++) {
            $generatedSeparator .= $separator;
        }

        return $generatedSeparator;
    }
}
