<?php

namespace Tests;

use App\Services\FileManager;
use App\Services\JsonDecoder;
use App\Services\RepositoryManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use LaravelZero\Framework\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected string $tempDirPath;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.disks' => [
            ...config('filesystems.disks'),
            'local' => [
                'driver' => 'local',
                'root' => base_path(),
            ],
        ]]);

        $this->tempDirPath = pathable(base_path('tests/temp'));

        Storage::disk('local')
            ->makeDirectory('tests/temp');
        Storage::disk('local')
            ->makeDirectory('tests/temp1');

        RepositoryManager::clearStats();
    }

    protected function tearDown(): void
    {
        Storage::disk('local')
            ->deleteDirectory('tests/temp');
        Storage::disk('local')
            ->deleteDirectory('tests/temp1');

        parent::tearDown();
    }

    protected function gistFakeList($hasComments = false): array
    {
        $fakeList = [
            'https://api.github.com/users/amirHossein5/gists?per_page=50&page=1*' => Http::response(JsonDecoder::decodePath(__DIR__ . '/FakeResponses/amirHossein5-gists.json')),
            'https://api.github.com/users/amirHossein5/gists?per_page=50&page=*' => Http::response([]),
            'https://gist.githubusercontent.com/amirHossein5/7e7516537cb090305d1cfc8a2034fc0c/raw/10ed03d7abf309ef343c6add905d5040f76158a1/config.php' => Http::response('config file of purifier'),
        ];

        if ($hasComments) {
            $fakeList["https://api.github.com/gists/7e7516537cb090305d1cfc8a2034fc0c/comments?page=1*"] = Http::response(JsonDecoder::decodePath(__DIR__ . '/FakeResponses/gist-comments.json'));
        }

        $fakeList['https://api.github.com/gists/7e7516537cb090305d1cfc8a2034fc0c/comments*'] = Http::response('');

        return $fakeList;
    }

    protected function mkGitDirectory(string $dirPath): void
    {
        if (is_dir($dirPath)) {
            rmdir($dirPath);
        }

        mkdir($dirPath);

        exec("cd {$dirPath}; git init 2>&1");
    }

    protected function checkUserDownloadedGists(string $userDirPath, array $gists): void
    {
        $userDirPath = pathable($userDirPath);
        expect(is_dir($userDirPath))->toBeTrue();

        foreach ($gists as $gist) {
            $gistDirName = str($gist['description'] . '-' . $gist['id'])->slug();
            $gistPath = pathable("$userDirPath/$gistDirName");

            expect(FileManager::allDir($gistPath))->toHaveCount(0);
            expect(FileManager::allFiles($gistPath))->toHaveCount(count($gist['files']));

            foreach ($gist['files'] as $filename => $content) {
                $filePath = pathable("$userDirPath/$gistDirName/$filename");

                expect(sha1($content))->toBe(sha1(file_get_contents($filePath)));
            }
        }
    }

    protected function getServersJson($servers): string
    {
        $servers = ['servers' => [Arr::undot($servers)]];

        return json_encode($servers);
    }

    protected function getLongFileName(bool $overlapTerminalWidth = true, int $charLenght = 0): string
    {
        $generatedName = '';
        if ($overlapTerminalWidth) {
            $terminalWidth = (new \Termwind\Terminal())->width();

            for ($i=0; $i < $terminalWidth + 3; $i++) {
                $generatedName .= rand(0, 9);
            }

            return $generatedName;
        }

        for ($i=0; $i < $charLenght; $i++) {
            $generatedName .= rand(0, 9);
        }

        return $generatedName;
    }
}
