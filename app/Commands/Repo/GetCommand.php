<?php

namespace App\Commands\Repo;

use App\Exceptions\FileNotFoundException;
use App\Exceptions\JsonDecodeException;
use App\Exceptions\RequiredKeyMissing;
use App\Services\ConfigReader;
use App\Services\RepositoryManager;
use App\Traits\HasForcedOptions;
use Illuminate\Support\Collection;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Command\Command as Output;

class GetCommand extends Command
{
    use HasForcedOptions;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'repo:get
        {--config=}
        {--repo-matches= : Filters repos.}
        {--matches= : Filter servers based on specified name}
    ';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Clone/Fetch repos from server(s).';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if (! $this->hasAllOptions('config')) {
            return Output::FAILURE;
        }

        $configPath = $this->option('config');

        if (! file_exists($configPath)) {
            $this->error("Counld'nt find config file in path: ".$configPath);

            return Output::FAILURE;
        }

        if (! $config = $this->readConfig($configPath)) {
            return Output::FAILURE;
        }

        if ($matches = $this->option('matches')) {
            $config->setServers(
                RepositoryManager::filterServersBy(
                    servers: $config->servers(),
                    matches: $matches,
                )
            );
        }

        $summaryMessages = [];

        if (count($config->servers()) === 0) {
            $this->error('No server found!');

            return Output::FAILURE;
        }

        foreach ($config->servers() as $data) {
            $this->newLine();
            $this->info(
                'Collecting repository names for: '.
                "<comment>{$data['name']}</comment> "
            );

            $repoNames = $this->getRepoNames($data['repo-names']);

            if ($repoMatches = $this->option('repo-matches')) {
                $repoNames = collect($repoNames)
                    ->filter(
                        fn ($repo) => str($repo)->contains($repoMatches)
                    );
            }

            if (! $repoNames) {
                return Output::FAILURE;
            }

            $reposCount = $repoNames->count();

            $this->info("<comment>{$reposCount}</comment> repository found. Cloning/Fetching...");

            $summaryMessages[] = '';
            $summaryMessages[] = "name: <comment>{$data['name']}</comment>";
            $summaryMessages[] = "found repos count: <comment>{$reposCount}</comment>";
            $summaryMessages[] = "path to repos: {$data['clone']['to']}";

            $this->newLine();

            if (! is_dir($data['clone']['to'])) {
                $this->error('Directory not found: '.$data['clone']['to']);

                continue;
            }

            $this->withProgressBar($repoNames, function ($repoName) use ($data) {
                $output = RepositoryManager::cloneOrFetch(
                    $data['clone']['to'],
                    $repoName.'.git',
                    str("git clone --mirror {$data['clone']['using']}")->replace('<repo>', $repoName)
                );

                if ($output->count() !== 0) {
                    $this->newLine();
                    $output->each(fn ($o) => $this->line($o));
                }
            });

            $this->newLine();
        }

        if (count($summaryMessages) === 0) {
            return Output::SUCCESS;
        }

        $this->newLine();
        $this->warn('Clone/Fetch summary:');

        collect($summaryMessages)
            ->each(fn ($message) => $this->info($message));

        return Output::SUCCESS;
    }

    private function readConfig($configPath): bool|ConfigReader
    {
        try {
            $config = ConfigReader::read(file_get_contents($configPath));
        } catch (JsonDecodeException $e) {
            $this->error('Error when decoding json: ');
            $this->error($e->getMessage());

            return false;
        } catch (RequiredKeyMissing $e) {
            $this->error('required config key '.$e->getMessage().' missing.');

            return false;
        } catch (FileNotFoundException $e) {
            $this->error($e->getMessage());

            return false;
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return false;
        }

        return $config;
    }

    private function getRepoNames($data): bool|Collection
    {
        if (isset($data['names'])) {
            return collect($data['names']);
        }

        try {
            $repoNames = RepositoryManager::getRepoNamesFromApi(
                url: $data['from-api'],
                token: $data['token'],
                pattern: $data['pattern'],
            );
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return false;
        }

        return $repoNames;
    }
}
