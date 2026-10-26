<?php

use Tests\Support\AnotherAppConfig;
use Tests\Support\SimpleAppConfig;
use Tests\Support\StrictReloadConfig;

beforeEach(function () {
    cleanTestConfigDir();
    SimpleAppConfig::clear();
    AnotherAppConfig::clear();
    StrictReloadConfig::clear();
});

afterEach(function () {
    SimpleAppConfig::clear();
    AnotherAppConfig::clear();
    StrictReloadConfig::clear();
    cleanTestConfigDir();
});

test('save writes valid PHP array file with flock and var_export', function () {
    SimpleAppConfig::set('name', 'Persisted App Name');
    SimpleAppConfig::set('debug', true);
    SimpleAppConfig::set('version', '2.5.0');

    expect(SimpleAppConfig::save())->toBeTrue();

    $filePath = SimpleAppConfig::callGetFilePath();
    expect(file_exists($filePath))->toBeTrue();

    // Verify it is a valid PHP file returning an array
    $fileData = include $filePath;
    expect($fileData)->toBeArray()
        ->and($fileData['name'])->toBe('Persisted App Name')
        ->and($fileData['debug'])->toBeTrue()
        ->and($fileData['version'])->toBe('2.5.0');
});

test('save filters out null and empty array sections', function () {
    SimpleAppConfig::set('active_section', ['key' => 'val']);
    SimpleAppConfig::set('null_section', null);

    expect(SimpleAppConfig::save())->toBeTrue();

    $filePath = SimpleAppConfig::callGetFilePath();
    $fileData = include $filePath;

    expect($fileData)->toHaveKey('active_section')
        ->and($fileData)->not->toHaveKey('null_section');
});

test('reload discards in-memory modifications and restores file state', function () {
    SimpleAppConfig::set('name', 'Saved State');
    SimpleAppConfig::save();

    // Modify in-memory
    SimpleAppConfig::set('name', 'Unsaved Memory Modification');
    expect(SimpleAppConfig::get('name'))->toBe('Unsaved Memory Modification');

    // Reload from file
    expect(SimpleAppConfig::reload())->toBeTrue();
    expect(SimpleAppConfig::get('name'))->toBe('Saved State');
});

test('setData with save: true persists to disk immediately', function () {
    SimpleAppConfig::setData(['name' => 'Direct Save', 'debug' => false], save: true);

    $filePath = SimpleAppConfig::callGetFilePath();
    expect(file_exists($filePath))->toBeTrue();

    $fileData = include $filePath;
    expect($fileData['name'])->toBe('Direct Save');
});

test('remove deletes the configuration file from disk', function () {
    SimpleAppConfig::set('name', 'To Be Deleted');
    SimpleAppConfig::save();

    $filePath = SimpleAppConfig::callGetFilePath();
    expect(file_exists($filePath))->toBeTrue();

    expect(SimpleAppConfig::remove())->toBeTrue();
    expect(file_exists($filePath))->toBeFalse();

    // Calling remove when file does not exist still returns true
    expect(SimpleAppConfig::remove())->toBeTrue();
});

test('reload on non-existent file sets data to empty array and returns true', function () {
    SimpleAppConfig::remove();

    expect(SimpleAppConfig::reload())->toBeTrue();
    expect(SimpleAppConfig::get('name'))->toBe('My Application'); // default
});

test('reload catches exception when IGNORE_RELOAD_EXEPTION is true', function () {
    $filePath = SimpleAppConfig::callGetFilePath();
    // Write invalid PHP syntax that causes a ParseError
    file_put_contents($filePath, '<?php this is invalid syntax;');

    // Default IGNORE_RELOAD_EXEPTION is true -> returns false instead of crashing
    expect(SimpleAppConfig::reload())->toBeFalse();
});

test('reload throws exception when IGNORE_RELOAD_EXEPTION is false', function () {
    $filePath = StrictReloadConfig::callGetFilePath();
    file_put_contents($filePath, '<?php this is invalid syntax;');

    // StrictReloadConfig has IGNORE_RELOAD_EXEPTION = false -> throws ParseError
    expect(fn () => StrictReloadConfig::reload())->toThrow(\ParseError::class);
});

test('reloadAllLoadedClasses reloads all previously loaded config classes', function () {
    SimpleAppConfig::set('name', 'App 1');
    SimpleAppConfig::save();

    AnotherAppConfig::set('title', 'App 2');
    AnotherAppConfig::save();

    // Ensure both are loaded
    SimpleAppConfig::reload();
    AnotherAppConfig::reload();

    $results = SimpleAppConfig::reloadAllLoadedClasses();

    expect($results)->toBeArray()
        ->and($results)->toHaveKey(SimpleAppConfig::class)
        ->and($results)->toHaveKey(AnotherAppConfig::class)
        ->and($results[SimpleAppConfig::class])->toBeTrue()
        ->and($results[AnotherAppConfig::class])->toBeTrue();
});
