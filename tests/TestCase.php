<?php

namespace Tests;

use App\Services\RepositoryManager;
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

    public function mkGitDirectory(string $dirPath): void
    {
        if (is_dir($dirPath)) {
            rmdir($dirPath);
        }

        mkdir($dirPath);

        exec("cd {$dirPath}; git init 2>&1");
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
