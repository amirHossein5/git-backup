<?php

use App\Services\FileManager;
use App\Services\GistService;
use App\Services\JsonDecoder;
use App\Services\RepositoryManager;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Console\Command\Command;

it('has required options', function () {
    $this->artisan('gist:get')
        ->expectsOutput('Option --config is required.')
        ->assertExitCode(Command::FAILURE);

    $this->artisan('gist:get --config some/not/found')
        ->expectsOutput('Option --to-dir is required.')
        ->assertExitCode(Command::FAILURE);

    $this->artisan('gist:get --config some/not/found --to-dir app/')
        ->expectsOutput("Config file not found: some/not/found")
        ->assertExitCode(Command::FAILURE);
});

it('shows error if cannot decode config or required config key missing', function () {
    Storage::disk('local')->put('tests/temp/config.json', <<<'EOL'
    {
        // some valid hjson config
    }
    EOL);

    $pathToConfig = Storage::disk('local')->path('tests/temp/config.json');

    $this->artisan('gist:get --to-dir app/ --config ' . $pathToConfig)
        ->expectsOutput('no `username` key found in config.')
        ->assertExitCode(Command::FAILURE);

    Storage::disk('local')->put('tests/temp/config.json', <<<'EOL'
    {
        invalid
    }
    EOL);

    $this->artisan('gist:get --to-dir app/ --config ' . $pathToConfig)
        ->expectsOutputToContain("'}' where a key name was expected")
        ->assertExitCode(Command::FAILURE);
});

it('shows error when to-dir not found or it is not a directory', function () {
    $this->artisan('gist:get --config backup --to-dir not/found')
        ->expectsOutput("Directory not found: not/found")
        ->assertExitCode(Command::FAILURE);
    $this->artisan('gist:get --config backup --to-dir LICENCE')
        ->expectsOutput("Directory not found: LICENCE")
        ->assertExitCode(Command::FAILURE);
});

it('fails on wrong gist token', function () {
    Http::fake(function (Request $request) {
        if ($request->headers()['Authorization'][0] === 'Bearer sometoken') {
            return Http::response([
                'message' => 'Bad credentials',
                'documentation_url' => "https://docs.github.com/rest"
            ], 401);
        }
    });
    $pathToConfig = Storage::disk('local')->path('tests/temp/config.json');

    Storage::disk('local')->put('tests/temp/config.json', <<<'EOL'
    {
        username: amirHossein5
        token: sometoken
    }
    EOL);

    $this->artisan('gist:get --to-dir tests/temp --config ' . $pathToConfig)
        ->expectsOutput('Getting gists...')
        ->expectsOutputToContain('HTTP request returned status code 401:')
        ->assertExitCode(Command::FAILURE);
});

it('gets filtered gists and checks files for updates', function () {
    Http::fake($this->gistFakeList());

    $pathToConfig = Storage::disk('local')->path('tests/temp/config.json');
    $toDir = pathable(base_path('tests/temp1'));
    Storage::disk('local')->deleteDirectory('tests/temp1');
    Storage::disk('local')->makeDirectory('tests/temp1');

    Storage::disk('local')->put('tests/temp/config.json', <<<'EOL'
    {
        username: amirHossein5
    }
    EOL);

    $this->artisan("gist:get --to-dir $toDir --config $pathToConfig --desc-matches purifi")
        ->expectsOutput('Getting gists...')
        ->expectsOutput(PHP_EOL)
        ->expectsOutput('Processing purifier')
        ->expectsOutput('Creating file config.php')
        ->expectsOutput(PHP_EOL)
        ->expectsOutput('Total proceeded gists: 1')
        ->assertExitCode(Command::SUCCESS);

    expect(FileManager::allDir($toDir))->toHaveCount(1);
    expect(FileManager::allFiles($toDir))->toHaveCount(0);
    $this->checkUserDownloadedGists("$toDir/amirHossein5_gists", [
        [
            'id' => '7e7516537cb090305d1cfc8a2034fc0c',
            'description' => 'purifier',
            'files' => [
                'config.php' => 'config file of purifier'
            ]
        ]
    ]);

    // check for update
    $this->artisan("gist:get --to-dir $toDir --config $pathToConfig --desc-matches purifi")
        ->expectsOutput('Getting gists...')
        ->expectsOutput(PHP_EOL)
        ->expectsOutput('Processing purifier')
        ->expectsOutput('Checked file config.php')
        ->expectsOutput(PHP_EOL)
        ->expectsOutput('Total proceeded gists: 1')
        ->assertExitCode(Command::SUCCESS);

    expect(FileManager::allDir($toDir))->toHaveCount(1);
    expect(FileManager::allFiles($toDir))->toHaveCount(0);
    $this->checkUserDownloadedGists("$toDir/amirHossein5_gists", [
        [
            'id' => '7e7516537cb090305d1cfc8a2034fc0c',
            'description' => 'purifier',
            'files' => [
                'config.php' => 'config file of purifier'
            ]
        ]
    ]);
});

it('updates filtered gist', function () {
    Http::fake($this->gistFakeList());

    $pathToConfig = Storage::disk('local')->path('tests/temp/config.json');
    $toDir = pathable(base_path('tests/temp1'));
    Storage::disk('local')->deleteDirectory('tests/temp1');
    Storage::disk('local')->put('tests/temp1/amirHossein5_gists/purifier-7e7516537cb090305d1cfc8a2034fc0c/config.php', 'some old content');

    Storage::disk('local')->put('tests/temp/config.json', <<<'EOL'
    {
        username: amirHossein5
    }
    EOL);

    $this->artisan("gist:get --to-dir $toDir --config $pathToConfig --desc-matches purifi")
        ->expectsOutput('Getting gists...')
        ->expectsOutput(PHP_EOL)
        ->expectsOutput('Processing purifier')
        ->expectsOutput('Updating file config.php')
        ->expectsOutput(PHP_EOL)
        ->expectsOutput('Total proceeded gists: 1')
        ->assertExitCode(Command::SUCCESS);

    expect(FileManager::allDir($toDir))->toHaveCount(1);
    expect(FileManager::allFiles($toDir))->toHaveCount(0);
    $this->checkUserDownloadedGists("$toDir/amirHossein5_gists", [
        [
            'id' => '7e7516537cb090305d1cfc8a2034fc0c',
            'description' => 'purifier',
            'files' => [
                'config.php' => 'config file of purifier'
            ]
        ]
    ]);
});

it('gets gist comments', function () {
    Http::fake($this->gistFakeList(hasComments: true));

    $pathToConfig = Storage::disk('local')->path('tests/temp/config.json');
    $toDir = pathable(base_path('tests/temp1'));
    Storage::disk('local')->deleteDirectory('tests/temp1');
    Storage::disk('local')->makeDirectory('tests/temp1');

    Storage::disk('local')->put('tests/temp/config.json', <<<'EOL'
    {
        username: amirHossein5
    }
    EOL);

    $this->artisan("gist:get --to-dir $toDir --config $pathToConfig --desc-matches purifi")
        ->expectsOutput('Getting gists...')
        ->expectsOutput(PHP_EOL)
        ->expectsOutput('Processing purifier')
        ->expectsOutput('Creating file config.php')
        ->expectsOutput('Creating file comments.txt')
        ->expectsOutput(PHP_EOL)
        ->expectsOutput('Total proceeded gists: 1')
        ->assertExitCode(Command::SUCCESS);

    $responseComments = Http::retry(3, 100)->get("https://api.github.com/gists/7e7516537cb090305d1cfc8a2034fc0c/comments?page=1")->json();
    $commentsTxtContent = '';

    foreach ($responseComments as $comment) {
        $author = $comment['user']['login'];
        $body = $comment['body'];
        $createdAt = $comment['created_at'];
        $updatedAt = $comment['updated_at'];

        $title = "created_at: [{$createdAt}] updated_at: [{$updatedAt}] author: {$author}";
        $titleSeparator = GistService::createTitleSeparator(len: strlen($title));

        $commentsTxtContent .= $titleSeparator.PHP_EOL;
        $commentsTxtContent .= $title . PHP_EOL;
        $commentsTxtContent .= $titleSeparator.PHP_EOL;
        $commentsTxtContent .= $body.PHP_EOL;
    }
    $commentsTxtContent = str($commentsTxtContent)->trim(PHP_EOL)->toString();

    expect(file_get_contents(
        pathable('tests/temp1/amirHossein5_gists/'.str('purifier-7e7516537cb090305d1cfc8a2034fc0c')->slug().'/comments.txt')
    ))->toContain($commentsTxtContent);
});
