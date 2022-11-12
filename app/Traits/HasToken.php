<?php

namespace App\Traits;

use App\Exceptions\JsonDecodeException;
use App\Services\DiskManager;
use App\Services\JsonDecoder;

trait HasToken
{
    private function manageDiskTokens(?string $tokensPath, string $disk): bool
    {
        if ($tokensPath) {
            try {
                $tokens = JsonDecoder::decodePath($tokensPath);
            } catch (JsonDecodeException $e) {
                $this->error('Error when decoding json:');
                $this->error($e->getMessage());

                return false;
            } catch (\Exception $e) {
                $this->error($e->getMessage());

                return false;
            }

            DiskManager::fillTokensOf($disk, $tokens);
        }

        return true;
    }
}
