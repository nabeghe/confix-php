<?php

require_once __DIR__ . '/Support/TestConfigs.php';

use Tests\Support\BaseTestConfig;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase".
|
*/

// Set up a temporary test directory for all tests
function getTestConfigDir(): string
{
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'confix_pest_tests';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    return $dir;
}

function cleanTestConfigDir(?string $dir = null): void
{
    $dir ??= getTestConfigDir();
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
        return;
    }
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            cleanTestConfigDir($path);
            @rmdir($path);
        } else {
            @unlink($path);
        }
    }
}

// Ensure tests use the temporary directory
BaseTestConfig::$customDirectory = getTestConfigDir();
