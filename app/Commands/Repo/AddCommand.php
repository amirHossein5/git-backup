<?php

namespace App\Commands\Repo;

use App\Services\RepositoryManager;
use App\Traits\HasForcedOptions;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Command\Command as Output;

class AddCommand extends Command
{
    use HasForcedOptions;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'repo:add {--repo-path=} {--to-dir=} {--fetch-branches=true}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Adds mirrored local git repo to the passed directory.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if (! $this->hasAllOptions('repo-path', 'to-dir')) {
            return Output::FAILURE;
        }

        $repoPath = $this->option('repo-path');
        $toDir = $this->option('to-dir');
        $repoFolderName = basename($repoPath);
        $repoPathWillBe = pathable("{$toDir}/{$repoFolderName}.git");

        if (! is_dir($toDir)) {
            $this->error("Could'nt find folder in path: {$toDir}");

            return Output::FAILURE;
        }
        if (! is_dir($repoPath)) {
            $this->error("Could'nt find folder in path: {$repoPath}");

            return Output::FAILURE;
        }
        if (is_dir($repoPathWillBe)) {
            $this->error("Directory already exists: {$repoPathWillBe}");

            return Output::FAILURE;
        }

        if (! RepositoryManager::isGitRepo($repoPath)) {
            $this->error("It's not a git repo: {$repoPath}");

            return Output::FAILURE;
        }

        $this->newLine();

        if ($this->option('fetch-branches') === 'true') {
            if (RepositoryManager::hasAnyBranches($repoPath)) {
                $this->info('Getting/Fetching all remote branches of <comment>'. basename($repoPath).'</comment>...');
                RepositoryManager::fetchAllBranches($repoPath);
                $this->newLine();
            }
        }

        $this->info('Cloning <comment>'. basename($repoPath).'</comment> to '. $repoPathWillBe);
        RepositoryManager::clone($toDir, "git clone --mirror {$repoPath}", afterNewLine: false);

        return Output::SUCCESS;
    }
}
