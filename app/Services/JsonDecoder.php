<?php

namespace App\Services;

use App\Exceptions\FileNotFoundException;
use App\Exceptions\JsonDecodeException;
use HJSON\HJSONParser;

class JsonDecoder
{
    public static function decode(string $jsonContent): array
    {
        $parser = new HJSONParser();

        try {
            $decodedJson = $parser->parse($jsonContent, ['assoc' => true]);
        } catch (\Exception $e) {
            throw new JsonDecodeException($e->getMessage());
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
