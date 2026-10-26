# Confix

> **Modern, static-architecture PHP configuration library.**  
> File-backed, class-based configuration with parent/child partitioning, dot-notation support, and zero instantiation.

[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.3-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![Tests](https://img.shields.io/badge/Tests-Pest%20PHP-brightgreen?style=flat-square)](tests)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg?style=flat-square)](LICENSE.md)

---

## Table of Contents

- [Features](#features)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Core Concepts](#core-concepts)
  - [Recommended Base Class](#1-recommended-base-class)
  - [Standalone Config (Dedicated File)](#2-standalone-config-dedicated-file)
  - [Child Config (Shared File Partition)](#3-child-config-shared-file-partition)
  - [Chained Hierarchies (Grandchild Configs)](#4-chained-hierarchies-grandchild-configs)
  - [Dynamic Static Methods (`__callStatic`)](#5-dynamic-static-methods-__callstatic)
  - [Nested Dot-Notation](#6-nested-dot-notation)
  - [Custom Validation](#7-custom-validation)
- [Constants Reference](#constants-reference)
- [API Reference](#api-reference)
  - [Reading Data](#reading-data)
  - [Writing Data](#writing-data)
  - [Removing Data](#removing-data)
  - [Bulk Data & Reference Operations](#bulk-data--reference-operations)
  - [File Persistence & Lifecycle](#file-persistence--lifecycle)
  - [Overridable Hooks](#overridable-hooks)
- [Running Tests](#running-tests)
- [License](#license)

---

## Features

- ⚡ **Zero Instantiation:** 100% static architecture — no singletons, factories, or dependency injection containers required.
- 📁 **File-Backed & Auto-Loaded:** Automatically reads from and writes to native PHP array configuration files.
- 🌳 **Parent/Child Partitioning:** Organize configs into clean, dedicated classes while storing them in a single physical file.
- 🔗 **Multi-Level Delegation:** Seamlessly chain configs to any depth (`Child -> Parent -> Root`).
- 🎯 **IDE Autocompletion:** Use `@method` annotations to enjoy full IDE code completion without magic strings.
- 🔍 **Nested Dot-Notation:** Optional dot-notation (`db.connections.mysql.host`) with automatic nested array creation and cleanup.
- 🛡️ **Built-in Validation Hook:** Easily guard and validate configuration values before runtime storage.
- 🔒 **Safe Concurrent Writes:** File persistence uses `flock(LOCK_EX)` to prevent race conditions during saves.
- 🛠️ **Fully Customizable I/O:** Override hooks for storage directory, serialization, directory creation, and file writers.

---

## Installation

Install Confix via Composer:

```bash
composer require nabeghe/confix
```

> **Requirements:** PHP **8.3** or higher.

---

## Quick Start

```php
use Nabeghe\Confix\Confix;

/**
 * @method static string name(?string $value = null)
 * @method static bool debug(?bool $value = null)
 * @method static string version(?string $value = null)
 */
class AppConfig extends Confix
{
    const string NAME = 'app'; // maps to config/app.php

    const array DEFAULTS = [
        'name'    => 'My Application',
        'debug'   => false,
        'version' => '1.0.0',
    ];

    protected static array $_; // declares standalone file ownership
}

// 1. Read values (falls back to defaults automatically)
AppConfig::name();        // 'My Application'
AppConfig::get('debug');  // false

// 2. Write values at runtime
AppConfig::debug(true);   // true
AppConfig::get('debug');  // true

// 3. Persist to disk (writes config/app.php)
AppConfig::save();
```

---

## Core Concepts

### 1. Recommended Base Class

By default, Confix locates configuration files in `<project-root>/config`. It is best practice to define an abstract base class for your project to configure this path and any shared hooks:

```php
namespace App\Config;

use Nabeghe\Confix\Confix;
use Override;

abstract class Settings extends Confix
{
    #[Override]
    protected static function getDirectory(): string
    {
        return dirname(__DIR__, 2) . '/config';
    }
}
```

All your specific configuration classes can then extend `Settings` rather than `Confix` directly.

---

### 2. Standalone Config (Dedicated File)

A standalone configuration owns its own physical file. Declaring `protected static array $_;` signals file ownership:

```php
namespace App\Config;

/**
 * @method static string env(?string $value = null)
 * @method static bool debug(?bool $value = null)
 */
class AppConfig extends Settings
{
    const string NAME = 'app'; // → config/app.php

    const array DEFAULTS = [
        'env'   => 'production',
        'debug' => false,
    ];

    protected static array $_;
}
```

---

### 3. Child Config (Shared File Partition)

A child configuration sets `PARENT` to another config class and **does not** declare `protected static array $_;`. Instead, it lives as a **named section (key)** inside the parent's file:

```php
namespace App\Config;

/**
 * @method static string driver(?string $value = null)
 * @method static int ttl(?int $value = null)
 */
class CacheConfig extends Settings
{
    const string NAME = 'cache'; // key inside config/app.php

    const ?string PARENT = AppConfig::class;

    const array DEFAULTS = [
        'driver' => 'file',
        'ttl'    => 3600,
    ];
}
```

When saved, `config/app.php` neatly partitions both configs into one unified array:

```php
<?php return [
    'env'   => 'production',
    'debug' => false,
    'cache' => [
        'driver' => 'file',
        'ttl'    => 3600,
    ],
];
```

Operations on `CacheConfig` (such as `save()`, `load()`, `reload()`, and `remove()`) automatically delegate to `AppConfig`.

---

### 4. Chained Hierarchies (Grandchild Configs)

Confix supports parent delegation chains to **any depth**:

```php
namespace App\Config;

class RedisConfig extends Settings
{
    const string NAME = 'redis'; // section inside 'cache'

    const ?string PARENT = CacheConfig::class;

    const array DEFAULTS = [
        'host' => '127.0.0.1',
        'port' => 6379,
    ];
}

// Stored at: app.php → ['cache']['redis']['host']
RedisConfig::get('host'); // '127.0.0.1'
RedisConfig::set('host', '10.0.0.1');

// Persisting RedisConfig saves the root app.php file!
RedisConfig::save();
```

---

### 5. Dynamic Static Methods (`__callStatic`)

Instead of writing string keys manually, any static method call not explicitly declared on the class is intercepted by `__callStatic`:

| Method Call | Equivalent API Call | Description |
|---|---|---|
| `Config::key()` | `Config::get('key')` | Retrieves current or default value |
| `Config::key($value)` | `Config::set('key', $value)` | Stores `$value` and returns `true` |
| `Config::key(null)` | `Config::forget('key')` | Removes runtime value and returns `true` |

Add `@method` docblock tags to your configuration class to get instantaneous IDE autocompletion and type insights:

```php
/**
 * @method static string name(?string $value = null)
 * @method static bool debug(?bool $value = null)
 * @method static int timeout(?int $value = null)
 */
class AppConfig extends Settings { ... }
```

---

### 6. Nested Dot-Notation

Set `const bool NESTED = true;` to enable dot-notation access for multi-dimensional arrays:

```php
namespace App\Config;

class DatabaseConfig extends Settings
{
    const string NAME = 'database';
    
    const bool NESTED = true; // Enables dot-notation support

    const array DEFAULTS = [
        'connections' => [
            'mysql' => [
                'host' => '127.0.0.1',
                'port' => 3306,
                'user' => 'root',
            ],
        ],
    ];

    protected static array $_;
}

// 1. Reading nested keys
DatabaseConfig::get('connections.mysql.host'); // '127.0.0.1'
DatabaseConfig::has('connections.mysql.port'); // true

// 2. Writing nested keys (intermediate arrays are created automatically)
DatabaseConfig::set('connections.mysql.host', '192.168.1.100');
DatabaseConfig::set('connections.pgsql.port', 5432);

// 3. Removing nested keys
DatabaseConfig::forget('connections.pgsql.port');
DatabaseConfig::set('connections.mysql.host', null); // Setting null also unsets the key
```

> **Note:** When `NESTED = false` (default), keys containing dots (e.g. `'db.host'`) are treated as literal flat string keys.

---

### 7. Custom Validation

Override the protected `validate()` hook to guard keys before storing them at runtime:

```php
namespace App\Config;

use Override;

class ServerConfig extends Settings
{
    const string NAME = 'server';

    const array DEFAULTS = [
        'port' => 8080,
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

ServerConfig::set('port', 443);  // ✅ true  — stored
ServerConfig::set('port', -1);   // ❌ false — rejected, previous value kept
ServerConfig::set('port', 99999);// ❌ false — rejected

// Bypass validation if needed by passing false as 3rd parameter:
ServerConfig::set('port', -1, validate: false); // ✅ true — stored as-is
```

---

## Constants Reference

| Constant | Type | Default | Description |
|---|---|---|---|
| `NAME` | `string` | `'app'` | File name (for standalone configs) or section key (for child configs). |
| `PARENT` | `?string` | `null` | Fully-qualified class name of the parent configuration, or `null` for standalone. |
| `NESTED` | `bool` | `false` | When `true`, enables dot-notation traversal for multi-dimensional arrays. |
| `DEFAULTS` | `array` | `[]` | Fallback values returned when a key has not been explicitly set at runtime. |
| `IGNORE_RELOAD_EXEPTION` | `bool` | `true` | When `true`, catches reload exceptions (e.g. corrupt files) and returns `false`. When `false`, re-throws the exception. |

---

## API Reference

### Reading Data

#### `get(string $name, bool $includeDefault = true): mixed`
Returns the runtime value for `$name`. If the key is not set:
- Returns `DEFAULTS[$name]` when `$includeDefault = true`.
- Returns `null` when `$includeDefault = false`.

```php
AppConfig::get('debug');        // Returns runtime value or default
AppConfig::get('debug', false); // Returns runtime value or null
```

#### `getDefault(string $name): mixed`
Returns the default value directly from `DEFAULTS`, completely bypassing runtime data.

```php
AppConfig::getDefault('version'); // '1.0.0'
```

#### `has(string $name, bool $includeDefault = true): bool`
Returns `true` if a non-null value exists (or non-null default if `$includeDefault = true`).

```php
AppConfig::has('name');         // true
AppConfig::has('ghost_key');   // false
```

#### `hasDefault(string $name): bool`
Returns `true` if the key is defined and non-null in `DEFAULTS`.

```php
AppConfig::hasDefault('version'); // true
AppConfig::hasDefault('ghost');   // false
```

---

### Writing Data

#### `set(string $name, mixed $value, bool $validate = true): bool`
Stores `$value` for key `$name`.
- Validates `$value` via `validate()` unless `$validate = false`.
- Setting a key to `null` automatically unsets it from runtime data.
- Returns `true` on success, or `false` if validation failed.

```php
AppConfig::set('name', 'Acme App');
AppConfig::set('port', 8080, validate: false); // skips validation
AppConfig::set('name', null);                 // unsets 'name', falls back to default
```

---

### Removing Data

#### `forget(string $name): void`
Removes `$name` from runtime data. Subsequent `get()` calls will fall back to `DEFAULTS`.

```php
AppConfig::set('name', 'Custom Name');
AppConfig::forget('name');
AppConfig::get('name'); // 'My Application' (default restored)
```

#### `clear(?string $name = null): void`
Empties all runtime data in the configuration. For child configs, clears only that child's section.

```php
AppConfig::clear();
AppConfig::getData(); // []
```

---

### Bulk Data & Reference Operations

#### `getData(bool $default = false): array|bool`
Returns the entire runtime data array (or the child's partitioned section).

```php
$data = AppConfig::getData();
// ['name' => 'My Application', 'debug' => false, ...]
```

#### `&getDataByRef(): array`
Returns a **direct reference** to the internal configuration array, allowing in-place mutation:

```php
$ref = &AppConfig::getDataByRef();
$ref['name'] = 'Mutated Directly';

AppConfig::get('name'); // 'Mutated Directly'
```

#### `setData(?array $newData = null, bool $save = false): bool`
Replaces the entire runtime data array. Pass `save: true` to persist immediately to disk.

```php
AppConfig::setData(['name' => 'Brand New App', 'debug' => true]);
AppConfig::setData(['name' => 'Persisted App'], save: true); // immediately writes to disk
```

---

### File Persistence & Lifecycle

#### `save(): bool`
Writes runtime configuration to disk using `flock(LOCK_EX)` for atomic concurrency. Sections with `null` or empty values are automatically filtered out. Child configs delegate to their parent.

```php
AppConfig::set('env', 'staging');
AppConfig::save(); // writes to config/app.php
```

#### `load(): bool`
Loads data from the file into memory. Called automatically on first access.

```php
AppConfig::load();
```

#### `reload(): bool`
Re-reads the file from disk, discarding any unsaved runtime mutations.

```php
AppConfig::reload();
```

#### `reloadAllLoadedClasses(): array`
Reloads all Confix classes that have been accessed during the current request. Returns an array of `[ClassName => bool]`.

```php
$results = Confix::reloadAllLoadedClasses();
// [
//     'App\Config\AppConfig'   => true,
//     'App\Config\CacheConfig' => true,
// ]
```

#### `remove(): bool`
Deletes the configuration file from disk.

```php
AppConfig::remove(); // deletes config/app.php
```

---

### Overridable Hooks

You can customize Confix by overriding these protected static methods in your base class:

| Method | Return Type | Description |
|---|---|---|
| `getDirectory()` | `string` | Returns the directory path where config files reside. |
| `getFilePath()` | `string` | Returns the absolute path to the config file (`directory/name.php`). |
| `validate(string $name, mixed $value)` | `bool` | Custom validation rule; return `false` to reject storage. |
| `varExport(mixed $var)` | `string` | Serializes data to PHP code (override to use e.g. `VarExporter`). |
| `mkdir(string $path)` | `bool` | Creates directory (override to integrate custom filesystem). |
| `filePutContents(string $file, string $content)` | `bool` | Writes file content (override for custom file writers). |
| `mergeDefaults(array $data)` | `array` | Merges defaults with runtime data (`recursive` if `NESTED`, `flat` otherwise). |

---

## Running Tests

Confix is thoroughly tested with [Pest PHP](https://pestphp.com/). Run the test suite with:

```bash
composer test
```

Or run Pest directly:

```bash
./vendor/bin/pest
```

---

## 📜 License

Created with ❤️ by **Hadi Akbarzadeh** ([Nabeghe](https://github.com/nabeghe)).  
Licensed under the [MIT License](LICENSE.md). Free for personal and commercial use.
