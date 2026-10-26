<?php

use Tests\Support\SimpleAppConfig;

beforeEach(function () {
    cleanTestConfigDir();
    SimpleAppConfig::clear();
});

afterEach(function () {
    SimpleAppConfig::clear();
    cleanTestConfigDir();
});

test('retrieves default values correctly', function () {
    expect(SimpleAppConfig::getDefault('name'))->toBe('My Application')
        ->and(SimpleAppConfig::getDefault('debug'))->toBeFalse()
        ->and(SimpleAppConfig::getDefault('version'))->toBe('1.0.0')
        ->and(SimpleAppConfig::getDefault('non_existing'))->toBeNull();

    expect(SimpleAppConfig::hasDefault('name'))->toBeTrue()
        ->and(SimpleAppConfig::hasDefault('debug'))->toBeTrue()
        ->and(SimpleAppConfig::hasDefault('non_existing'))->toBeFalse();
});

test('get and has fall back to defaults when key is not set in runtime', function () {
    expect(SimpleAppConfig::get('name'))->toBe('My Application')
        ->and(SimpleAppConfig::get('debug'))->toBeFalse()
        ->and(SimpleAppConfig::get('version'))->toBe('1.0.0')
        ->and(SimpleAppConfig::get('non_existing'))->toBeNull();

    expect(SimpleAppConfig::has('name'))->toBeTrue()
        ->and(SimpleAppConfig::has('debug'))->toBeTrue()
        ->and(SimpleAppConfig::has('non_existing'))->toBeFalse();
});

test('set stores values in runtime and get retrieves them', function () {
    expect(SimpleAppConfig::set('name', 'Custom Name'))->toBeTrue()
        ->and(SimpleAppConfig::set('debug', true))->toBeTrue()
        ->and(SimpleAppConfig::set('new_key', 'hello world'))->toBeTrue();

    expect(SimpleAppConfig::get('name'))->toBe('Custom Name')
        ->and(SimpleAppConfig::get('debug'))->toBeTrue()
        ->and(SimpleAppConfig::get('new_key'))->toBe('hello world');

    expect(SimpleAppConfig::has('new_key'))->toBeTrue();
});

test('setting value to null unsets it from runtime data and falls back to default', function () {
    SimpleAppConfig::set('name', 'Temporary Name');
    expect(SimpleAppConfig::get('name'))->toBe('Temporary Name');

    // Setting to null should remove the runtime key
    expect(SimpleAppConfig::set('name', null))->toBeTrue();
    expect(SimpleAppConfig::get('name'))->toBe('My Application');

    // Setting a key without default to null
    SimpleAppConfig::set('custom', 'val');
    SimpleAppConfig::set('custom', null);
    expect(SimpleAppConfig::get('custom'))->toBeNull()
        ->and(SimpleAppConfig::has('custom'))->toBeFalse();
});

test('forget removes key from runtime data and falls back to default', function () {
    SimpleAppConfig::set('name', 'Changed Name');
    SimpleAppConfig::set('temp_key', 12345);

    SimpleAppConfig::forget('name');
    expect(SimpleAppConfig::get('name'))->toBe('My Application');

    SimpleAppConfig::forget('temp_key');
    expect(SimpleAppConfig::get('temp_key'))->toBeNull()
        ->and(SimpleAppConfig::has('temp_key'))->toBeFalse();

    // Calling forget on a non-existent key does not error
    SimpleAppConfig::forget('non_existent_key');
    expect(SimpleAppConfig::get('non_existent_key'))->toBeNull();
});

test('clear resets all runtime data to empty array', function () {
    SimpleAppConfig::set('name', 'Custom');
    SimpleAppConfig::set('debug', true);
    SimpleAppConfig::set('extra', 'val');

    SimpleAppConfig::clear();

    // Custom runtime keys are gone
    expect(SimpleAppConfig::get('extra'))->toBeNull();
    // Default keys still fall back to their defaults
    expect(SimpleAppConfig::get('name'))->toBe('My Application');
    expect(SimpleAppConfig::get('debug'))->toBeFalse();
});

test('magic __callStatic handles get, set and forget dynamically', function () {
    // Getter call
    expect(SimpleAppConfig::name())->toBe('My Application');
    expect(SimpleAppConfig::debug())->toBeFalse();

    // Setter call
    expect(SimpleAppConfig::name('Dynamic Name'))->toBeTrue();
    expect(SimpleAppConfig::name())->toBe('Dynamic Name');

    expect(SimpleAppConfig::debug(true))->toBeTrue();
    expect(SimpleAppConfig::debug())->toBeTrue();

    expect(SimpleAppConfig::dynamicKey('arbitrary value'))->toBeTrue();
    expect(SimpleAppConfig::dynamicKey())->toBe('arbitrary value');

    // Forget call by passing null
    expect(SimpleAppConfig::name(null))->toBeTrue();
    expect(SimpleAppConfig::name())->toBe('My Application');

    expect(SimpleAppConfig::dynamicKey(null))->toBeTrue();
    expect(SimpleAppConfig::dynamicKey())->toBeNull();
});

test('bulk data operations with getData, setData and getDataByRef', function () {
    // getData returns array
    $data = SimpleAppConfig::getData();
    expect($data)->toBeArray();

    // setData replaces data
    SimpleAppConfig::setData(['name' => 'Bulk App', 'custom' => 'bulk_val']);
    expect(SimpleAppConfig::get('name'))->toBe('Bulk App')
        ->and(SimpleAppConfig::get('custom'))->toBe('bulk_val')
        ->and(SimpleAppConfig::get('debug'))->toBeFalse(); // default still accessible

    // setData with null
    SimpleAppConfig::setData(null);
    expect(SimpleAppConfig::get('custom'))->toBeNull();

    // getDataByRef allows mutating internal data directly
    SimpleAppConfig::setData(['name' => 'Original']);
    $ref = &SimpleAppConfig::getDataByRef();
    $ref['name'] = 'Directly Mutated';
    $ref['injected'] = 999;

    expect(SimpleAppConfig::get('name'))->toBe('Directly Mutated')
        ->and(SimpleAppConfig::get('injected'))->toBe(999);
});
