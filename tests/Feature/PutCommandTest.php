<?php

use App\Services\FileManager;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command;

it('has required options', function () {
    $this->artisan('put')
        ->expectsOutput('Option --disk is required.')
        ->assertExitCode(Command::FAILURE);

    $this->artisan('put --disk dropbox')
        ->expectsOutput('Option --dir is required.')
        ->assertExitCode(Command::FAILURE);

    $this->artisan('put --disk dropbox --dir=some/not/found/path')
        ->expectsOutput('Directory not found some/not/found/path')
        ->assertExitCode(Command::FAILURE);
});

it('shows error when direcotry not found', function () {
    $this->artisan('put --disk dropbox --dir=some/not/found/path')
        ->expectsOutput('Directory not found some/not/found/path')
        ->assertExitCode(Command::FAILURE);
});

it('shows error when disk not found', function () {
    $dir = base_path();

    $this->artisan('put --disk notfound --dir='.$dir)
        ->expectsOutput('disk notfound not found.')
        ->expectsOutput('See available disk list via, php backup show:disk')
        ->assertExitCode(Command::FAILURE);
});

it('shows error when passed disk tokens not exists or is dir', function () {
    $dir = base_path();

    $this->artisan("put --disk local --disk-tokens /some/not/found --dir={$dir}")
        ->expectsOutput('File not found at: /some/not/found')
        ->assertExitCode(Command::FAILURE);

    $this->artisan("put --disk local --disk-tokens {$dir} --dir={$dir}")
        ->expectsOutput("Disk tokens should be json file not directory: {$dir}")
        ->assertExitCode(Command::FAILURE);
});

it('shows error when could not decode disk tokens json', function () {
    $dir = base_path();
    $diskTokensPath = $this->tempDirPath.DIRECTORY_SEPARATOR.'disk-tokens.json';
    Storage::disk('local')->put('tests/temp/disk-tokens.json', '
    {
        // invalid json
    }
    ');

    $this->artisan("put --disk local --disk-tokens {$diskTokensPath} --dir={$dir}")
        ->expectsOutput('Error when decoding json:')
        ->expectsOutput('Syntax error')
        ->assertExitCode(Command::FAILURE);
});

it('sets disk tokens correctly', function () {
    $dir = base_path('stubs');
    $diskTokensPath = $this->tempDirPath.DIRECTORY_SEPARATOR.'disk-tokens.json';
    Storage::disk('local')->put('tests/temp/disk-tokens.json', <<<'EOT'
    {
        "notexists": "sometoken",
        "key": "somekey",
        "secret": "somesecret"
    }
    EOT);

    config(['filesystems.disks.local' => [
        ...config('filesystems.disks.local'),
        'key' => null,
        'secret' => null,
    ]]);

    expect(Artisan::call("put --disk local --disk-tokens {$diskTokensPath} --dir={$dir} --to-dir tests/temp/stubs"))
        ->toBe(Command::SUCCESS);
    $configArray = config('filesystems.disks.local');

    unset($configArray['root']);
    unset($configArray['driver']);

    expect(count($configArray))->toBe(2);
    expect(isset($configArray['key']))->toBeTrue();
    expect(isset($configArray['secret']))->toBeTrue();
});

it('shows confirmation to delete dir if already exists', function () {
    $dir = base_path('stubs');

    $this->artisan("put --disk local --dir={$dir} --to-dir tests/temp")
        ->expectsOutput(PHP_EOL)
        ->expectsOutput('Checking disk...')
        ->expectsOutput('Directory tests/temp exists in disk: local')
        ->expectsConfirmation('Do you want to delete tests/temp ?', 'no')
        ->assertExitCode(Command::FAILURE);

    expect(Storage::disk('local')->directoryExists('tests/temp'))->toBeTrue();
});

it('deletes dir if confirmation for file already exists passed', function () {
    $dir = base_path('stubs');

    $this->artisan("put --disk local --dir={$dir} --to-dir tests/temp")
        ->expectsOutput(PHP_EOL)
        ->expectsOutput('Checking disk...')
        ->expectsOutput('Directory tests/temp exists in disk: local')
        ->expectsConfirmation('Do you want to delete tests/temp ?', 'yes')
        ->expectsOutput('Deleted tests/temp: ✔')
        ->expectsOutput('run command again.')
        ->assertExitCode(Command::SUCCESS);

    expect(Storage::disk('local')->directoryExists('tests/temp'))->not->toBeTrue();
});

it('shows error when dir does not have any file or folder', function () {
    $dirName = uniqid();
    Storage::disk('local')->makeDirectory('tests/temp/'.$dirName);
    $dir = Storage::disk('local')->path('tests/temp/'.$dirName);

    $this->artisan("put --disk local --dir={$dir}")
        ->expectsOutput(PHP_EOL)
        ->expectsOutput('Checking disk...')
        ->expectsOutput("Uploading to disk: local, path: {$dirName}/")
        ->expectsOutput(PHP_EOL)
        ->expectsOutput("$dir Does not have any file or folder.")
        ->assertExitCode(Command::FAILURE);
});

it('it will upload everything successfully', function () {
    $dirName = uniqid();
    Storage::disk('local')->makeDirectory('tests/temp/'.$dirName);
    Storage::disk('local')->makeDirectory('tests/temp/'.$dirName.'/some/empty/folder');
    Storage::disk('local')->makeDirectory('tests/temp/'.$dirName.'/some/not/folder');
    $dir = Storage::disk('local')->path('tests/temp/'.$dirName);

    Storage::disk('local')
        ->put("tests/temp/{$dirName}/some/not/file.txt", 'some/not/file.txt');
    Storage::disk('local')
        ->put("tests/temp/{$dirName}/some/not/folder/file.txt", 'some/not/folder/file.txt');
    Storage::disk('local')
        ->put("tests/temp/{$dirName}/file.txt", 'rest');

    Artisan::call("put --disk local --dir={$dir}");

    expect(count(FileManager::allDir($dirName)))->toBe(1);
    expect(count(FileManager::allFiles($dirName)))->toBe(1);
    expect(FileManager::allDir($dirName)[0])->toBe("$dirName/some");
    expect(FileManager::allFiles($dirName)[0])->toBe("$dirName/file.txt");

    expect(count(FileManager::allDir("$dirName/some")))->toBe(2);
    expect(count(FileManager::allFiles("$dirName/some")))->toBe(0);
    expect(FileManager::allDir("$dirName/some")[0])->toBe("$dirName/some/empty");
    expect(FileManager::allDir("$dirName/some")[1])->toBe("$dirName/some/not");

    expect(count(FileManager::allDir("$dirName/some/empty")))->toBe(1);
    expect(count(FileManager::allFiles("$dirName/some/empty")))->toBe(0);
    expect(FileManager::allDir("$dirName/some/empty")[0])->toBe("$dirName/some/empty/folder");

    expect(count(FileManager::allDir("$dirName/some/empty/folder")))->toBe(0);
    expect(count(FileManager::allFiles("$dirName/some/empty/folder")))->toBe(0);

    expect(count(FileManager::allDir("$dirName/some/not")))->toBe(1);
    expect(count(FileManager::allFiles("$dirName/some/not")))->toBe(1);
    expect(FileManager::allDir("$dirName/some/not")[0])->toBe("$dirName/some/not/folder");
    expect(FileManager::allFiles("$dirName/some/not")[0])->toBe("$dirName/some/not/file.txt");

    expect(count(FileManager::allDir("$dirName/some/not/folder")))->toBe(0);
    expect(count(FileManager::allFiles("$dirName/some/not/folder")))->toBe(1);
    expect(FileManager::allFiles("$dirName/some/not/folder")[0])->toBe("$dirName/some/not/folder/file.txt");

    expect(file_get_contents(base_path($dirName.'/file.txt')))->toBe('rest');
    expect(file_get_contents(base_path($dirName.'/some/not/folder/file.txt')))->toBe('some/not/folder/file.txt');
    expect(file_get_contents(base_path($dirName.'/some/not/file.txt')))->toBe('some/not/file.txt');

    expect(Storage::disk('local')->deleteDirectory($dirName))->toBeTrue();
});

it('it will upload everything successfully on specified folder', function () {
    $dirName = uniqid();
    Storage::disk('local')->makeDirectory('tests/temp/'.$dirName);
    Storage::disk('local')->makeDirectory('tests/temp/'.$dirName.'/some/empty/folder');
    Storage::disk('local')->makeDirectory('tests/temp/'.$dirName.'/some/not/folder');
    $dir = Storage::disk('local')->path('tests/temp/'.$dirName);
    $toDir = 'somenotprevoiuslycreatedfolder';

    Storage::disk('local')->deleteDirectory($toDir);

    Storage::disk('local')
        ->put("tests/temp/{$dirName}/some/not/file.txt", 'some/not/file.txt');
    Storage::disk('local')
        ->put("tests/temp/{$dirName}/some/not/folder/file.txt", 'some/not/folder/file.txt');
    Storage::disk('local')
        ->put("tests/temp/{$dirName}/file.txt", 'rest');

    Artisan::call("put --disk local --dir={$dir} --to-dir={$toDir}");

    $dirName = $toDir;

    expect(count(FileManager::allDir($dirName)))->toBe(1);
    expect(count(FileManager::allFiles($dirName)))->toBe(1);
    expect(FileManager::allDir($dirName)[0])->toBe("$dirName/some");
    expect(FileManager::allFiles($dirName)[0])->toBe("$dirName/file.txt");

    expect(count(FileManager::allDir("$dirName/some")))->toBe(2);
    expect(count(FileManager::allFiles("$dirName/some")))->toBe(0);
    expect(FileManager::allDir("$dirName/some")[0])->toBe("$dirName/some/empty");
    expect(FileManager::allDir("$dirName/some")[1])->toBe("$dirName/some/not");

    expect(count(FileManager::allDir("$dirName/some/empty")))->toBe(1);
    expect(count(FileManager::allFiles("$dirName/some/empty")))->toBe(0);
    expect(FileManager::allDir("$dirName/some/empty")[0])->toBe("$dirName/some/empty/folder");

    expect(count(FileManager::allDir("$dirName/some/empty/folder")))->toBe(0);
    expect(count(FileManager::allFiles("$dirName/some/empty/folder")))->toBe(0);

    expect(count(FileManager::allDir("$dirName/some/not")))->toBe(1);
    expect(count(FileManager::allFiles("$dirName/some/not")))->toBe(1);
    expect(FileManager::allDir("$dirName/some/not")[0])->toBe("$dirName/some/not/folder");
    expect(FileManager::allFiles("$dirName/some/not")[0])->toBe("$dirName/some/not/file.txt");

    expect(count(FileManager::allDir("$dirName/some/not/folder")))->toBe(0);
    expect(count(FileManager::allFiles("$dirName/some/not/folder")))->toBe(1);
    expect(FileManager::allFiles("$dirName/some/not/folder")[0])->toBe("$dirName/some/not/folder/file.txt");

    expect(file_get_contents(base_path($dirName.'/file.txt')))->toBe('rest');
    expect(file_get_contents(base_path($dirName.'/some/not/folder/file.txt')))->toBe('some/not/folder/file.txt');
    expect(file_get_contents(base_path($dirName.'/some/not/file.txt')))->toBe('some/not/file.txt');

    expect(Storage::disk('local')->deleteDirectory($dirName))->toBeTrue();
});

test('test when not passing disk token', function () {
    $dir = base_path();

    $this->artisan("put --disk dropbox --dir={$dir}")
        ->expectsOutput(PHP_EOL)
        ->expectsOutput('Checking disk...')
        ->expectsOutput('Uploading to disk: dropbox, path: git-backup/')
        ->expectsOutput(PHP_EOL)
        ->expectsOutput("Counldn't create directory in disk path git-backup/.editorconfig. Check your connection, or set disk authorization tokens.")
        ->assertExitCode(Command::FAILURE);
});

test('test when disk tokens are not valid', function () {
    $dir = base_path();
    $diskTokensPath = $this->tempDirPath.DIRECTORY_SEPARATOR.'disk-tokens.json';
    Storage::disk('local')->put('tests/temp/disk-tokens.json', <<<'EOL'
    {
        "key": "some key",
        "token": "sometoken"
    }
    EOL);

    $this->artisan("put --disk dropbox --disk-tokens {$diskTokensPath} --dir={$dir}")
        ->expectsOutput(PHP_EOL)
        ->expectsOutput('Checking disk...')
        ->expectsOutput('Uploading to disk: dropbox, path: git-backup/')
        ->expectsOutput(PHP_EOL)
        ->expectsOutput("Counldn't create directory in disk path git-backup/.editorconfig. Check your connection, or set disk authorization tokens.")
        ->assertExitCode(Command::FAILURE);
});
