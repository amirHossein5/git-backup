<?php

use App\Services\ConfigReader;
use Illuminate\Support\Facades\Http;

test('generates url from repoNames.fromApi key', function () {
    $serversJson = $this->getServersJson([
        'name' => 'some name for server',
        'clone.to' => 'somewhere',
        'clone.using' => 'git clone',
        'repoNames.fromApi' => 'someurl.com',
        'repoNames.pattern' => 'items.*.name'
    ]);

    $config = ConfigReader::read($serversJson);
    $urls = $config->servers()[0]['repoNames']['fromApi']['urls'];

    expect($config)->toHaveLength(1);
    expect($urls)->toHaveLength(1);
    expect($urls[0])->toBe('someurl.com');
});

test('generates url from repoNames.url key', function () {
    $serversJson = $this->getServersJson([
        'name' => 'some name for server',
        'clone.to' => 'somewhere',
        'clone.using' => 'git clone',
        'repoNames.fromApi.url' => 'someurl.com',
        'repoNames.pattern' => 'items.*.name'
    ]);

    $config = ConfigReader::read($serversJson);
    $urls = $config->servers()[0]['repoNames']['fromApi']['urls'];

    expect($config)->toHaveLength(1);
    expect($urls)->toHaveLength(1);
    expect($urls[0])->toBe('someurl.com');
});

test('generates url from repoNames.urls key', function () {
    $serversJson = $this->getServersJson([
        'name' => 'some name for server',
        'clone.to' => 'somewhere',
        'clone.using' => 'git clone',
        'repoNames.fromApi.urls.0' => 'someurl.com',
        'repoNames.pattern' => 'items.*.name'
    ]);

    $config = ConfigReader::read($serversJson);
    $urls = $config->servers()[0]['repoNames']['fromApi']['urls'];

    expect($config)->toHaveLength(1);
    expect($urls)->toHaveLength(1);
    expect($urls[0])->toBe('someurl.com');
});

test('generates paginated urls', function () {
    $serversJson = $this->getServersJson([
        'name' => 'some name for server',
        'clone.to' => 'somewhere',
        'clone.using' => 'git clone',
        'repoNames.fromApi.url' => 'someurl.com',
        'repoNames.fromApi.withPagination' => true,
        'repoNames.fromApi.total' => 10,
        'repoNames.fromApi.perPage' => 3,
        'repoNames.pattern' => 'items.*.name'
    ]);

    $config = ConfigReader::read($serversJson);
    $urls = $config->servers()[0]['repoNames']['fromApi']['urls'];

    expect($config)->toHaveLength(1);
    expect($urls)->toHaveLength(4);

    for ($i=0; $i <= 3; $i++) {
        expect($urls[$i])->toBe("someurl.com?page=" . $i+1);
    }

    $serversJson = $this->getServersJson([
        'name' => 'some name for server',
        'clone.to' => 'somewhere',
        'clone.using' => 'git clone',
        'repoNames.fromApi.url' => 'someurl.com?per_page=2&another',
        'repoNames.fromApi.withPagination' => true,
        'repoNames.fromApi.total' => 10,
        'repoNames.fromApi.perPage' => 3,
        'repoNames.pattern' => 'items.*.name'
    ]);

    $config = ConfigReader::read($serversJson);
    $urls = $config->servers()[0]['repoNames']['fromApi']['urls'];

    expect($config)->toHaveLength(1);
    expect($urls)->toHaveLength(4);

    for ($i=0; $i <= 3; $i++) {
        expect($urls[$i])->toBe("someurl.com?per_page=2&another&page=" . $i+1);
    }
});

test('query string is changeable', function () {
    $serversJson = $this->getServersJson([
        'name' => 'some name for server',
        'clone.to' => 'somewhere',
        'clone.using' => 'git clone',
        'repoNames.fromApi.url' => 'someurl.com',
        'repoNames.fromApi.withPagination' => true,
        'repoNames.fromApi.total' => 10,
        'repoNames.fromApi.perPage' => 3,
        'repoNames.fromApi.pageQueryString' => 'key',
        'repoNames.pattern' => 'items.*.name'
    ]);

    $config = ConfigReader::read($serversJson);
    $urls = $config->servers()[0]['repoNames']['fromApi']['urls'];

    expect($config)->toHaveLength(1);
    expect($urls)->toHaveLength(4);

    for ($i=0; $i <= 3; $i++) {
        expect($urls[$i])->toBe("someurl.com?key=" . $i+1);
    }

    $serversJson = $this->getServersJson([
        'name' => 'some name for server',
        'clone.to' => 'somewhere',
        'clone.using' => 'git clone',
        'repoNames.fromApi.url' => 'someurl.com?per_page=2&another',
        'repoNames.fromApi.withPagination' => true,
        'repoNames.fromApi.total' => 10,
        'repoNames.fromApi.perPage' => 3,
        'repoNames.fromApi.pageQueryString' => 'key',
        'repoNames.pattern' => 'items.*.name'
    ]);

    $config = ConfigReader::read($serversJson);
    $urls = $config->servers()[0]['repoNames']['fromApi']['urls'];

    expect($config)->toHaveLength(1);
    expect($urls)->toHaveLength(4);

    for ($i=0; $i <= 3; $i++) {
        expect($urls[$i])->toBe("someurl.com?per_page=2&another&key=" . $i+1);
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
        'repoNames.fromApi.url' => 'someurl.com',
        'repoNames.fromApi.withPagination' => true,
        'repoNames.fromApi.total' => "https://api.github.com/search/repositories?q=user:someusername",
        'repoNames.fromApi.totalKey' => "total_count",
        'repoNames.fromApi.perPage' => 3,
        'repoNames.pattern' => 'items.*.name'
    ]);

    $config = ConfigReader::read($serversJson);
    $urls = $config->servers()[0]['repoNames']['fromApi']['urls'];

    expect($config)->toHaveLength(1);
    expect($urls)->toHaveLength(4);

    for ($i=0; $i <= 3; $i++) {
        expect($urls[$i])->toBe("someurl.com?page=" . $i+1);
    }
});
