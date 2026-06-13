# Confix

> **Static-architecture PHP configuration library.**  
> Simple, file-backed, class-based configuration with parent/child partitioning and dot-notation support.

[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.3-blue)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

---

## Table of Contents

- [Overview](#overview)
- [Installation](#installation)
- [Core Concepts](#core-concepts)
    - [Standalone Config (own file)](#standalone-config-own-file)
    - [Child Config (shared file)](#child-config-shared-file)
    - [Dynamic Method Calls](#dynamic-method-calls)
    - [Nested Dot-Notation](#nested-dot-notation)
    - [Custom Validation](#custom-validation)
- [Constants Reference](#constants-reference)
- [API Reference](#api-reference)
    - [Reading Values](#reading-values)
    - [Writing Values](#writing-values)
    - [Removing Values](#removing-values)
    - [Bulk Data Operations](#bulk-data-operations)
    - [File Persistence](#file-persistence)
    - [Overridable Hooks](#overridable-hooks)
- [Running Tests](#running-tests)
- [License](#license)

---

## Overview

Confix is designed with the goal that your configuration system should not require instantiating a class; it works in a fully static manner.
You can have static configuration classes whose data source is a file; a file that returns the configuration as an array.
Each class can have its own dedicated config file, or it can be a section within a parent config file.

In fact, two types of classes can be implemented using Confix:
1. Parent classes (Standalone configs) that have their own dedicated config file. For example, `AppSettings`, whose file could be `app.php`.
2. Child (nested) classes that are linked to a parent class. For example, `DatabaseSettings`, which can be linked to `app.php` and whose configuration is stored within a section (key) named `database`.

The purpose of having dedicated classes for configs is to use dynamic static methods, handled by __`callStatic`;
for getting and setting config keys, so there is no need to write keys as raw strings.
There is also no need to actually implement the static methods;
it is sufficient to declare them in the class docblock as `@method` annotations to assist the IDE.
That said, methods for accessing keys as plain strings or via dot-notation are also available.

---

## Installation

```bash
composer require nabeghe/confix
```

Requires **PHP ≥ 8.3**.

---

## Core Concepts

### Base Config class

By default, Confix uses the config directory in your project root.
It is recommended to configure this path yourself, even if you do not intend to change it.
Therefore, you should have a base class, and all other config classes should extend this class instead of extending Confix directly.
So:

```php
use Nabeghe\Confix\Confix;

abstract class Settings extends Confix
{
    #[Override]
    protected static function getDirectory(): string
    {
        // TODO: Return the path to the config files directory without a trailing slash.
    }
}
```

You can also override methods such as `mkdir`, `filePutContents`, and `varExport` to implement your own logic.
I recommend using `Symfony\Component\VarExporter` for `varExport`, and `Symfony\Component\Filesystem\Filesystem` for `mkdir` and `filePutContents`.

### Standalone Config (own file)

A standalone config declares `protected static array $_;` which signals that it owns its own config file.

```php
use Nabeghe\Confix\Confix;

/**
 * @method static string name(string $value = 0)
 * @method static bool debug(bool $value = 0)
 * @method static string version(string $value = 0)
 */
class AppConfig extends Confix
{
    const string NAME = 'app'; // → config/app.php

    const array DEFAULTS = [
        'name'    => 'My Application',
        'debug'   => false,
        'version' => '1.0.0',
    ];

    protected static array $_; // declares file ownership
}
```

The directory defaults to `<project-root>/config/`. Override `getDirectory()` to customise it.

---

### Child Config (shared file)

A child config sets `PARENT` to point at another config class.
It lives as a **keyed section** inside the parent's file; no separate file is created.

```php

/**
 * @method static string driver(string $value = 0)
 * @method static int ttl(int $value = '')
 */
class CacheConfig extends Confix
{
    const string NAME     = 'cache'; // key inside app.php
    
    const ?string PARENT  = AppConfig::class;

    const array DEFAULTS = [
        'driver' => 'file',
        'ttl'    => 600,
    ];
    
    // No protected static array $_ here!
}
```

The resulting `app.php` file will look like:

```php
<?php return [
    'name'  => 'My Application',
    'cache' => [
        'driver' => 'redis',
        'ttl'    => 3600,
    ],
];
```

Chains of any depth work: `GrandChildConfig::PARENT = CacheConfig::class` delegates all the way up to `AppConfig`.

---

## Dynamic Method Calls

Any method name that is **not** defined on `Confix` is intercepted by `__callStatic()` and treated as a config key:

| Call | Equivalent | Returns |
|---|---|---|
| `Config::key_name()` | `Config::get('key_name')` | Current or default value |
| `Config::key_name($value)` | `Config::set('key_name', $value)` | `true` / `false` |
| `Config::key_name(null)` | `Config::forget('key_name')` | `true` |

Document these in your config's docblock for IDE autocompletion:

```php
/**
 * @method static string name(string $value = 0)
 * @method static bool   debug(bool $value = 0)
 * @method static int    version(string $value = 0)
 */
class AppConfig extends Confix { ... }
```

---

### Nested Dot-Notation

Set `const bool NESTED = true` to enable accessing multi-level arrays with dot-notation strings.

```php
class InfraConfig extends Confix
{
    const string NAME  = 'infra';
    
    const bool   NESTED = true; // important

    const array DEFAULTS = [
        'db' => [
            'host' => 'localhost',
            'port' => 3306,
        ],
    ];

    protected static array $_;
}

// Read
InfraConfig::get('db.host'); // 'localhost'
InfraConfig::get('db.port'); // 3306

// Write
InfraConfig::set('db.host', '10.0.0.1');

// Forget
InfraConfig::forget('db.port');
```

**Notice:** Without `NESTED = true`, dot-notation keys are treated as literal strings.

---

### Custom Validation

Override the protected `validate()` method to guard individual keys:

```php
class ServerConfig extends Confix
{
    const string NAME = 'server';
    
    const array  DEFAULTS = ['port' => 80];
    
    protected static array $_;

    protected static function validate(string $name, mixed $value): bool
    {
        return match ($name) {
            'port' => is_int($value) && $value > 0 && $value <= 65535,
            default => true,
        };
    }
}

ServerConfig::set('port', 443); // ✅ true — stored
ServerConfig::set('port', -1); // ❌ false — rejected, old value kept
```

Pass `false` as the third argument to `set()` to skip validation:

```php
ServerConfig::set('port', 0, false); // bypasses validate(), stored as-is
```

---

## Constants Reference

| Constant | Type | Default | Description |
|---|---|---|---|
| `NAME` | `string` | `'app'` | Key name used for the file name (standalone) or section key (child) |
| `PARENT` | `?string` | `null` | Fully-qualified class name of the parent config, or `null` for standalone |
| `NESTED` | `bool` | `false` | Enable dot-notation access for multi-dimensional arrays |
| `DEFAULTS` | `array` | `[]` | Fallback values returned when a key has no runtime value |

---

## API Reference

### Reading Values

#### `get(string $name, bool $includeDefault = true): mixed`

Returns the runtime value for `$name`.
If not set, falls back to `DEFAULTS[$name]` when `$includeDefault` is `true`;
returns `null` otherwise.

```php
AppConfig::get('debug'); // Can include default
AppConfig::get('debug', false); // Cannot include default
```

#### `getDefault(string $name): mixed`

Returns the value from `DEFAULTS` directly, bypassing runtime data.

```php
AppConfig::getDefault('version'); // '1.0.0'
```

#### `has(string $name, bool $includeDefault = true): bool`

Returns `true` when a non-null value exists for `$name`.

```php
AppConfig::has('name'); // Can include default
AppConfig::has('name', false); // Cannot include default
```

#### `hasDefault(string $name): bool`

Returns `true` if the key is declared in `DEFAULTS`.

```php
AppConfig::hasDefault('version'); // true
AppConfig::hasDefault('ghost_key'); // false
```

---

### Writing Values

#### `set(string $name, mixed $value, bool $validate = true): bool`

Stores `$value` for `$name`. Runs `validate()` first unless `$validate` is `false`. Returns `true` on success, `false` if validation fails or `$value` is `null` (which triggers removal).

```php
AppConfig::set('debug', true); // true
AppConfig::set('port', -1); // false — validation failed
AppConfig::set('port', -1, false);  // true  — validation skipped
```

---

### Removing Values

#### `forget(string $name): void`

Removes `$name` from the runtime data. The default value is still accessible afterwards.

```php
AppConfig::forget('debug');
AppConfig::get('debug');    // false — falls back to DEFAULTS
```

#### `clear(?string $name = null): void`

Clears **all** runtime data in the config.

```php
AppConfig::clear();
AppConfig::getData();    // []
```

---

### Bulk Data Operations

#### `getData(bool $default = false): array|bool`

Returns the current runtime data array (or the child partition for child configs).

```php
$data = AppConfig::getData();
// ['name' => 'My Application', 'cache' => ['driver' => 'redis']]
```

#### `getDataByRef(): array` *(reference)*

Returns a **reference** to the internal data array, allowing direct mutation.

```php
$ref = &AppConfig::getDataByRef();
$ref['name'] = 'Updated';
AppConfig::get('name', false); // 'Updated'
```

#### `setData(?array $newData = null, bool $save = false): bool`

Replaces the entire runtime data array. Pass `$save = true` to immediately persist to file.

```php
AppConfig::setData(['name' => 'New App', 'debug' => false]);
AppConfig::setData(['name' => 'New App'], save: true); // writes file
```

---

### File Persistence

#### `load(): bool`

Loads data from the config file into memory. Auto-called on first access; you rarely need to call this manually.

```php
AppConfig::load();
```

#### `reload(): bool`

Forces a fresh read from the filesystem, discarding any in-memory changes.

```php
AppConfig::reload();
```

#### `reloadAllLoadedClasses(): array`

Reloads every config class that has been accessed in the current request. Returns an array of `[class => bool]`.

```php
$results = AppConfig::reloadAllLoadedClasses();
// ['App\Config\AppConfig' => true, 'App\Config\CacheConfig' => true]
```

#### `save(): bool`

Serialises the runtime data to the config file using `flock()` for safe concurrent writes. Child configs delegate to the parent.

```php
AppConfig::set('name', 'Updated');
AppConfig::save(); // writes config/app.php
```

#### `remove(): bool`

Deletes the config file from disk.

```php
AppConfig::remove();
```

---

### Overridable Hooks

These protected static methods can be overridden in your base config class:

| Method | Signature | Purpose |
|---|---|---|
| `getDirectory()` | `(): string` | Return the directory where config files are stored |
| `mkdir()` | `(string $path): bool` | Create a directory; override to use your filesystem abstraction |
| `filePutContents()` | `(string $file, string $content): bool` | Write content to a file; override to use custom writers |
| `varExport()` | `(mixed $var): string` | Serialise a value to PHP code; override to use e.g. `VarExporter::export()` |
| `validate()` | `(string $name, mixed $value): bool` | Return `false` to reject a value before storage |

---

## 📜 License

Created with ❤️ by Nabeghe. Licensed under the [MIT License](LICENSE.md). Free to use, modify, and distribute!

