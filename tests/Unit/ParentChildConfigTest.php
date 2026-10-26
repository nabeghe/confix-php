<?php

use Tests\Support\CacheChildConfig;
use Tests\Support\ChildWithUnderscoreConfig;
use Tests\Support\RedisGrandchildConfig;
use Tests\Support\SessionChildConfig;
use Tests\Support\SimpleAppConfig;

beforeEach(function () {
    cleanTestConfigDir();
    SimpleAppConfig::clear();
});

afterEach(function () {
    SimpleAppConfig::clear();
    cleanTestConfigDir();
});

test('child config retrieves defaults correctly', function () {
    expect(CacheChildConfig::getDefault('driver'))->toBe('file')
        ->and(CacheChildConfig::getDefault('ttl'))->toBe(600)
        ->and(CacheChildConfig::getDefault('non_existent'))->toBeNull();

    expect(CacheChildConfig::hasDefault('driver'))->toBeTrue()
        ->and(CacheChildConfig::hasDefault('ttl'))->toBeTrue()
        ->and(CacheChildConfig::hasDefault('unknown'))->toBeFalse();
});

test('child config reads defaults via get and has when runtime data is empty', function () {
    expect(CacheChildConfig::get('driver'))->toBe('file')
        ->and(CacheChildConfig::get('ttl'))->toBe(600)
        ->and(CacheChildConfig::has('driver'))->toBeTrue()
        ->and(CacheChildConfig::has('ttl'))->toBeTrue()
        ->and(CacheChildConfig::has('non_existent'))->toBeFalse();
});

test('child config set stores data inside parent data under section name', function () {
    CacheChildConfig::set('driver', 'redis');
    CacheChildConfig::set('ttl', 3600);

    // Child sees the updated values
    expect(CacheChildConfig::get('driver'))->toBe('redis')
        ->and(CacheChildConfig::get('ttl'))->toBe(3600);

    // Parent sees the section
    $parentData = SimpleAppConfig::getData();
    expect($parentData)->toHaveKey('cache')
        ->and($parentData['cache']['driver'])->toBe('redis')
        ->and($parentData['cache']['ttl'])->toBe(3600);
});

test('child dynamic method calls work via __callStatic', function () {
    expect(CacheChildConfig::driver())->toBe('file')
        ->and(CacheChildConfig::ttl())->toBe(600);

    expect(CacheChildConfig::driver('memcached'))->toBeTrue()
        ->and(CacheChildConfig::ttl(1800))->toBeTrue();

    expect(CacheChildConfig::driver())->toBe('memcached')
        ->and(CacheChildConfig::ttl())->toBe(1800);

    // Forget via dynamic call with null
    expect(CacheChildConfig::driver(null))->toBeTrue();
    expect(CacheChildConfig::driver())->toBe('file'); // falls back to default
});

test('child forget removes key from section without affecting parent or other keys', function () {
    SimpleAppConfig::set('name', 'Main App');
    CacheChildConfig::set('driver', 'array');
    CacheChildConfig::set('extra', 'temporary');

    CacheChildConfig::forget('extra');
    expect(CacheChildConfig::get('extra'))->toBeNull();

    CacheChildConfig::forget('driver');
    expect(CacheChildConfig::get('driver'))->toBe('file'); // default

    // Parent name is intact
    expect(SimpleAppConfig::get('name'))->toBe('Main App');
});

test('child clear removes only child section data without affecting parent or sibling children', function () {
    SimpleAppConfig::set('name', 'App One');
    CacheChildConfig::set('driver', 'apc');
    SessionChildConfig::set('lifetime', 999);

    // Clear only cache child
    CacheChildConfig::clear();

    // Cache is cleared
    expect(CacheChildConfig::getData())->toBeEmpty()
        ->and(CacheChildConfig::get('driver'))->toBe('file'); // default

    // Sibling session child is untouched
    expect(SessionChildConfig::get('lifetime'))->toBe(999);

    // Parent is untouched
    expect(SimpleAppConfig::get('name'))->toBe('App One');
});

test('sibling child configs partition data under their own names in the same parent', function () {
    CacheChildConfig::set('driver', 'redis');
    SessionChildConfig::set('lifetime', 7200);

    $parentData = SimpleAppConfig::getData();
    expect($parentData)->toHaveKey('cache')
        ->and($parentData)->toHaveKey('session')
        ->and($parentData['cache']['driver'])->toBe('redis')
        ->and($parentData['session']['lifetime'])->toBe(7200);
});

test('grandchild config delegates through parent chain to root standalone parent', function () {
    expect(RedisGrandchildConfig::getDefault('host'))->toBe('127.0.0.1')
        ->and(RedisGrandchildConfig::getDefault('port'))->toBe(6379);

    RedisGrandchildConfig::set('host', '10.0.0.5');
    RedisGrandchildConfig::set('port', 6380);

    expect(RedisGrandchildConfig::get('host'))->toBe('10.0.0.5')
        ->and(RedisGrandchildConfig::get('port'))->toBe(6380);

    // Check intermediate child data
    $cacheData = CacheChildConfig::getData();
    expect($cacheData)->toHaveKey('redis')
        ->and($cacheData['redis']['host'])->toBe('10.0.0.5')
        ->and($cacheData['redis']['port'])->toBe(6380);

    // Check root parent data
    $parentData = SimpleAppConfig::getData();
    expect($parentData['cache']['redis']['host'])->toBe('10.0.0.5');

    // Grandchild forget
    RedisGrandchildConfig::forget('port');
    expect(RedisGrandchildConfig::get('port'))->toBe(6379); // default
});

test('child class declaring protected static array $_ still delegates to PARENT', function () {
    // Specifically testing the scenario where a class has PARENT and also defines $_
    expect(ChildWithUnderscoreConfig::getDefault('option_a'))->toBe('alpha')
        ->and(ChildWithUnderscoreConfig::get('option_a'))->toBe('alpha');

    ChildWithUnderscoreConfig::set('option_a', 'beta');
    ChildWithUnderscoreConfig::set('option_b', 'gamma');

    expect(ChildWithUnderscoreConfig::get('option_a'))->toBe('beta')
        ->and(ChildWithUnderscoreConfig::get('option_b'))->toBe('gamma');

    // The data is stored inside parent's data under 'child_with_underscore'
    $parentData = SimpleAppConfig::getData();
    expect($parentData)->toHaveKey('child_with_underscore')
        ->and($parentData['child_with_underscore']['option_a'])->toBe('beta')
        ->and($parentData['child_with_underscore']['option_b'])->toBe('gamma');

    // Child getData returns only its section
    expect(ChildWithUnderscoreConfig::getData())->toBe([
        'option_a' => 'beta',
        'option_b' => 'gamma',
    ]);

    // Child getDataByRef mutates the parent section
    $ref = &ChildWithUnderscoreConfig::getDataByRef();
    $ref['option_a'] = 'mutated_via_ref';

    expect(ChildWithUnderscoreConfig::get('option_a'))->toBe('mutated_via_ref')
        ->and(SimpleAppConfig::getData()['child_with_underscore']['option_a'])->toBe('mutated_via_ref');
});

test('child file persistence operations delegate to parent', function () {
    SimpleAppConfig::set('name', 'Persistent App');
    CacheChildConfig::set('driver', 'database');
    RedisGrandchildConfig::set('host', '192.168.1.100');

    // Save called from grandchild should persist everything to parent's file
    expect(RedisGrandchildConfig::save())->toBeTrue();

    // Verify parent file exists
    $filePath = SimpleAppConfig::callGetFilePath();
    expect(file_exists($filePath))->toBeTrue();

    $content = file_get_contents($filePath);
    expect($content)->toContain('Persistent App')
        ->and($content)->toContain('database')
        ->and($content)->toContain('192.168.1.100');

    // Clear runtime data
    SimpleAppConfig::clear();
    expect(CacheChildConfig::get('driver'))->toBe('file'); // falls back to default

    // Load called from child loads the parent file
    expect(CacheChildConfig::load())->toBeTrue();
    expect(CacheChildConfig::get('driver'))->toBe('database')
        ->and(RedisGrandchildConfig::get('host'))->toBe('192.168.1.100')
        ->and(SimpleAppConfig::get('name'))->toBe('Persistent App');

    // Reload called from grandchild
    expect(RedisGrandchildConfig::reload())->toBeTrue();
    expect(CacheChildConfig::get('driver'))->toBe('database');

    // Remove called from child deletes the parent file
    expect(CacheChildConfig::remove())->toBeTrue();
    expect(file_exists($filePath))->toBeFalse();
});
