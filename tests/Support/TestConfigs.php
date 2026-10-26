<?php

namespace Tests\Support;

use Nabeghe\Confix\Confix;
use Override;

/**
 * Base config for tests that redirects files to a temporary directory.
 */
abstract class BaseTestConfig extends Confix
{
    public static ?string $customDirectory = null;

    #[Override]
    public static function getDirectory(): string
    {
        if (static::PARENT) {
            return static::PARENT::getDirectory();
        }

        return static::$customDirectory ?? (sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'confix_tests');
    }

    public static function callGetFilePath(): string
    {
        return static::getFilePath();
    }

    public static function callWriteToFile(string $content): bool
    {
        return static::writeToFile($content);
    }

    public static function callMkdir(string $path): bool
    {
        return static::mkdir($path);
    }

    public static function callFilePutContents(string $file, string $content): bool
    {
        return static::filePutContents($file, $content);
    }

    public static function resetState(): void
    {
        static::$_ = [];
    }
}

/**
 * Standalone configuration with defaults and IDE dynamic methods docblock.
 *
 * @method static string name(string $value = 0)
 * @method static bool debug(bool $value = 0)
 * @method static string version(string $value = 0)
 * @method static mixed dynamicKey(mixed $value = null)
 */
class SimpleAppConfig extends BaseTestConfig
{
    const string NAME = 'simple_app';

    const array DEFAULTS = [
        'name'    => 'My Application',
        'debug'   => false,
        'version' => '1.0.0',
    ];

    protected static array $_;
}

/**
 * Another standalone configuration to test isolation and reloadAllLoadedClasses.
 */
class AnotherAppConfig extends BaseTestConfig
{
    const string NAME = 'another_app';

    const array DEFAULTS = [
        'title' => 'Another',
    ];

    protected static array $_;
}

/**
 * Child configuration sharing file with SimpleAppConfig.
 *
 * @method static string driver(string $value = 0)
 * @method static int ttl(int $value = '')
 */
class CacheChildConfig extends BaseTestConfig
{
    const string NAME = 'cache';

    const ?string PARENT = SimpleAppConfig::class;

    const array DEFAULTS = [
        'driver' => 'file',
        'ttl'    => 600,
    ];
}

/**
 * Another sibling child configuration sharing file with SimpleAppConfig.
 */
class SessionChildConfig extends BaseTestConfig
{
    const string NAME = 'session';

    const ?string PARENT = SimpleAppConfig::class;

    const array DEFAULTS = [
        'lifetime' => 120,
    ];
}

/**
 * Grandchild configuration (Child of CacheChildConfig, Grandchild of SimpleAppConfig).
 */
class RedisGrandchildConfig extends BaseTestConfig
{
    const string NAME = 'redis';

    const ?string PARENT = CacheChildConfig::class;

    const array DEFAULTS = [
        'host' => '127.0.0.1',
        'port' => 6379,
    ];
}

/**
 * Child configuration that points to PARENT AND declares its own protected static array $_.
 * Tests behavior specifically requested by user.
 */
class ChildWithUnderscoreConfig extends BaseTestConfig
{
    const string NAME = 'child_with_underscore';

    const ?string PARENT = SimpleAppConfig::class;

    const array DEFAULTS = [
        'option_a' => 'alpha',
    ];

    protected static array $_;
}

/**
 * Configuration with NESTED = true for dot-notation access.
 */
class NestedInfraConfig extends BaseTestConfig
{
    const string NAME = 'nested_infra';

    const bool NESTED = true;

    const array DEFAULTS = [
        'db' => [
            'host' => 'localhost',
            'port' => 3306,
            'credentials' => [
                'user' => 'root',
                'password' => '',
            ],
        ],
        'logging' => [
            'level' => 'debug',
        ],
    ];

    protected static array $_;
}

/**
 * Configuration with custom validation on keys.
 */
class ValidatedServerConfig extends BaseTestConfig
{
    const string NAME = 'validated_server';

    const array DEFAULTS = [
        'port' => 80,
        'host' => '127.0.0.1',
    ];

    protected static array $_;

    #[Override]
    protected static function validate(string $name, mixed $value): bool
    {
        return match ($name) {
            'port' => is_int($value) && $value > 0 && $value <= 65535,
            'host' => is_string($value) && filter_var($value, FILTER_VALIDATE_IP) !== false,
            default => true,
        };
    }
}

/**
 * Configuration with IGNORE_RELOAD_EXEPTION = false to test exception throwing on reload.
 */
class StrictReloadConfig extends BaseTestConfig
{
    const string NAME = 'strict_reload';

    const bool IGNORE_RELOAD_EXEPTION = false;

    protected static array $_;
}

/**
 * Configuration that overrides hooks: varExport, mkdir, filePutContents.
 */
class CustomHookConfig extends BaseTestConfig
{
    const string NAME = 'custom_hook';

    public static array $hookCalls = [];

    protected static array $_;

    #[Override]
    protected static function varExport(mixed $var): string
    {
        static::$hookCalls['varExport'] = true;
        return parent::varExport($var);
    }

    #[Override]
    protected static function mkdir(string $path): bool
    {
        static::$hookCalls['mkdir'] = $path;
        return parent::mkdir($path);
    }

    #[Override]
    protected static function filePutContents(string $file, string $content): bool
    {
        static::$hookCalls['filePutContents'] = ['file' => $file, 'content' => $content];
        return parent::filePutContents($file, $content);
    }
}

/**
 * Test class exposing protected methods for testing.
 */
class ExposeProtectedConfix extends BaseTestConfig
{
    const string NAME = 'exposed';

    const bool NESTED = true;

    const array DEFAULTS = [
        'nested' => [
            'a' => 1,
            'b' => 2,
        ],
        'flat' => 'default',
    ];

    protected static array $_;

    public static function callMergeDefaults(array $data): array
    {
        return static::mergeDefaults($data);
    }

    public static function callWriteToFile(string $content): bool
    {
        return static::writeToFile($content);
    }

    public static function callMkdir(string $path): bool
    {
        return static::mkdir($path);
    }

    public static function callFilePutContents(string $file, string $content): bool
    {
        return static::filePutContents($file, $content);
    }

    public static function callGetDirectory(): string
    {
        return static::getDirectory();
    }

    public static function callGetFilePath(): string
    {
        return static::getFilePath();
    }

    public static function callEnsureLoaded(?string $name = null): void
    {
        static::ensureLoaded($name);
    }

    public static function callVarExport(mixed $var): string
    {
        return static::varExport($var);
    }

    public static function callValidate(string $name, mixed $value): bool
    {
        return static::validate($name, $value);
    }
}

class FlatExposeProtectedConfix extends BaseTestConfig
{
    const string NAME = 'flat_exposed';

    const bool NESTED = false;

    const array DEFAULTS = [
        'flat' => 'default',
        'other' => 123,
    ];

    protected static array $_;

    public static function callMergeDefaults(array $data): array
    {
        return static::mergeDefaults($data);
    }
}
