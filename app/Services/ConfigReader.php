<?php

namespace App\Services;

use App\Exceptions\FileNotFoundException;
use App\Exceptions\JsonDecodeException;
use App\Exceptions\RequiredKeyMissing;

class ConfigReader
{
    private static array $requiredKeys = [
        'servers.*.name',
        'servers.*.clone.to',
        'servers.*.clone.using',
    ];

    /**
     * If key is missing value(s) are required, in servers scope(key).
     */
    private static array $requiredIfMissingInServers = [
        "repo-names.names" => [
            "repo-names.from-api",
            "repo-names.pattern",
        ],
    ];

    public function __construct(private array $config) {}

    public static function read(string $jsonContent): ConfigReader
    {
        if (! $decodedJson = json_decode($jsonContent, true)) {
            throw new JsonDecodeException(json_last_error_msg());
        }

        $decodedJson = self::wrapUse($decodedJson);

        collect(self::$requiredKeys)->each(function($forceKey) use ($decodedJson) {
            self::throwExceptionIfKeyDoesNotExist($decodedJson, $forceKey, count($decodedJson['servers']));
        });

        collect(self::$requiredIfMissingInServers)->each(function($requiredKeys, $key) use ($decodedJson) {

            foreach($decodedJson['servers'] as $server) {
                $searchForKey = collect(data_get($server, $key))->filter();

                if ($searchForKey->count() !== 0) {
                    continue;
                }

                foreach ($requiredKeys as $requiredKey) {
                    self::throwExceptionIfKeyDoesNotExist($server, $requiredKey, 1);
                }
            }

        });

        return new ConfigReader(self::getTranslatedConfig($decodedJson));
    }

    public function servers(): array
    {
        return $this->config['servers'];
    }

    public function setServers(array $servers): void
    {
        $this->config['servers'] = $servers;
    }

    private static function wrapUse(array $decodedJson): array
    {
        $decodedJson = self::wrapUseInServerRepoNames($decodedJson);

        return $decodedJson;
    }

    private static function wrapUseInServerRepoNames(array $decodedJson): array
    {
        foreach($decodedJson['servers'] as $serverKey => $server) {
            if (! isset($server['repo-names']['use'])) {
                continue;
            }

            if (! file_exists($server['repo-names']['use'])) {
                throw new FileNotFoundException('file not found: '.$server['repo-names']['use']);
            }

            if (is_dir($server['repo-names']['use'])) {
                throw new \Exception('Use must be json file not directory: '.$server['repo-names']['use']);
            }

            if (! $useArray = json_decode(file_get_contents($server['repo-names']['use']), true)) {
                throw new JsonDecodeException(json_last_error_msg());
            }

            foreach($useArray['repo-names'] as $key => $item) {
                if (isset($server['repo-names'][$key])) {
                    continue;
                }

                $decodedJson['servers'][$serverKey]['repo-names'][$key] = $item;
            }

            unset($decodedJson['servers'][$serverKey]['repo-names']['use']);
        }

        return $decodedJson;
    }

    private static function throwExceptionIfKeyDoesNotExist(array $array, string $requiredKey, int $expectedCount): void
    {
        $key = collect(data_get($array, $requiredKey))
                ->filter();

        if ($key->count() !== $expectedCount) {
            throw new RequiredKeyMissing($requiredKey);
        }
    }

    /**
     * Translates the user given infos to program intended keys.
     * @param  array  $servers
     * @return array
     */
    private static function getTranslatedConfig(array $decodedJson): array
    {
        $config = ['servers' => []];

        collect($decodedJson['servers'])->each(function($server) use (&$config) {
            $config['servers'][] = [
                "name" => $server['name'],
                "clone" => [
                    "to" => $server['clone']['to'],
                    "using" => $server['clone']['using'],
                ],
                "repo-names" => [
                    "from-api" => isset($server['repo-names']['from-api']) ? $server['repo-names']['from-api'] : null,
                    "pattern"  => isset($server['repo-names']['pattern']) ? $server['repo-names']['pattern'] : null,
                    "names"    => isset($server['repo-names']['names']) ? $server['repo-names']['names'] : null,
                    "token"    => isset($server['repo-names']['token']) ? $server['repo-names']['token'] : null,
                ],
            ];
        });

        return $config;
    }
}
