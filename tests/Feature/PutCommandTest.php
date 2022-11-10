<?php

use App\Commands\PutCommand;
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
        ->expectsOutput('Directory not found '.pathable('some/not/found/path'))
        ->assertExitCode(Command::FAILURE);
});

it('shows error when direcotry not found', function () {
    $this->artisan('put --disk dropbox --dir=some/not/found/path')
        ->expectsOutput('Directory not found '.pathable('some/not/found/path'))
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
        ->expectsOutput('File not found at: '.pathable('/some/not/found'))
        ->assertExitCode(Command::FAILURE);

    $this->artisan("put --disk local --disk-tokens {$dir} --dir={$dir}")
        ->expectsOutput("expected json found directory: ".pathable($dir))
        ->assertExitCode(Command::FAILURE);
});

it('shows error when could not decode disk tokens json', function () {
    $dir = base_path();
    $diskTokensPath = $this->tempDirPath.DIRECTORY_SEPARATOR.'disk-tokens.json';
    Storage::disk('local')->put('tests/temp/disk-tokens.json', '
    {
        somekey
    }
    ');

    $this->artisan("put --disk local --disk-tokens {$diskTokensPath} --dir={$dir}")
        ->expectsOutput('Error when decoding json:')
        ->expectsOutputToContain("Found '}' where a key name was expected")
        ->assertExitCode(Command::FAILURE);
});

it('sets disk tokens correctly', function () {
    $dir = base_path('stubs');
    $diskTokensPath = $this->tempDirPath.DIRECTORY_SEPARATOR.'disk-tokens.json';
    Storage::disk('local')->put('tests/temp/disk-tokens.json', <<<'EOT'
    {
        notexists: sometoken
        key: somekey
        secret: somesecret
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

it('shows error when dir does not have any file or folder', function () {
    $dirName = uniqid();
    Storage::disk('local')->makeDirectory('tests/temp/'.$dirName);
    $dir = Storage::disk('local')->path('tests/temp/'.$dirName);

    $this->artisan("put --disk local --dir={$dir}")
        ->expectsOutput(PHP_EOL)
        ->expectsOutput('Checking disk...')
        ->expectsOutput("Uploading to disk: local, path: ".pathable($dirName).'/')
        ->expectsOutput(pathable($dir)." Does not have any file or folder.")
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
    expect(FileManager::allDir($dirName)[0])->toBe(pathable("$dirName/some"));
    expect(FileManager::allFiles($dirName)[0])->toBe(pathable("$dirName/file.txt"));

    expect(count(FileManager::allDir("$dirName/some")))->toBe(2);
    expect(count(FileManager::allFiles("$dirName/some")))->toBe(0);
    expect(FileManager::allDir("$dirName/some")[0])->toBe(pathable("$dirName/some/empty"));
    expect(FileManager::allDir("$dirName/some")[1])->toBe(pathable("$dirName/some/not"));

    expect(count(FileManager::allDir("$dirName/some/empty")))->toBe(1);
    expect(count(FileManager::allFiles("$dirName/some/empty")))->toBe(0);
    expect(FileManager::allDir("$dirName/some/empty")[0])->toBe(pathable("$dirName/some/empty/folder"));

    expect(count(FileManager::allDir("$dirName/some/empty/folder")))->toBe(0);
    expect(count(FileManager::allFiles("$dirName/some/empty/folder")))->toBe(0);

    expect(count(FileManager::allDir("$dirName/some/not")))->toBe(1);
    expect(count(FileManager::allFiles("$dirName/some/not")))->toBe(1);
    expect(FileManager::allDir("$dirName/some/not")[0])->toBe(pathable("$dirName/some/not/folder"));
    expect(FileManager::allFiles("$dirName/some/not")[0])->toBe(pathable("$dirName/some/not/file.txt"));

    expect(count(FileManager::allDir("$dirName/some/not/folder")))->toBe(0);
    expect(count(FileManager::allFiles("$dirName/some/not/folder")))->toBe(1);
    expect(FileManager::allFiles("$dirName/some/not/folder")[0])->toBe(pathable("$dirName/some/not/folder/file.txt"));

    expect(file_get_contents(base_path($dirName.'/file.txt')))->toBe('rest');
    expect(file_get_contents(base_path($dirName.'/some/not/folder/file.txt')))->toBe(pathable('some/not/folder/file.txt'));
    expect(file_get_contents(base_path($dirName.'/some/not/file.txt')))->toBe(pathable('some/not/file.txt'));

    expect(Storage::disk('local')->deleteDirectory($dirName))->toBeTrue();
});

it('it will upload empty file', function () {
    $dirName = uniqid();
    Storage::disk('local')->put('tests/temp/'.$dirName.'/file.txt', '');
    $dirPath = Storage::disk('local')->path('tests/temp/'.$dirName);

    Artisan::call("put --disk local --dir={$dirPath}");

    $uploadedPath = base_path($dirName);

    expect(count(FileManager::allDir($uploadedPath)))->toBe(0);
    expect(count(FileManager::allFiles($uploadedPath)))->toBe(1);
    expect(file_get_contents($uploadedPath.'/file.txt'))->toBe(' ');

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
    expect(FileManager::allDir($dirName)[0])->toBe(pathable("$dirName/some"));
    expect(FileManager::allFiles($dirName)[0])->toBe(pathable("$dirName/file.txt"));

    expect(count(FileManager::allDir("$dirName/some")))->toBe(2);
    expect(count(FileManager::allFiles("$dirName/some")))->toBe(0);
    expect(FileManager::allDir("$dirName/some")[0])->toBe(pathable("$dirName/some/empty"));
    expect(FileManager::allDir("$dirName/some")[1])->toBe(pathable("$dirName/some/not"));

    expect(count(FileManager::allDir("$dirName/some/empty")))->toBe(1);
    expect(count(FileManager::allFiles("$dirName/some/empty")))->toBe(0);
    expect(FileManager::allDir("$dirName/some/empty")[0])->toBe(pathable("$dirName/some/empty/folder"));

    expect(count(FileManager::allDir("$dirName/some/empty/folder")))->toBe(0);
    expect(count(FileManager::allFiles("$dirName/some/empty/folder")))->toBe(0);

    expect(count(FileManager::allDir("$dirName/some/not")))->toBe(1);
    expect(count(FileManager::allFiles("$dirName/some/not")))->toBe(1);
    expect(FileManager::allDir("$dirName/some/not")[0])->toBe(pathable("$dirName/some/not/folder"));
    expect(FileManager::allFiles("$dirName/some/not")[0])->toBe(pathable("$dirName/some/not/file.txt"));

    expect(count(FileManager::allDir("$dirName/some/not/folder")))->toBe(0);
    expect(count(FileManager::allFiles("$dirName/some/not/folder")))->toBe(1);
    expect(FileManager::allFiles("$dirName/some/not/folder")[0])->toBe(pathable("$dirName/some/not/folder/file.txt"));

    expect(file_get_contents(base_path($dirName.'/file.txt')))->toBe('rest');
    expect(file_get_contents(base_path($dirName.'/some/not/folder/file.txt')))->toBe(pathable('some/not/folder/file.txt'));
    expect(file_get_contents(base_path($dirName.'/some/not/file.txt')))->toBe(pathable('some/not/file.txt'));

    expect(Storage::disk('local')->deleteDirectory($dirName))->toBeTrue();
});

test('test when not passing disk token', function () {
    $dir = base_path();

    $this->artisan("put --disk dropbox --dir={$dir}")
        ->expectsOutput(PHP_EOL)
        ->expectsOutput('Checking disk...')
        ->expectsOutput('Uploading to disk: dropbox, path: git-backup'.DIRECTORY_SEPARATOR)
        ->expectsOutput(PHP_EOL)
        ->expectsOutput("Counldn't create file in disk path ".pathable("git-backup/.editorconfig").". Check your connection, or set disk authorization tokens.")
        ->assertExitCode(Command::FAILURE);
});

test('test when disk tokens are not valid', function () {
    $dir = base_path();
    $diskTokensPath = $this->tempDirPath.DIRECTORY_SEPARATOR.'disk-tokens.json';
    Storage::disk('local')->put('tests/temp/disk-tokens.json', <<<'EOL'
    {
        key: some key
        token: sometoken
    }
    EOL);

    $this->artisan("put --disk dropbox --disk-tokens {$diskTokensPath} --dir={$dir}")
        ->expectsOutput(PHP_EOL)
        ->expectsOutput('Checking disk...')
        ->expectsOutput('Uploading to disk: dropbox, path: git-backup'.DIRECTORY_SEPARATOR)
        ->expectsOutput(PHP_EOL)
        ->expectsOutput("Counldn't create file in disk path ".pathable("git-backup/.editorconfig").". Check your connection, or set disk authorization tokens.")
        ->assertExitCode(Command::FAILURE);
});

it('when dir already exists, deletes dir', function () {
    $dir = base_path('stubs');

    $this->artisan("put --disk local --dir={$dir} --to-dir tests/temp")
        ->expectsOutput(PHP_EOL)
        ->expectsOutput('Checking disk...')
        ->expectsQuestion('Directory <comment>tests/temp</comment> exists in disk <comment>local</comment>', PutCommand::DELETE_FROM_DISK)
        ->expectsOutput('Deleted tests/temp: ✔')
        ->assertExitCode(Command::SUCCESS);

    expect(Storage::disk('local')->directoryExists('tests/temp'))->not->toBeTrue();
});

it('when dir already exists, uses new dir name', function () {
    $dir = base_path('stubs');

    $this->artisan("put --disk local --dir={$dir} --to-dir tests/temp")
        ->expectsOutput(PHP_EOL)
        ->expectsOutput('Checking disk...')
        ->expectsQuestion('Directory <comment>tests/temp</comment> exists in disk <comment>local</comment>', PutCommand::SELECT_NEW_NAME)
        ->expectsQuestion('Write path(equilvant of --to-dir option)', 'tests/temp1')
        ->expectsOutput(PHP_EOL)
        ->expectsOutput('Checking disk...')
        ->expectsQuestion('Directory <comment>tests/temp1</comment> exists in disk <comment>local</comment>', PutCommand::DELETE_FROM_DISK)
        ->expectsOutput('Deleted tests/temp1: ✔')
        ->assertExitCode(Command::SUCCESS);

    expect(Storage::disk('local')->directoryExists('tests/temp'))->toBeTrue();
    expect(Storage::disk('local')->directoryExists('tests/temp1'))->not->toBeTrue();
});

it('when dir already exists, replaces dir', function () {
    $dir = base_path('will-be-replace');
    Storage::disk('local')->deleteDirectory('tests/temp');
    expect(glob('*.tmp'))->toHaveLength(0);

    Storage::disk('local')->makeDirectory('will-be-replace/some/empty');
    Storage::disk('local')->put('will-be-replace/text.txt', '/');
    Storage::disk('local')->put('will-be-replace/another/text.txt', 'another/');
    Storage::disk('local')->put('will-be-replace/some/text.txt', 'some/');
    Storage::disk('local')->put('will-be-replace/some/dir/text.txt', 'old content');

    expect(Artisan::call("put --disk local --dir={$dir} --to-dir tests/temp"))
        ->toBe(Command::SUCCESS);

    Storage::disk('local')->deleteDirectory('will-be-replace');

    Storage::disk('local')->makeDirectory('will-be-replace/another');
    Storage::disk('local')->put('will-be-replace/some/new.txt', 'new file');
    Storage::disk('local')->put('will-be-replace/some/dir/text.txt', 'new content');

    expect(Artisan::call("put --disk local --dir={$dir} --to-dir tests/temp --replace"))
        ->toBe(Command::SUCCESS);

    expect(FileManager::allDir('tests/temp'))->toHaveLength(2);
    expect(FileManager::allFiles('tests/temp'))->toHaveLength(0);

    expect(FileManager::allDir('tests/temp/another'))->toHaveLength(0);
    expect(FileManager::allFiles('tests/temp/another'))->toHaveLength(0);

    expect(FileManager::allDir('tests/temp/some'))->toHaveLength(1);
    expect(FileManager::allFiles('tests/temp/some'))->toHaveLength(1);

    expect(FileManager::allDir('tests/temp/some/dir'))->toHaveLength(0);
    expect(FileManager::allFiles('tests/temp/some/dir'))->toHaveLength(1);

    expect(Storage::disk('local')->get('tests/temp/some/dir/text.txt'))
        ->toBe('new content');
    expect(Storage::disk('local')->get('tests/temp/some/new.txt'))
        ->toBe('new file');

    expect(Storage::disk('local')->deleteDirectory('will-be-replace'))->toBeTrue();
    expect(glob('*.tmp'))->toHaveLength(0);
});

it('when dir already exists, merges dir', function () {
    $dir = base_path('will-be-merge');
    Storage::disk('local')->deleteDirectory('tests/temp');
    expect(glob('*.tmp'))->toHaveLength(0);

    Storage::disk('local')->makeDirectory('will-be-merge/some/empty');
    Storage::disk('local')->put('will-be-merge/text.txt', '/');
    Storage::disk('local')->put('will-be-merge/some/dir/text.txt', 'old content');

    expect(Artisan::call("put --disk local --dir={$dir} --to-dir tests/temp"))
        ->toBe(Command::SUCCESS);

    Storage::disk('local')->makeDirectory('will-be-merge/another');
    Storage::disk('local')->put('will-be-merge/some/new.txt', 'new file');
    Storage::disk('local')->put('will-be-merge/some/dir/text.txt', 'new content');

    expect(Artisan::call("put --disk local --dir={$dir} --to-dir tests/temp --merge"))
        ->toBe(Command::SUCCESS);

    expect(FileManager::allDir('tests/temp'))->toHaveLength(2);
    expect(FileManager::allFiles('tests/temp'))->toHaveLength(1);

    expect(FileManager::allDir('tests/temp/another'))->toHaveLength(0);
    expect(FileManager::allFiles('tests/temp/another'))->toHaveLength(0);

    expect(FileManager::allDir('tests/temp/some'))->toHaveLength(2);
    expect(FileManager::allFiles('tests/temp/some'))->toHaveLength(1);

    expect(FileManager::allDir('tests/temp/some/dir'))->toHaveLength(0);
    expect(FileManager::allFiles('tests/temp/some/dir'))->toHaveLength(1);

    expect(FileManager::allDir('tests/temp/some/empty'))->toHaveLength(0);
    expect(FileManager::allFiles('tests/temp/some/empty'))->toHaveLength(0);

    expect(Storage::disk('local')->get('tests/temp/text.txt'))
        ->toBe('/');
    expect(Storage::disk('local')->get('tests/temp/some/dir/text.txt'))
        ->toBe('new content');
    expect(Storage::disk('local')->get('tests/temp/some/new.txt'))
        ->toBe('new file');

    expect(Storage::disk('local')->deleteDirectory('will-be-merge'))->toBeTrue();
    expect(glob('*.tmp'))->toHaveLength(0);
});
