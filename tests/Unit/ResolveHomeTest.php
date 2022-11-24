<?php

test('replaces ~ with home directory', function () {
    expect(resolvehome('~/some/dir'))->toBe($_SERVER['HOME'] . '/some/dir');
});

test('replaces ~ if is first character', function () {
    expect(resolvehome('/~/some/dir'))->toBe('/~/some/dir');
});

test('replaces first ~', function () {
    expect(resolvehome('~/~/some/~'))->toBe($_SERVER['HOME'] . '/~/some/~');
});
