<?php

namespace App\Services;

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
}
