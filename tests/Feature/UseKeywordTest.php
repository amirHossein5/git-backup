<?php

use App\Services\ConfigReader;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->expectedFinalOutput = [
        'servers' => [
            [
                'name' => 'some name',
                'clone' => [
                    'to' => 'some path',
                    'using' => 'git c',
                ],
                'repo-names' => [
                    'from-api' => null,
                    'pattern' => null,
                    'names' => 'a repo name',
                    'token' => null,
                ],
            ],
        ],
    ];
});

test('adds keys to array', function () {
    Storage::disk('local')->put('tests/temp/config.json', <<<'EOL'
    {
        servers: [
            {
                name: some name
                clone: {
                    to: some path
                    using: git c
                },
                repo-names: {
                    names: a repo name
                }
            }
        ]
    }
    EOL);

    $usePath = Storage::disk('local')->path('tests/temp/config.json');

    $config = ConfigReader::read(<<<EOL
    {
        "use": "$usePath"
    }
    EOL)->getConfig();

    expect($config)->toMatchArray($this->expectedFinalOutput);

    Storage::disk('local')->put('tests/temp/config.json', <<<'EOL'
    {
        name: some name
        clone: {
            to: some path
            using: git c
        },
        repo-names: {
            names: a repo name
        }
    }
    EOL);

    $config = ConfigReader::read(<<<EOL
    {
        servers: [
            {
                use: {
                    from: $usePath
                }
            }
        ]
    }
    EOL)->getConfig();

    expect($config)->toMatchArray($this->expectedFinalOutput);
});

test('adds keys to array, without rewriting previous ones', function () {
    $usePath = Storage::disk('local')->path('tests/temp/config.json');

    Storage::disk('local')->put('tests/temp/config.json', <<<'EOL'
    {
        name: new name
        clone: {
            to: new path
            using: git c
        }
    }
    EOL);

    $config = ConfigReader::read(<<<EOL
    {
        servers: [
            {
                use: $usePath
                name: some name
                clone: {
                    to: some path
                },
                repo-names: {
                    names: a repo name
                }
            }
        ]
    }
    EOL)->getConfig();

    expect($config)->toMatchArray($this->expectedFinalOutput);
});

test('adds keys to array, with vars', function () {
    $usePath = Storage::disk('local')->path('tests/temp/config.json');

    Storage::disk('local')->put('tests/temp/config.json', <<<'EOL'
    {
        name: -serverName-
        clone: {
            to: -clone.to-
            using: -clone.using-
        }
    }
    EOL);

    $config = ConfigReader::read(<<<EOL
    {
        servers: [
            {
                use: {
                    from: $usePath
                    with: {
                        serverName: some name
                        clone.to: some path
                        clone.using: git c
                    }
                },
                repo-names: {
                    names: a repo name
                }
            }
        ]
    }
    EOL)->getConfig();

    expect($config)->toMatchArray($this->expectedFinalOutput);
});
