<?php

use Tests\Support\NestedInfraConfig;
use Tests\Support\SimpleAppConfig;

beforeEach(function () {
    cleanTestConfigDir();
    NestedInfraConfig::clear();
    SimpleAppConfig::clear();
});

afterEach(function () {
    NestedInfraConfig::clear();
    SimpleAppConfig::clear();
    cleanTestConfigDir();
});

test('retrieves nested defaults with dot-notation', function () {
    expect(NestedInfraConfig::getDefault('db.host'))->toBe('localhost')
        ->and(NestedInfraConfig::getDefault('db.port'))->toBe(3306)
        ->and(NestedInfraConfig::getDefault('db.credentials.user'))->toBe('root')
        ->and(NestedInfraConfig::getDefault('db.credentials.password'))->toBe('')
        ->and(NestedInfraConfig::getDefault('logging.level'))->toBe('debug')
        ->and(NestedInfraConfig::getDefault('db.non_existent'))->toBeNull()
        ->and(NestedInfraConfig::getDefault('non_existent.key'))->toBeNull();

    expect(NestedInfraConfig::hasDefault('db.host'))->toBeTrue()
        ->and(NestedInfraConfig::hasDefault('db.credentials.user'))->toBeTrue()
        ->and(NestedInfraConfig::hasDefault('db.unknown'))->toBeFalse();
});

test('reads nested values falling back to defaults', function () {
    expect(NestedInfraConfig::get('db.host'))->toBe('localhost')
        ->and(NestedInfraConfig::get('db.port'))->toBe(3306)
        ->and(NestedInfraConfig::get('db.credentials.user'))->toBe('root')
        ->and(NestedInfraConfig::has('db.host'))->toBeTrue()
        ->and(NestedInfraConfig::has('db.unknown'))->toBeFalse();

    // get with includeDefault = false returns null when not set in runtime
    expect(NestedInfraConfig::get('db.host', false))->toBeNull();
});

test('writes nested values using dot-notation without overwriting siblings', function () {
    expect(NestedInfraConfig::set('db.host', '192.168.1.50'))->toBeTrue();

    // db.host is updated, but db.port is still preserved
    expect(NestedInfraConfig::get('db.host'))->toBe('192.168.1.50')
        ->and(NestedInfraConfig::get('db.port'))->toBe(3306);

    // Deep write
    NestedInfraConfig::set('db.credentials.user', 'db_admin');
    expect(NestedInfraConfig::get('db.credentials.user'))->toBe('db_admin');

    // Intermediate arrays are created automatically for non-existent paths
    NestedInfraConfig::set('cache.clusters.primary.node', 'redis-01');
    expect(NestedInfraConfig::get('cache.clusters.primary.node'))->toBe('redis-01');

    $data = NestedInfraConfig::getData();
    expect($data['cache']['clusters']['primary']['node'])->toBe('redis-01');
});

test('setting nested value to null unsets the key', function () {
    NestedInfraConfig::set('db.host', 'custom-host');
    expect(NestedInfraConfig::get('db.host'))->toBe('custom-host');

    // Setting to null unsets it from runtime
    NestedInfraConfig::set('db.host', null);
    // Falls back to default
    expect(NestedInfraConfig::get('db.host'))->toBe('localhost');

    // Without default
    NestedInfraConfig::set('custom.nested.key', 'temporary');
    NestedInfraConfig::set('custom.nested.key', null);
    expect(NestedInfraConfig::get('custom.nested.key'))->toBeNull();
});

test('forget removes nested keys using dot-notation', function () {
    NestedInfraConfig::set('db.host', 'modified-host');
    NestedInfraConfig::set('custom.deep.item', 42);

    NestedInfraConfig::forget('db.host');
    expect(NestedInfraConfig::get('db.host'))->toBe('localhost'); // default

    NestedInfraConfig::forget('custom.deep.item');
    expect(NestedInfraConfig::get('custom.deep.item'))->toBeNull();

    // Calling forget on non-existent path or scalar intermediate does not error
    NestedInfraConfig::forget('non.existent.path');
    NestedInfraConfig::set('scalar_key', 'just a string');
    NestedInfraConfig::forget('scalar_key.child');
    expect(NestedInfraConfig::get('scalar_key'))->toBe('just a string');
});

test('gracefully handles non-array intermediate values when reading with dot-notation', function () {
    NestedInfraConfig::set('broken_tree', 'not an array');

    // Reading a child of a non-array returns null (or default if any)
    expect(NestedInfraConfig::get('broken_tree.child'))->toBeNull()
        ->and(NestedInfraConfig::has('broken_tree.child'))->toBeFalse();
});

test('when NESTED is false, dot-notation keys are treated as literal strings', function () {
    // SimpleAppConfig has NESTED = false
    SimpleAppConfig::set('literal.dot.key', 'literal_value');

    // Treated as a single literal string key in runtime data
    expect(SimpleAppConfig::get('literal.dot.key'))->toBe('literal_value');

    $data = SimpleAppConfig::getData();
    expect($data)->toHaveKey('literal.dot.key')
        ->and($data['literal.dot.key'])->toBe('literal_value');

    // forget removes the literal key
    SimpleAppConfig::forget('literal.dot.key');
    expect(SimpleAppConfig::get('literal.dot.key'))->toBeNull();
});
