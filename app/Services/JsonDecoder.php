<?php

namespace App\Services;

use App\Exceptions\FileNotFoundException;
use App\Exceptions\JsonDecodeException;

class JsonDecoder
{
    public static function decode(string $jsonContent): array
    {
        if (! $decodedJson = json_decode($jsonContent, true)) {
            throw new JsonDecodeException(json_last_error_msg());
        }

        return $decodedJson;
    }

    public static function decodePath(string $path): array
    {
        $path = pathable($path);

        if (is_dir($path)) {
            throw new \Exception("{$path} Is directory not json file.");
        }
        if (! file_exists($path)) {
            throw new FileNotFoundException("File not found: {$path}");
        }

        $json = file_get_contents($path);

        return JsonDecoder::decode($json);
    }
}
