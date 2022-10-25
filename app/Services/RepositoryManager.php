<?php

namespace App\Services;

use App\Services\RepositoryManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class RepositoryManager
{
    public static function filterServersBy(array $servers, string $matches): array
    {
        return collect($servers)->filter(fn ($server) =>
            str($server['name'])->contains($matches)
        )->toArray();
    }

    public static function cloneOrFetch(string $cloneTo, string $repoName, string $gitCloneCommand): Collection
    {
        if (is_dir($dir = $cloneTo.DIRECTORY_SEPARATOR.$repoName)) {
            return self::fetch($dir);
        }

        self::clone($cloneTo, $gitCloneCommand);

        return collect();
    }

    public static function getRepoNamesFromApi(string $url, null|string $token = null, string $pattern="*.name"): Collection
    {
        $http = Http::acceptJson();

        if ($token) {
            $http->withToken($token);
        }

        $response = $http->get($url);

        if ($response->failed()) {
            $message = isset($response->json()['message'])
                ? "Message: ".$response->json()['message']
                : null;

            throw new \Exception("Request failed with status code: ". $response->status().". ". $message);
        }

        return collect(data_get($response->json(), $pattern))->filter();
    }

    private static function clone(string $cloneTo, string $gitCloneCommand): void
    {
        system("cd {$cloneTo}; echo; $gitCloneCommand");
    }

    private static function fetch(string $dir): Collection
    {
        exec("cd {$dir}; git fetch 2>&1", $output);

        return collect($output);
    }
}
