<?php

namespace App\Services;

use App\Exceptions\RequiredKeyMissing;
use Illuminate\Support\Arr;

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
        'repo-names.names' => [
            'repo-names.from-api',
            'repo-names.pattern',
        ],
    ];

    public function __construct(private array $config)
    {
    }

    public static function read(string $jsonContent): ConfigReader
    {
        $decodedJson = JsonDecoder::decode($jsonContent);

        $decodedJson = self::resolveUse($decodedJson);

        collect(self::$requiredKeys)->each(function ($forceKey) use ($decodedJson) {
            self::throwExceptionIfKeyDoesNotExist($decodedJson, $forceKey, count($decodedJson['servers']));
        });

        collect(self::$requiredIfMissingInServers)->each(function ($requiredKeys, $key) use ($decodedJson) {
            foreach ($decodedJson['servers'] as $server) {
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

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setServers(array $servers): void
    {
        $this->config['servers'] = $servers;
    }

    private static function resolveUse(array $decodedJson)
    {
        $arrayDot = Arr::dot($decodedJson);

        $useKeys = collect($arrayDot)->filter(
            fn ($value, $key) => str($key)->explode('.')->filter(fn ($v) => $v === 'use')->count() === 1 &&
            str($key)->explode('.')->contains('use') ||
            str($key)->endsWith('use.from') ||
            str($key)->contains('use.with')
        );

        foreach ($useKeys as $useKey => $value) {
            if (str($useKey)->contains('use.with')) {
                continue;
            }

            $decodedUse = Arr::dot(JsonDecoder::decodePath($value));
            $varKey = (string) str($useKey)->replace('use.from', 'use.with');

            $vars = $useKeys->filter(fn ($val, $key) => str($key)->contains($varKey));

            foreach ($vars->toArray() as $key => $val) {
                unset($arrayDot[$key]);
            }

            if ($vars->isNotEmpty()) {
                $vars = $vars->mapWithKeys(
                    fn ($val, $key) => [str_replace($varKey.'.', '', $key) => $val]
                )->toArray();

                $decodedUse = self::resolveVars(with: $vars, in: $decodedUse);
            }

            foreach ($decodedUse as $key => $value) {
                $generatedKey = str($useKey)->replace('use'.str($useKey)->after('use'), '').$key;

                if (! isset($arrayDot[$generatedKey])) {
                    $arrayDot[$generatedKey] = $value;
                }
            }

            unset($arrayDot[$useKey]);
        }

        return Arr::undot($arrayDot);
    }

    private static function resolveVars(array $with, array $in, string $varStart = '-', string $varEnd = '-'): array
    {
        $in = Arr::dot($in);

        foreach ($with as $var => $varValue) {
            foreach ($in as $key => $value) {
                if (str($value)->contains("{$varStart}{$var}{$varEnd}")) {
                    $in[$key] = str($value)->replace("{$varStart}{$var}{$varEnd}", $varValue)->value;
                }
            }
        }

        return $in;
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
     *
     * @param  array  $servers
     * @return array
     */
    private static function getTranslatedConfig(array $decodedJson): array
    {
        $config = ['servers' => []];

        collect($decodedJson['servers'])->each(function ($server) use (&$config) {
            $config['servers'][] = [
                'name' => $server['name'],
                'clone' => [
                    'to' => $server['clone']['to'],
                    'using' => $server['clone']['using'],
                ],
                'repo-names' => [
                    'from-api' => isset($server['repo-names']['from-api']) ? $server['repo-names']['from-api'] : null,
                    'pattern' => isset($server['repo-names']['pattern']) ? $server['repo-names']['pattern'] : null,
                    'names' => isset($server['repo-names']['names']) ? $server['repo-names']['names'] : null,
                    'token' => isset($server['repo-names']['token']) ? $server['repo-names']['token'] : null,
                ],
            ];
        });

        return $config;
    }
}
