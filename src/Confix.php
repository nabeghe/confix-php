<?php

namespace Nabeghe\Confix;

use Throwable;

/**
 * Confix - Static Configuration Library
 *
 * @link https://github.com/nabeghe/confix-php
 */
abstract class Confix
{
    const string NAME = 'app';

    /**
     * @var self
     * @noinspection PhpDocFieldTypeMismatchInspection
     */
    const ?string PARENT = null;

    const bool NESTED = false;

    const array DEFAULTS = [];

    const bool IGNORE_RELOAD_EXEPTION = true;

    protected static array $_;

    private static array $_loadedClasses = [];

    private function __construct()
    {
    }

    public static function __callStatic(string $name, array $arguments)
    {
        static::ensureLoaded();

        if ($arguments) {
            if ($arguments[0] === null) {
                static::forget($name);
            } else {
                static::set($name, $arguments[0]);
            }
            return true;
        }

        return static::get($name);
    }

    public static function get(string $name, bool $includeDefault = true): mixed
    {
        $data = &static::getDataByRef();

        if (static::NESTED && str_contains($name, '.')) {
            $keys = explode('.', $name);
            foreach ($keys as $key) {
                if (!is_array($data) || !isset($data[$key])) {
                    return $includeDefault ? static::getDefault($name) : null;
                }
                $data = $data[$key];
            }
            return $data;
        }

        return $data[$name] ?? static::getDefault($name);
    }

    public static function set(string $name, mixed $value, bool $validate = true): bool
    {
        if ($validate && !static::validate($name, $value)) {
            return false;
        }

        $data = &static::getDataByRef();

        if (static::NESTED && str_contains($name, '.')) {
            $current = &$data;
            $keys = explode('.', $name);
            $lastKey = array_pop($keys);
            foreach ($keys as $key) {
                if (!isset($current[$key]) || !is_array($current[$key])) {
                    $current[$key] = [];
                }
                $current = &$current[$key];
            }

            $current[$lastKey] = $value;
            if (is_null($value)) {
                unset($current[$lastKey]);
            }
        } else {
            $data[$name] = $value;
            if (is_null($value)) {
                unset($data[$name]);
            }
        }

        return true;
    }

    public static function has(string $name, bool $includeDefault = true): bool
    {
        return !is_null(static::get($name, $includeDefault));
    }

    public static function getDefault(string $name): mixed
    {
        if (static::NESTED && str_contains($name, '.')) {
            $data = static::DEFAULTS;

            $keys = explode('.', $name);
            foreach ($keys as $key) {
                if (!is_array($data) || !isset($data[$key])) {
                    return null;
                }
                $data = $data[$key];
            }

            return $data;
        }

        return static::DEFAULTS[$name] ?? null;
    }

    public static function hasDefault(string $name): bool
    {
        return !is_null(static::getDefault($name));
    }

    protected static function validate(string $name, mixed $value): bool
    {
        return true;
    }

    public static function forget(string $name): void
    {
        $data = &static::getDataByRef();
        if (!$data) {
            return;
        }

        if (static::NESTED && str_contains($name, '.')) {
            $current = &$data;
            $keys = explode('.', $name);
            $lastKey = array_pop($keys);
            foreach ($keys as $key) {
                if (!isset($current[$key]) || !is_array($current[$key])) {
                    return;
                }
                $current = &$current[$key];
            }

            if (array_key_exists($lastKey, $current)) {
                unset($current[$lastKey]);
            }
        } elseif (array_key_exists($name, $data)) {
            unset($data[$name]);
        }
    }

    public static function getData(bool $default = false): array|bool
    {
        self::ensureLoaded();

        if (static::PARENT) {
            return static::PARENT::getData()[static::NAME];
        } else {
            return static::$_;
        }
    }

    public static function &getDataByRef(): array
    {
        self::ensureLoaded();

        if (static::PARENT) {
            return static::PARENT::getDataByRef()[static::NAME];
        } else {
            return static::$_;
        }
    }

    public static function setData(?array $newData = null, bool $save = false): bool
    {
        $data = &static::getDataByRef();
        $data = $newData;
        unset($data);
        return !$save || static::save();
    }

    public static function save(): bool
    {
        if (static::PARENT) {
            return static::PARENT::save();
        }

        $filePath = static::getFilePath();
        $fp = fopen($filePath, 'w');

        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            return false;
        }

        try {
            $content = '<?php return '.static::varExport(array_filter(static::$_, fn($section) => !is_null($section) && (!is_array($section) || $section))).';';
            fwrite($fp, $content);
            fflush($fp);
            flock($fp, LOCK_UN);
            return true;
        } catch (Throwable) {
            return false;
        } finally {
            fclose($fp);
        }
    }

    protected static function varExport(mixed $var): string
    {
        return var_export(static::$_, true);
    }

    public static function load(): bool
    {
        if (static::PARENT) {
            return static::PARENT::load();
        }

        return self::reload();
    }

    public static function reload(): bool
    {
        if (static::PARENT) {
            return static::PARENT::reload();
        }

        try {
            $file = static::getFilePath();

            if (file_exists($file)) {
                static::$_ = include $file;
                if (!isset(static::$_)) {
                    static::$_ = [];
                }
            } else {
                static::$_ = [];
            }

            self::$_loadedClasses[static::class] = true;

            return true;
        } catch (Throwable $e) {
            if (!static::IGNORE_RELOAD_EXEPTION) {
                /** @noinspection PhpUnhandledExceptionInspection */
                throw $e;
            }

            return false;
        }
    }

    public static function reloadAllLoadedClasses(): array
    {
        $results = [];

        foreach (array_keys(self::$_loadedClasses) as $class) {
            if (class_exists($class) && is_subclass_of($class, Confix::class)) {
                $results[$class] = $class::reload();
            } else {
                $results[$class] = false;
            }
        }

        return $results;
    }

    public static function clear(?string $name = null): void
    {
        $data = &static::getDataByRef();
        $data = [];
    }

    public static function remove(): bool
    {
        if (static::PARENT) {
            return static::PARENT::remove();
        }

        $file = static::getFilePath();
        try {
            if (file_exists($file)) {
                unlink($file);
            }
        } catch (Throwable) {
        }
        return !file_exists($file);
    }

    protected static function ensureLoaded(?string $name = null): void
    {
        if (static::PARENT) {
            static::PARENT::ensureLoaded($name ?? static::NAME);
        } else {
            if (!isset(static::$_)) {
                static::load();
            }
            if (!isset(static::$_[$name])) {
                static::$_[$name] = [];
            }
        }
    }

    protected static function getDirectory(): string
    {
        if (static::PARENT) {
            return static::PARENT::getDirectory();
        }

        return dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'config';
    }

    protected static function getFilePath(): string
    {
        if (static::PARENT) {
            return static::PARENT::getFilePath();
        }

        return static::getDirectory().'/'.static::NAME.'.php';
    }

    protected static function writeToFile(string $content): bool
    {
        if (static::PARENT) {
            return static::PARENT::writeToFile($content);
        }

        static::remove();

        $filePath = static::getFilePath();
        $directory = dirname($filePath);

        if (!is_dir($directory) && !static::mkdir($directory)) {
            return false;
        }

        return static::filePutContents($filePath, $content);
    }

    protected static function mergeDefaults(array $data): array
    {
        if (static::NESTED) {
            return array_merge_recursive(static::DEFAULTS, $data);
        }
        return array_merge(static::DEFAULTS, $data);
    }

    protected static function mkdir(string $path): bool
    {
        if (static::PARENT) {
            return static::PARENT::mkdir($path);
        }

        return mkdir($path, 0755, true);
    }

    protected static function filePutContents(string $file, string $content): bool
    {
        if (static::PARENT) {
            return static::PARENT::filePutContents($file, $content);
        }

        return file_put_contents($file, $content, LOCK_EX) !== false;
    }
}
