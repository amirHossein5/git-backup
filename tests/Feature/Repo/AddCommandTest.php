<?php

// use App\Services\RepositoryManager;
// use Illuminate\Support\Facades\Artisan;
// use Illuminate\Support\Facades\Storage;
// use Symfony\Component\Console\Command\Command;

// beforeEach(function() {
//     RepositoryManager::clearStats();
// });

// test('test required options', function () {
//     $this->artisan('repo:add')
//         ->expectsOutput('Option --repo-path is required.')
//         ->assertExitCode(Command::FAILURE);

//     $this->artisan('repo:add --repo-path=some/path')
//         ->expectsOutput('Option --to-dir is required.')
//         ->assertExitCode(Command::FAILURE);

//     $this->artisan('repo:add --repo-path=some/notfound/path --to-dir=some/not/found/path')
//         ->expectsOutput("Could'nt find folder in path: some/not/found/path")
//         ->assertExitCode(Command::FAILURE);
// });

// it('shows error when to-dir is not folder or not exists', function () {
//     $this->artisan('repo:add --repo-path=some/notfound/path --to-dir=some/not/found/path')
//         ->expectsOutput("Could'nt find folder in path: some/not/found/path")
//         ->assertExitCode(Command::FAILURE);

//     $filePath = __FILE__;

//     $this->artisan('repo:add --repo-path=some/notfound/path --to-dir='.$filePath)
//         ->expectsOutput("Could'nt find folder in path: {$filePath}")
//         ->assertExitCode(Command::FAILURE);
// });

// it('shows error when repo-path is not folder or not exists', function () {
//     $toDir = __DIR__;

//     $this->artisan('repo:add --repo-path=some/notfound/path --to-dir='.$toDir)
//         ->expectsOutput("Could'nt find folder in path: some/notfound/path")
//         ->assertExitCode(Command::FAILURE);

//     $filePath = __FILE__;

//     $this->artisan("repo:add --repo-path={$filePath} --to-dir={$toDir}")
//         ->expectsOutput("Could'nt find folder in path: {$filePath}")
//         ->assertExitCode(Command::FAILURE);
// });

// it('shows error when a folder already exists in to-dir that has same name to repo-path', function () {
//     $repoPath = base_path();
//     $toDir = $this->tempDirPath;
//     $repoFolderName = basename($repoPath);
//     $repoPathWillBe = pathable("{$toDir}/{$repoFolderName}.git");

//     Storage::disk('local')
//         ->makeDirectory('tests/temp/'.$repoFolderName.'.git');

//     $this->artisan("repo:add --repo-path={$repoPath} --to-dir={$toDir}")
//         ->expectsOutput("Directory already exists: {$repoPathWillBe}")
//         ->assertExitCode(Command::FAILURE);
// });

// it('shows error when repo-path is not a git repo', function () {
//     $repoName = uniqid();
//     $repoPath = realpath(base_path().'/../')."/{$repoName}";
//     $toDir = $this->tempDirPath;

//     if (is_dir($repoPath)) {
//         rmdir($repoPath);
//     }

//     mkdir($repoPath);

//     $this->artisan("repo:add --repo-path={$repoPath} --to-dir={$toDir}")
//         ->expectsOutput("It's not a git repo: {$repoPath}")
//         ->assertExitCode(Command::FAILURE);

//     rmdir($repoPath);
// });

// it('fetches branches if has any', function () {
//     $repoName = uniqid();
//     $repoPath = realpath(base_path().'/../')."/{$repoName}";
//     $toDir = $this->tempDirPath;

//     $this->mkGitDirectory($repoPath);

//     expect(
//         Artisan::call("repo:add --repo-path={$repoPath} --to-dir={$toDir}")
//     )->toBe(Command::SUCCESS);

//     rmdir_recursive($repoPath);

//     expect(count(RepositoryManager::getFetchedAllBranchesOfRepos()))->toBe(0);
//     expect(count(RepositoryManager::getClonedReposTo()))->toBe(1);
//     expect(RepositoryManager::getClonedReposTo()[0])->toBe($toDir);
// });

// it('won\'t fetch branches if false option passed');

// it('clones repo to specified directory and fetches branches', function () {});

