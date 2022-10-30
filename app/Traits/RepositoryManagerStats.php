<?php

namespace App\Traits;

trait RepositoryManagerStats
{
    private static array $fetchedRepos = [];
    private static array $clonedReposTo = [];
    private static array $fetchedAllBranchesOfRepos = [];

    public static function clearStats(): void
    {
        self::$fetchedAllBranchesOfRepos = [];
        self::$fetchedRepos = [];
        self::$clonedReposTo = [];
    }

    public static function getFetchedRepos(): array
    {
        return self::$fetchedRepos;
    }

    public static function getClonedReposTo(): array
    {
        return self::$clonedReposTo;
    }

    public static function getFetchedAllBranchesOfRepos(): array
    {
        return self::$fetchedAllBranchesOfRepos;
    }

    public static function addFetchedRepos(string $repoPath): void
    {
        self::$fetchedRepos = [...self::$fetchedRepos, $repoPath];
    }

    public static function addClonedReposTo(string $repoPath): void
    {
        self::$clonedReposTo = [...self::$clonedReposTo, $repoPath];
    }

    public static function addFetchedAllBranchesOfRepos(string $repoPath): void
    {
        self::$fetchedAllBranchesOfRepos = [...self::$fetchedAllBranchesOfRepos, $repoPath];
    }
}
