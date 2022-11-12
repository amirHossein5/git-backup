<?php

namespace App\Services;

class Terminal
{
    public static function mkTwoColMessage(string $firstCol, string $secondCol, string $fillWith = '-'): string
    {
        $firstColWidth = strlen(Terminal::clearTags($firstCol));
        $secondColWidth = strlen(Terminal::clearTags($secondCol));
        $usedWidth = $firstColWidth + $secondColWidth;
        $remainedWidth = (new \Termwind\Terminal())->width()-$usedWidth;

        $firstCol .= ' ';

        if ($remainedWidth > 1) {
            for ($i=1; $i < $remainedWidth; $i++) {
                $firstCol .= '-';
            }

            $firstCol = str($firstCol)->replaceLast('-', ' ');
        }

        return $firstCol.$secondCol;
    }

    public static function clearTags(string $string): string
    {
        return str($string)->replace(['<comment>', '</comment>', '<info>', '</info>'], '');
    }

    public static function fitWidth(string $string, $usedWidth = 0, string $concatRemovedPartWith = '..', bool $cutFirstOfString = false): string
    {
        $availableWidth = (new \Termwind\Terminal())->width() - $usedWidth - strlen($concatRemovedPartWith);

        if ($availableWidth <= 0) {
            return $concatRemovedPartWith;
        }
        if ($availableWidth > strlen($string)) {
            return $string;
        }
        if ($cutFirstOfString) {
            return $concatRemovedPartWith . substr($string, -$availableWidth);
        }

        return substr($string, 0, $availableWidth) . $concatRemovedPartWith;
    }
}
