<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class RepositoryManager
{
    public static function hasAnyBranches(string $repoPath): bool
    {
        $command = self::getCommandToSeeAllBranches();

        exec("cd {$repoPath} && {$command} 2>&1", $output);

        return count($output) !== 0;
    }

    public static function filterServersBy(array $servers, string $matches): array
    {
        return collect($servers)->filter(fn ($server) => str($server['name'])->contains($matches)
        )->toArray();
    }

    public static function fetchAllBranches(string $repoPath): void
    {
        system("cd {$repoPath} && git fetch --all; git pull --all;".self::getCommandToFetchAllBranches());
    }

    public static function isGitRepo(string $dir): bool
    {
        exec("cd {$dir} && git status 2>&1", $output);

        return str($output[0])->contains('On branch');
    }

    public static function cloneOrFetch(string $cloneTo, string $repoName, string $gitCloneCommand): Collection
    {
        if (is_dir($dir = $cloneTo.DIRECTORY_SEPARATOR.$repoName)) {
            return self::fetch($dir);
        }

        self::clone($cloneTo, $gitCloneCommand);

        return collect();
    }

    public static function getRepoNamesFromApi(string $url, null|string $token = null, string $pattern = '*.name'): Collection
    {
        $http = Http::acceptJson();

        if ($token) {
            $http->withToken($token);
        }

        $response = $http->get($url);

        if ($response->failed()) {
            $message = isset($response->json()['message'])
                ? 'Message: '.$response->json()['message']
                : null;

            throw new \Exception('Request failed with status code: '.$response->status().'. '.$message);
        }

        return collect(data_get($response->json(), $pattern))->filter();
    }

    public static function clone(string $cloneTo, string $gitCloneCommand, bool $afterNewLine = true): void
    {
        $command = "cd {$cloneTo} && ";

        if ($afterNewLine) {
            $command .= 'echo; ';
        }

        system($command.$gitCloneCommand);
    }

    public static function fetch(string $dir): Collection
    {
        exec("cd {$dir} && git fetch 2>&1", $output);

        return collect($output);
    }

    private static function getCommandToFetchAllBranches(): string
    {
        return 'git branch -r | grep -v \'\->\' | sed "s,\x1B\[[0-9;]*[a-zA-Z],,g" | while read remote; do git branch --track "${remote#origin/}" "$remote"; done';
    }

    private static function getCommandToSeeAllBranches(): string
    {
        return 'git branch -r';
    }
}
