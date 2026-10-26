<?php

use Tests\Support\SimpleAppConfig;
use Tests\Support\ValidatedServerConfig;

beforeEach(function () {
    cleanTestConfigDir();
    ValidatedServerConfig::clear();
    SimpleAppConfig::clear();
});

afterEach(function () {
    ValidatedServerConfig::clear();
    SimpleAppConfig::clear();
    cleanTestConfigDir();
});

test('accepts valid values when validation passes', function () {
    expect(ValidatedServerConfig::set('port', 443))->toBeTrue()
        ->and(ValidatedServerConfig::get('port'))->toBe(443);

    expect(ValidatedServerConfig::set('host', '10.0.0.1'))->toBeTrue()
        ->and(ValidatedServerConfig::get('host'))->toBe('10.0.0.1');
});

test('rejects invalid values when validation fails and keeps previous value', function () {
    // Initial valid value
    ValidatedServerConfig::set('port', 8080);
    expect(ValidatedServerConfig::get('port'))->toBe(8080);

    // Negative port rejected
    expect(ValidatedServerConfig::set('port', -1))->toBeFalse()
        ->and(ValidatedServerConfig::get('port'))->toBe(8080);

    // Out of range port rejected
    expect(ValidatedServerConfig::set('port', 70000))->toBeFalse()
        ->and(ValidatedServerConfig::get('port'))->toBe(8080);

    // Non-integer port rejected
    expect(ValidatedServerConfig::set('port', 'not-a-port'))->toBeFalse()
        ->and(ValidatedServerConfig::get('port'))->toBe(8080);

    // Invalid IP rejected
    expect(ValidatedServerConfig::set('host', 'not.an.ip.address'))->toBeFalse()
        ->and(ValidatedServerConfig::get('host'))->toBe('127.0.0.1'); // default
});

test('bypasses validation when validate parameter is set to false', function () {
    // With validate = false, invalid values are accepted
    expect(ValidatedServerConfig::set('port', -999, false))->toBeTrue()
        ->and(ValidatedServerConfig::get('port'))->toBe(-999);

    expect(ValidatedServerConfig::set('host', 'invalid-domain-name', false))->toBeTrue()
        ->and(ValidatedServerConfig::get('host'))->toBe('invalid-domain-name');
});

test('unvalidated keys in ValidatedServerConfig default to valid', function () {
    expect(ValidatedServerConfig::set('unrestricted_key', 'anything'))->toBeTrue()
        ->and(ValidatedServerConfig::get('unrestricted_key'))->toBe('anything');
});

test('default base validation on Confix accepts any key and value', function () {
    // SimpleAppConfig does not override validate()
    expect(SimpleAppConfig::set('arbitrary', ['complex' => 'structure']))->toBeTrue()
        ->and(SimpleAppConfig::get('arbitrary'))->toBe(['complex' => 'structure']);
});
