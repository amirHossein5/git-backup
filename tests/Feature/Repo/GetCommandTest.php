<?php

use App\Services\FileManager;
use App\Services\RepositoryManager;
use Symfony\Component\Console\Command\Command;

it('has required options', function () {
    $this->artisan('repo:get')
        ->expectsOutput('Option --config is required.')
        ->assertExitCode(Command::FAILURE);

    $this->artisan('repo:get --config some/not/found')
        ->expectsOutput("Counld'nt find config file in path: " . pathable('some/not/found'))
        ->assertExitCode(Command::FAILURE);
});

it('shows error if config file not found', function () {
    $this->artisan('repo:get --config some/not/found')
        ->expectsOutput("Counld'nt find config file in path: " . pathable('some/not/found'))
        ->assertExitCode(Command::FAILURE);
});

it('shows error if cannot decode config', function () {
    Storage::disk('local')->put('tests/temp/config.json', <<<'EOL'
    {
        // some valid hjson config
    }
    EOL);

    $pathToConfig = Storage::disk('local')->path('tests/temp/config.json');

    $this->artisan('repo:get --config ' . $pathToConfig)
        ->expectsOutput('Undefined array key "servers"')
        ->assertExitCode(Command::FAILURE);

    Storage::disk('local')->put('tests/temp/config.json', <<<'EOL'
    {
        somekey
    }
    EOL);

    $this->artisan('repo:get --config ' . $pathToConfig)
        ->expectsOutput('Error when decoding json: ')
        ->expectsOutputToContain("Found '}' where a key name was expected")
        ->assertExitCode(Command::FAILURE);
});

it('shows error if required config key missing', function () {
    Storage::disk('local')->put('tests/temp/config.json', <<<'EOL'
    {
        sss: sss
    }
    EOL);

    $pathToConfig = Storage::disk('local')->path('tests/temp/config.json');

    $this->artisan('repo:get --config ' . $pathToConfig)
        ->expectsOutput('Undefined array key "servers"')
        ->assertExitCode(Command::FAILURE);

    Storage::disk('local')->put('tests/temp/config.json', <<<'EOL'
    {
        servers: [
            {

            }
        ]
    }
    EOL);

    $this->artisan('repo:get --config ' . $pathToConfig)
        ->expectsOutput('required config key servers.*.name missing.')
        ->assertExitCode(Command::FAILURE);

    Storage::disk('local')->put('tests/temp/config.json', <<<'EOL'
    {
        servers: [
            {
                name: some name
            }
        ]
    }
    EOL);

    $this->artisan('repo:get --config ' . $pathToConfig)
        ->expectsOutput('required config key servers.*.clone.to missing.')
        ->assertExitCode(Command::FAILURE);

    Storage::disk('local')->put('tests/temp/config.json', <<<'EOL'
    {
        servers: [
            {
                name: some name
                clone: {
                    to: /some/path
                }
            }
        ]
    }
    EOL);

    $this->artisan('repo:get --config ' . $pathToConfig)
        ->expectsOutput('required config key servers.*.clone.using missing.')
        ->assertExitCode(Command::FAILURE);

    Storage::disk('local')->put('tests/temp/config.json', <<<'EOL'
    {
        servers: [
            {
                name: some name
                clone: {
                    to: /some/path
                    using: git@github.com...
                }
            }
        ]
    }
    EOL);

    $this->artisan('repo:get --config ' . $pathToConfig)
        ->expectsOutput('required config key repo-names.fromApi missing.')
        ->assertExitCode(Command::FAILURE);

    Storage::disk('local')->put('tests/temp/config.json', <<<'EOL'
    {
        servers: [
            {
                name: some name
                clone: {
                    to: /some/path
                    using: git@github.com...
                },
                repo-names: {
                    fromApi: someserver.com
                }
            }
        ]
    }
    EOL);

    $this->artisan('repo:get --config ' . $pathToConfig)
        ->expectsOutput('required config key repo-names.pattern missing.')
        ->assertExitCode(Command::FAILURE);

    Storage::disk('local')->put('tests/temp/config.json', <<<'EOL'
    {
        servers: [
            {
                name: some name
                clone: {
                    to: /some/path
                    using: git@github.com...
                },
                repo-names: {
                    fromApi: smoenotfoundserver.notfound
                    pattern: *.name
                }
            }
        ]
    }
    EOL);

    $this->artisan('repo:get --config ' . $pathToConfig)
        ->expectsOutput(PHP_EOL)
        ->expectsOutput('Collecting repository names for: some name')
        ->expectsOutputToContain('cURL error 6: Could not resolve host: smoenotfoundserver.notfound')
        ->assertExitCode(Command::FAILURE);

    Storage::disk('local')->put('tests/temp/config.json', <<<'EOL'
    {
        servers: [
            {
                name: some name
                clone: {
                    to: /some/path
                    using: git@github.com...
                },
                repo-names: {
                    names: repo-name
                }
            }
        ]
    }
    EOL);

    $this->artisan('repo:get --config ' . $pathToConfig)
        ->expectsOutput(PHP_EOL)
        ->expectsOutput('Collecting repository names for: some name')
        ->expectsOutput('1 repository found. Cloning/Fetching...')
        ->expectsOutput(PHP_EOL)
        ->expectsOutput('Directory not found: ' . pathable('/some/path'))
        ->expectsOutput(PHP_EOL)
        ->expectsOutput('Clone/Fetch summary:')
        ->expectsOutput('name: some name')
        ->expectsOutput('found repos count: 1')
        ->expectsOutput('path to repos: ' . pathable('/some/path'))
        ->assertExitCode(Command::SUCCESS);

    Storage::disk('local')->put('tests/temp/config.json', <<<'EOL'
    {
        servers: [
            {
                name: some name
                clone: {
                    to: /some/path
                    using: git@github.com...
                },
                repo-names: {
                    names: [
                        "first", 'second'
                    ]
                }
            }
        ]
    }
    EOL);

    $this->artisan('repo:get --config ' . $pathToConfig)
        ->expectsOutput(PHP_EOL)
        ->expectsOutput('Collecting repository names for: some name')
        ->expectsOutput('2 repository found. Cloning/Fetching...')
        ->expectsOutput(PHP_EOL)
        ->expectsOutput('Directory not found: ' . pathable('/some/path'))
        ->expectsOutput(PHP_EOL)
        ->expectsOutput('Clone/Fetch summary:')
        ->expectsOutput('name: some name')
        ->expectsOutput('found repos count: 2')
        ->expectsOutput('path to repos: ' . pathable('/some/path'))
        ->assertExitCode(Command::SUCCESS);
});

it('skips server when clone.to directory not found', function () {
    $pathToConfig = Storage::disk('local')->path('tests/temp/config.json');

    Storage::disk('local')->put('tests/temp/config.json', <<<'EOL'
    {
        servers: [
            {
                name: some name
                clone: {
                    to: /some/path
                    using: git@github.com...
                },
                repo-names: {
                    names: repo-name
                }
            }
        ]
    }
    EOL);

    $this->artisan('repo:get --config ' . $pathToConfig)
        ->expectsOutput(PHP_EOL)
        ->expectsOutput('Collecting repository names for: some name')
        ->expectsOutput('1 repository found. Cloning/Fetching...')
        ->expectsOutput(PHP_EOL)
        ->expectsOutput('Directory not found: ' . pathable('/some/path'))
        ->expectsOutput(PHP_EOL)
        ->expectsOutput('Clone/Fetch summary:')
        ->expectsOutput('name: some name')
        ->expectsOutput('found repos count: 1')
        ->expectsOutput('path to repos: ' . pathable('/some/path'))
        ->assertExitCode(Command::SUCCESS);
});

it('shows error when no server found', function () {
    $pathToConfig = Storage::disk('local')->path('tests/temp/config.json');

    Storage::disk('local')->put('tests/temp/config.json', <<<'EOL'
    {
        "servers": [
            {
                "name": "some name",
                "clone": {
                    "to": "/some/path",
                    "using": "git@github.com..."
                },
                "repo-names": {
                    "names": "repo-name"
                }
            }
        ]
    }
    EOL);

    $this->artisan('repo:get --matches notfoundserver --config ' . $pathToConfig)
        ->expectsOutput('No server found!')
        ->assertExitCode(Command::FAILURE);
});

it('fails on wrong server token', function () {
    $pathToConfig = Storage::disk('local')->path('tests/temp/config.json');

    Storage::disk('local')->put('tests/temp/config.json', <<<'EOL'
    {
        "servers": [
            {
                "name": "some name",
                "clone": {
                    "to": "/some/path",
                    "using": "git@github.com..."
                },
                "repo-names": {
                    "fromApi": "https://api.github.com/search/repositories?q=user:-username-",
                    "pattern": "items.*.name",
                    "token": "sometoken"
                }
            }
        ]
    }
    EOL);

    $this->artisan('repo:get --config ' . $pathToConfig)
        ->expectsOutput(PHP_EOL)
        ->expectsOutput('Collecting repository names for: some name')
        ->expectsOutput('Request failed with status code: 401. Message: Bad credentials')
        ->assertExitCode(Command::FAILURE);
});

it('filters repos', function () {
    $pathToConfig = Storage::disk('local')->path('tests/temp/config.json');
    $to = base_path(pathable('tests/temp'));

    Storage::disk('local')->put('tests/temp/config.json', <<<EOL
    {
        "servers": [
            {
                "name": "some name",
                "clone": {
                    "to": "{$to}",
                    "using": "git@github.com..."
                },
                "repo-names": {
                    "names": "repo-name"
                }
            }
        ]
    }
    EOL);

    $this->artisan('repo:get --repo-matches notfoundrepo --config ' . $pathToConfig)
        ->expectsOutput('Collecting repository names for: some name')
        ->expectsOutput('0 repository found. Cloning/Fetching...')
        ->expectsOutput('Clone/Fetch summary:')
        ->expectsOutput('name: some name')
        ->expectsOutput('found repos count: 0')
        ->expectsOutput('path to repos: ' . pathable($to))
        ->assertExitCode(Command::SUCCESS);
});

it('gets filtered repo', function () {
    $pathToConfig = Storage::disk('local')->path('tests/temp1/config.json');
    $to = base_path(pathable('tests/temp'));

    Storage::disk('local')->put('tests/temp1/config.json', <<<EOL
    {
        "servers": [
            {
                "name": "some name",
                "clone": {
                    "to": "{$to}",
                    "using": "https://github.com/amirhossein5/<repo>"
                },
                "repo-names": {
                    "fromApi": "https://api.github.com/search/repositories?q=user:amirhossein5",
                    "pattern": "items.*.name"
                }
            }
        ]
    }
    EOL);

    expect(Artisan::call('repo:get --repo-matches full-screen-js-codes --config ' . $pathToConfig))
        ->toBe(Command::SUCCESS);
    expect(count(FileManager::allDir($to)))
        ->toBe(1);
    expect(count(FileManager::allFiles($to)))
        ->toBe(0);

    expect(FileManager::allDir($to)[0])->toContain('full-screen-js-codes.git');
});

it('skips server when no repo found', function () {
    $pathToConfig = Storage::disk('local')->path('tests/temp/config.json');
    $to = base_path(pathable('tests/temp'));

    Storage::disk('local')->put('tests/temp/config.json', <<<EOL
    {
        "servers": [
            {
                "name": "some name",
                "clone": {
                    "to": "{$to}",
                    "using": "git@github.com..."
                },
                "repo-names": {
                    "names": "repo-name"
                }
            },
            {
                "name": "another server",
                "clone": {
                    "to": "{$to}",
                    "using": "git@github.com..."
                },
                "repo-names": {
                    "names": "repo-name"
                }
            }
        ]
    }
    EOL);

    $this->artisan('repo:get --repo-matches notfoundrepo --config ' . $pathToConfig)
        ->expectsOutput('Collecting repository names for: some name')
        ->expectsOutput('0 repository found. Cloning/Fetching...')
        ->expectsOutput('Collecting repository names for: another server')
        ->expectsOutput('0 repository found. Cloning/Fetching...')
        ->expectsOutput('Clone/Fetch summary:')
        ->expectsOutput('name: some name')
        ->expectsOutput('found repos count: 0')
        ->expectsOutput("path to repos: {$to}")
        ->expectsOutput('name: another server')
        ->expectsOutput('found repos count: 0')
        ->expectsOutput("path to repos: {$to}")
        ->assertExitCode(Command::SUCCESS);
});

it('clones repos', function () {
    $pathToConfig = Storage::disk('local')->path('tests/temp1/config.json');
    $to = base_path(pathable('tests/temp'));

    Storage::disk('local')->put('tests/temp1/config.json', <<<EOL
    {
        "servers": [
            {
                "name": "some name",
                "clone": {
                    "to": "{$to}",
                    "using": "https://github.com/amirhossein5/<repo>"
                },
                "repo-names": {
                    "names": [
                        "full-screen-js-codes.git"
                    ]
                }
            }
        ]
    }
    EOL);

    expect(Artisan::call('repo:get --config ' . $pathToConfig))
        ->toBe(Command::SUCCESS);
    expect(count(FileManager::allDir($to)))
        ->toBe(1);
    expect(count(FileManager::allFiles($to)))
        ->toBe(0);

    expect(FileManager::allDir($to)[0])->toContain('full-screen-js-codes.git');

    expect(count(RepositoryManager::getClonedReposTo()))->toBe(1);
    expect(count(RepositoryManager::getFetchedRepos()))->toBe(0);
    expect(RepositoryManager::getClonedReposTo()[0])->toContain($to);

    Storage::disk('local')
        ->deleteDirectory('tests/temp');
    Storage::disk('local')
        ->makeDirectory('tests/temp');

    Storage::disk('local')->put('tests/temp1/config.json', <<<EOL
    {
        "servers": [
            {
                "name": "some name",
                "clone": {
                    "to": "{$to}",
                    "using": "https://github.com:/amirhossein5/<repo>"
                },
                "repo-names": {
                    "names": "full-screen-js-codes.git"
                }
            },
            {
                "name": "another server",
                "clone": {
                    "to": "{$to}",
                    "using": "https://github.com/amirhossein5/<repo>"
                },
                "repo-names": {
                    "names": "laravel-livewire.git"
                }
            }
        ]
    }
    EOL);

    expect(Artisan::call('repo:get --config ' . $pathToConfig))
        ->toBe(Command::SUCCESS);

    expect(count(FileManager::allDir($to)))
        ->toBe(2);
    expect(count(FileManager::allFiles($to)))
        ->toBe(0);

    expect(FileManager::allDir($to)[0])->toContain('full-screen-js-codes.git');
    expect(FileManager::allDir($to)[1])->toContain('laravel-livewire.git');

    expect(count(RepositoryManager::getClonedReposTo()))->toBe(3);
    expect(count(RepositoryManager::getFetchedRepos()))->toBe(0);
    expect(RepositoryManager::getClonedReposTo()[0])->toContain($to);
    expect(RepositoryManager::getClonedReposTo()[1])->toContain($to);
});

it('fetches repos', function () {
    $pathToConfig = Storage::disk('local')->path('tests/temp1/config.json');
    $to = base_path(pathable('tests/temp'));

    Storage::disk('local')->put('tests/temp1/config.json', <<<EOL
    {
        "servers": [
            {
                "name": "some name",
                "clone": {
                    "to": "{$to}",
                    "using": "https://github.com/amirhossein5/<repo>"
                },
                "repo-names": {
                    "names": [
                        "full-screen-js-codes.git"
                    ]
                }
            }
        ]
    }
    EOL);

    expect(Artisan::call('repo:get --config ' . $pathToConfig))
        ->toBe(Command::SUCCESS);

    expect(Artisan::call('repo:get --config ' . $pathToConfig))
        ->toBe(Command::SUCCESS);

    expect(FileManager::allDir($to)[0])->toContain('full-screen-js-codes.git');

    expect(count(RepositoryManager::getClonedReposTo()))->toBe(1);
    expect(count(RepositoryManager::getFetchedRepos()))->toBe(1);
    expect(RepositoryManager::getClonedReposTo()[0])->toContain($to);
    expect(RepositoryManager::getFetchedRepos()[0])->toContain($to . DIRECTORY_SEPARATOR . 'full-screen-js-codes.git');

    Storage::disk('local')
        ->deleteDirectory('tests/temp');
    Storage::disk('local')
        ->makeDirectory('tests/temp');

    RepositoryManager::clearStats();

    Storage::disk('local')->put('tests/temp1/config.json', <<<EOL
    {
        "servers": [
            {
                "name": "some name",
                "clone": {
                    "to": "{$to}",
                    "using": "https://github.com/amirhossein5/<repo>"
                },
                "repo-names": {
                    "names": "full-screen-js-codes.git"
                }
            },
            {
                "name": "another server",
                "clone": {
                    "to": "{$to}",
                    "using": "https://github.com/amirhossein5/<repo>"
                },
                "repo-names": {
                    "names": "laravel-livewire.git"
                }
            }
        ]
    }
    EOL);

    expect(Artisan::call('repo:get --config ' . $pathToConfig))
        ->toBe(Command::SUCCESS);

    expect(Artisan::call('repo:get --config ' . $pathToConfig))
        ->toBe(Command::SUCCESS);

    expect(FileManager::allDir($to)[0])->toContain('full-screen-js-codes.git');
    expect(FileManager::allDir($to)[1])->toContain('laravel-livewire.git');

    expect(count(RepositoryManager::getClonedReposTo()))->toBe(2);
    expect(count(RepositoryManager::getFetchedRepos()))->toBe(2);
    expect(RepositoryManager::getClonedReposTo()[0])->toContain($to);
    expect(RepositoryManager::getClonedReposTo()[1])->toContain($to);
    expect(RepositoryManager::getFetchedRepos()[0])->toContain($to . DIRECTORY_SEPARATOR . 'full-screen-js-codes.git');
    expect(RepositoryManager::getFetchedRepos()[1])->toContain($to . DIRECTORY_SEPARATOR . 'laravel-livewire.git');
});
