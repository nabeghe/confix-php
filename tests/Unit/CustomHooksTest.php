<?php

use Tests\Support\CustomHookConfig;
use Tests\Support\ExposeProtectedConfix;
use Tests\Support\FlatExposeProtectedConfix;

beforeEach(function () {
    cleanTestConfigDir();
    CustomHookConfig::clear();
    CustomHookConfig::$hookCalls = [];
    ExposeProtectedConfix::clear();
    Tests\Support\BaseTestConfig::$customDirectory = getTestConfigDir();
});

afterEach(function () {
    CustomHookConfig::clear();
    ExposeProtectedConfix::clear();
    Tests\Support\BaseTestConfig::$customDirectory = getTestConfigDir();
    cleanTestConfigDir();
});

test('mergeDefaults merges recursively when NESTED is true', function () {
    // ExposeProtectedConfix has DEFAULTS = ['nested' => ['a' => 1, 'b' => 2], 'flat' => 'default']
    $merged = ExposeProtectedConfix::callMergeDefaults([
        'nested' => [
            'b' => 99,
            'c' => 3,
        ],
        'extra' => 'val',
    ]);

    // array_merge_recursive merges nested arrays
    expect($merged)->toHaveKey('nested')
        ->and($merged['nested'])->toHaveKey('a')
        ->and($merged['nested'])->toHaveKey('c')
        ->and($merged['flat'])->toBe('default')
        ->and($merged['extra'])->toBe('val');
});

test('mergeDefaults merges shallowly when NESTED is false', function () {
    // FlatExposeProtectedConfix has NESTED = false, DEFAULTS = ['flat' => 'default', 'other' => 123]
    $merged = FlatExposeProtectedConfix::callMergeDefaults([
        'flat' => 'overridden',
        'extra' => 456,
    ]);

    expect($merged['flat'])->toBe('overridden')
        ->and($merged['other'])->toBe(123)
        ->and($merged['extra'])->toBe(456);
});

test('custom varExport hook is invoked during save', function () {
    CustomHookConfig::set('some_key', 'some_value');
    expect(CustomHookConfig::save())->toBeTrue();

    expect(CustomHookConfig::$hookCalls)->toHaveKey('varExport')
        ->and(CustomHookConfig::$hookCalls['varExport'])->toBeTrue();
});

test('custom mkdir and filePutContents hooks are invoked during writeToFile', function () {
    $targetDir = getTestConfigDir() . DIRECTORY_SEPARATOR . 'custom_sub_dir';
    CustomHookConfig::$customDirectory = $targetDir;

    $content = '<?php return ["custom" => "content"];';
    expect(CustomHookConfig::callWriteToFile($content))->toBeTrue();

    expect(CustomHookConfig::$hookCalls)->toHaveKey('mkdir')
        ->and(CustomHookConfig::$hookCalls)->toHaveKey('filePutContents')
        ->and(CustomHookConfig::$hookCalls['filePutContents']['content'])->toBe($content);
});

test('writeToFile removes existing file and creates new directory if needed', function () {
    $subDir = getTestConfigDir() . DIRECTORY_SEPARATOR . 'deep' . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'config';
    ExposeProtectedConfix::$customDirectory = $subDir;

    $filePath = ExposeProtectedConfix::callGetFilePath();
    expect(file_exists($filePath))->toBeFalse();

    $content = '<?php return ["written" => true];';
    expect(ExposeProtectedConfix::callWriteToFile($content))->toBeTrue();

    expect(file_exists($filePath))->toBeTrue();
    $data = include $filePath;
    expect($data)->toBe(['written' => true]);

    // Writing again overwrites existing file
    $newContent = '<?php return ["overwritten" => true];';
    expect(ExposeProtectedConfix::callWriteToFile($newContent))->toBeTrue();

    $newData = include $filePath;
    expect($newData)->toBe(['overwritten' => true]);
});

test('getDirectory and getFilePath return appropriate paths', function () {
    $dir = ExposeProtectedConfix::callGetDirectory();
    $file = ExposeProtectedConfix::callGetFilePath();

    expect($dir)->not->toBeEmpty()
        ->and($file)->toBe($dir . '/' . ExposeProtectedConfix::NAME . '.php');
});

test('base validate returns true by default and base varExport formats code', function () {
    expect(ExposeProtectedConfix::callValidate('any_key', 'any_value'))->toBeTrue();

    $exported = ExposeProtectedConfix::callVarExport(['test' => 123]);
    expect($exported)->toBeString();
});
