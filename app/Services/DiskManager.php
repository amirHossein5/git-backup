<?php

namespace App\Services;

class DiskManager
{
    public static function fillTokensOf(string $disk, array $tokens): void
    {
        if (! $diskConf = config("filesystems.disks.{$disk}")) {
            return;
        }

        unset($tokens['driver']);

        foreach ($tokens as $key => $value) {
            if (array_key_exists($key, $diskConf)) {
                $diskConf[$key] = $value;
            }
        }

        config(["filesystems.disks.{$disk}" => $diskConf]);
    }
}
