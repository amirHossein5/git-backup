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
        'repoNames.names' => [
            'repoNames.fromApi.urls',
            'repoNames.pattern',
        ],
    ];

    public function __construct(private array $config)
    {
    }

    public static function read(string $jsonContent): ConfigReader
    {
        $decodedJson = JsonDecoder::decode($jsonContent);
        $decodedJson = self::resolveUse($decodedJson);
        $decodedJson = static::wrapServers($decodedJson);

        collect(self::$requiredKeys)->each(function ($forceKey) use ($decodedJson) {
            self::throwExceptionIfKeyDoesNotExist($decodedJson, $forceKey, count($decodedJson['servers']));
        });

        $decodedJson = self::getTranslatedConfig($decodedJson);

        collect(self::$requiredIfMissingInServers)->each(function ($requiredKeys, $key) use ($decodedJson) {
            foreach ($decodedJson['servers'] as $server) {
                $searchForKey = collect(data_get($server, $key))->filter();

                if ($searchForKey->count() !== 0) {
                    continue;
                }

                foreach ($requiredKeys as $requiredKey) {
                    self::throwExceptionIfKeyDoesNotExist($server, $requiredKey);
                }
            }
        });

        return new ConfigReader($decodedJson);
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

    /**
     * Wraps config into servers key if it's missing.
     *
     * @param  array  $decodedJson
     * @return array
     */
    private static function wrapServers(array $decodedJson): array
    {
        return isset($decodedJson['servers'])
            ? $decodedJson
            : ['servers' => [$decodedJson]];
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
                    fn ($val, $key) => [str_replace($varKey . '.', '', $key) => $val]
                )->toArray();

                $decodedUse = self::resolveVars(with: $vars, in: $decodedUse, varStart: '<', varEnd: '>');
            }

            foreach ($decodedUse as $key => $value) {
                $generatedKey = str($useKey)->replace('use' . str($useKey)->after('use'), '') . $key;

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

    private static function throwExceptionIfKeyDoesNotExist(array $array, string $requiredKey, ?int $expectedCount = null): void
    {
        $key = collect(data_get($array, $requiredKey))
                ->filter();

        if (! $expectedCount) {
            if ($key->count() === 0) {
                throw new RequiredKeyMissing($requiredKey);
            }
        } else {
            if ($key->count() !== $expectedCount) {
                throw new RequiredKeyMissing($requiredKey);
            }
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
                'repoNames' => [
                    'fromApi' => isset($server['repoNames']['fromApi']) ? [
                        'urls' => self::getApiUrls(
                            $server['repoNames']['fromApi'],
                            isset($server['repoNames']['token']) ? $server['repoNames']['token'] : null
                        )
                    ] : null,
                    'pattern' => isset($server['repoNames']['pattern']) ? $server['repoNames']['pattern'] : null,
                    'names' => isset($server['repoNames']['names']) ? $server['repoNames']['names'] : null,
                    'token' => isset($server['repoNames']['token']) ? $server['repoNames']['token'] : null,
                ],
            ];
        });

        return $config;
    }

    private static function getApiUrls(string|array $apiUrls, ?string $token): array
    {
        if (is_string($apiUrls)) {
            return [$apiUrls];
        }

        if (isset($apiUrls['withPagination']) && $apiUrls['withPagination'] === true) {
            return self::resolvePaginationUrls($apiUrls, $token);
        }

        if (isset($apiUrls['url'])) {
            return [$apiUrls['url']];
        }

        if (isset($apiUrls['urls'])) {
            return $apiUrls['urls'];
        }

        return [];
    }

    private static function resolvePaginationUrls(array $apiUrls, ?string $token): array
    {
        $urls = [];
        $pageQueryString = isset($apiUrls['pageQueryString']) ? $apiUrls['pageQueryString'] : 'page';
        $perPage = (int) $apiUrls['perPage'];

        if (filter_var($apiUrls['total'], FILTER_VALIDATE_URL)) {
            $total = self::getTotalCountFromApi($apiUrls['total'], $token, $apiUrls['totalKey']);
        } else {
            $total = (int) $apiUrls['total'];
        }

        $countPages = ceil($total/$perPage);

        for ($i=1; $i <= $countPages; $i++) {
            $startQueryString = static::hasAnyQueryString($apiUrls['url']) ? '&' : '?';
            $urls[] = $apiUrls['url'] . $startQueryString . "{$pageQueryString}={$i}";
        }

        return $urls;
    }

    private static function hasAnyQueryString(string $url): bool
    {
        return isset(parse_url($url)['query']);
    }

    private static function getTotalCountFromApi(string $url, ?string $token, string $totalKey): int
    {
        return RepositoryManager::getPatternFromApi($url, $token, $totalKey)->first();
    }
}
