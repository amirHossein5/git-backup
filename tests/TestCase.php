<?php

namespace Tests;

use App\Services\RepositoryManager;
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
}
