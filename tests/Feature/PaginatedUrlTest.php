<?php

use App\Services\ConfigReader;
use Illuminate\Support\Facades\Http;

test('generates url from repo-names.fromApi key', function () {
    $serversJson = $this->getServersJson([
        'name' => 'some name for server',
        'clone.to' => 'somewhere',
        'clone.using' => 'git clone',
        'repo-names.fromApi' => 'someurl.com',
        'repo-names.pattern' => 'items.*.name'
    ]);

    $config = ConfigReader::read($serversJson);
    $urls = $config->servers()[0]['repo-names']['fromApi']['urls'];

    expect($config)->toHaveLength(1);
    expect($urls)->toHaveLength(1);
    expect($urls[0])->toBe('someurl.com');
});

test('generates url from repo-names.url key', function () {
    $serversJson = $this->getServersJson([
        'name' => 'some name for server',
        'clone.to' => 'somewhere',
        'clone.using' => 'git clone',
        'repo-names.fromApi.url' => 'someurl.com',
        'repo-names.pattern' => 'items.*.name'
    ]);

    $config = ConfigReader::read($serversJson);
    $urls = $config->servers()[0]['repo-names']['fromApi']['urls'];

    expect($config)->toHaveLength(1);
    expect($urls)->toHaveLength(1);
    expect($urls[0])->toBe('someurl.com');
});

test('generates url from repo-names.urls key', function () {
    $serversJson = $this->getServersJson([
        'name' => 'some name for server',
        'clone.to' => 'somewhere',
        'clone.using' => 'git clone',
        'repo-names.fromApi.urls.0' => 'someurl.com',
        'repo-names.pattern' => 'items.*.name'
    ]);

    $config = ConfigReader::read($serversJson);
    $urls = $config->servers()[0]['repo-names']['fromApi']['urls'];

    expect($config)->toHaveLength(1);
    expect($urls)->toHaveLength(1);
    expect($urls[0])->toBe('someurl.com');
});

test('generates paginated urls', function () {
    $serversJson = $this->getServersJson([
        'name' => 'some name for server',
        'clone.to' => 'somewhere',
        'clone.using' => 'git clone',
        'repo-names.fromApi.url' => 'someurl.com',
        'repo-names.fromApi.withPagination' => true,
        'repo-names.fromApi.total' => 10,
        'repo-names.fromApi.perPage' => 3,
        'repo-names.pattern' => 'items.*.name'
    ]);

    $config = ConfigReader::read($serversJson);
    $urls = $config->servers()[0]['repo-names']['fromApi']['urls'];

    expect($config)->toHaveLength(1);
    expect($urls)->toHaveLength(4);

    for ($i=0; $i <= 3; $i++) {
        expect($urls[$i])->toBe("someurl.com?page=".$i+1);
    }

    $serversJson = $this->getServersJson([
        'name' => 'some name for server',
        'clone.to' => 'somewhere',
        'clone.using' => 'git clone',
        'repo-names.fromApi.url' => 'someurl.com?per_page=2&another',
        'repo-names.fromApi.withPagination' => true,
        'repo-names.fromApi.total' => 10,
        'repo-names.fromApi.perPage' => 3,
        'repo-names.pattern' => 'items.*.name'
    ]);

    $config = ConfigReader::read($serversJson);
    $urls = $config->servers()[0]['repo-names']['fromApi']['urls'];

    expect($config)->toHaveLength(1);
    expect($urls)->toHaveLength(4);

    for ($i=0; $i <= 3; $i++) {
        expect($urls[$i])->toBe("someurl.com?per_page=2&another&page=".$i+1);
    }
});

test('query string is changeable', function () {
    $serversJson = $this->getServersJson([
        'name' => 'some name for server',
        'clone.to' => 'somewhere',
        'clone.using' => 'git clone',
        'repo-names.fromApi.url' => 'someurl.com',
        'repo-names.fromApi.withPagination' => true,
        'repo-names.fromApi.total' => 10,
        'repo-names.fromApi.perPage' => 3,
        'repo-names.fromApi.pageQueryString' => 'key',
        'repo-names.pattern' => 'items.*.name'
    ]);

    $config = ConfigReader::read($serversJson);
    $urls = $config->servers()[0]['repo-names']['fromApi']['urls'];

    expect($config)->toHaveLength(1);
    expect($urls)->toHaveLength(4);

    for ($i=0; $i <= 3; $i++) {
        expect($urls[$i])->toBe("someurl.com?key=".$i+1);
    }

    $serversJson = $this->getServersJson([
        'name' => 'some name for server',
        'clone.to' => 'somewhere',
        'clone.using' => 'git clone',
        'repo-names.fromApi.url' => 'someurl.com?per_page=2&another',
        'repo-names.fromApi.withPagination' => true,
        'repo-names.fromApi.total' => 10,
        'repo-names.fromApi.perPage' => 3,
        'repo-names.fromApi.pageQueryString' => 'key',
        'repo-names.pattern' => 'items.*.name'
    ]);

    $config = ConfigReader::read($serversJson);
    $urls = $config->servers()[0]['repo-names']['fromApi']['urls'];

    expect($config)->toHaveLength(1);
    expect($urls)->toHaveLength(4);

    for ($i=0; $i <= 3; $i++) {
        expect($urls[$i])->toBe("someurl.com?per_page=2&another&key=".$i+1);
    }
});

test('getting total items from api', function () {
    Http::fake([
        'https://api.github.com/search/repositories?q=user:someusername' => Http::response([
            'some key' => 'some value',
            'total_count' => 10
        ])
    ]);

    $serversJson = $this->getServersJson([
        'name' => 'some name for server',
        'clone.to' => 'somewhere',
        'clone.using' => 'git clone',
        'repo-names.fromApi.url' => 'someurl.com',
        'repo-names.fromApi.withPagination' => true,
        'repo-names.fromApi.total' => "https://api.github.com/search/repositories?q=user:someusername",
        'repo-names.fromApi.totalKey' => "total_count",
        'repo-names.fromApi.perPage' => 3,
        'repo-names.pattern' => 'items.*.name'
    ]);

    $config = ConfigReader::read($serversJson);
    $urls = $config->servers()[0]['repo-names']['fromApi']['urls'];

    expect($config)->toHaveLength(1);
    expect($urls)->toHaveLength(4);

    for ($i=0; $i <= 3; $i++) {
        expect($urls[$i])->toBe("someurl.com?page=".$i+1);
    }
});
