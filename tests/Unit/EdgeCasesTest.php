<?php

use Tests\Support\AnotherAppConfig;
use Tests\Support\ChildWithUnderscoreConfig;
use Tests\Support\NestedInfraConfig;
use Tests\Support\SimpleAppConfig;

beforeEach(function () {
    cleanTestConfigDir();
    SimpleAppConfig::clear();
    AnotherAppConfig::clear();
    NestedInfraConfig::clear();
    ChildWithUnderscoreConfig::clear();
});

afterEach(function () {
    SimpleAppConfig::clear();
    AnotherAppConfig::clear();
    NestedInfraConfig::clear();
    ChildWithUnderscoreConfig::clear();
    cleanTestConfigDir();
});

test('distinct standalone classes remain completely isolated', function () {
    SimpleAppConfig::set('shared_name', 'Value In Simple');
    AnotherAppConfig::set('shared_name', 'Value In Another');

    expect(SimpleAppConfig::get('shared_name'))->toBe('Value In Simple')
        ->and(AnotherAppConfig::get('shared_name'))->toBe('Value In Another');

    SimpleAppConfig::clear();

    expect(SimpleAppConfig::get('shared_name'))->toBeNull()
        ->and(AnotherAppConfig::get('shared_name'))->toBe('Value In Another');
});

test('class with PARENT and own protected static array $_ maintains synchronization with parent', function () {
    // Specifically testing the combination of PARENT + own $_ property
    // 1. Initial defaults
    expect(ChildWithUnderscoreConfig::get('option_a'))->toBe('alpha');

    // 2. Setting values from child
    ChildWithUnderscoreConfig::set('option_a', 'updated_alpha');
    ChildWithUnderscoreConfig::set('extra_flag', true);

    // Verify child reads them
    expect(ChildWithUnderscoreConfig::get('option_a'))->toBe('updated_alpha')
        ->and(ChildWithUnderscoreConfig::get('extra_flag'))->toBeTrue();

    // Verify parent has them under child section
    $parentData = SimpleAppConfig::getData();
    expect($parentData)->toHaveKey('child_with_underscore')
        ->and($parentData['child_with_underscore']['option_a'])->toBe('updated_alpha')
        ->and($parentData['child_with_underscore']['extra_flag'])->toBeTrue();

    // 3. Mutating from parent reflects in child
    $parentRef = &SimpleAppConfig::getDataByRef();
    $parentRef['child_with_underscore']['option_a'] = 'mutated_from_parent';
    expect(ChildWithUnderscoreConfig::get('option_a'))->toBe('mutated_from_parent');

    // 4. Saving from child persists the parent file
    expect(ChildWithUnderscoreConfig::save())->toBeTrue();
    $filePath = SimpleAppConfig::callGetFilePath();
    expect(file_exists($filePath))->toBeTrue();

    // 5. Reload from child discards memory and restores file state
    ChildWithUnderscoreConfig::set('option_a', 'unsaved');
    expect(ChildWithUnderscoreConfig::reload())->toBeTrue();
    expect(ChildWithUnderscoreConfig::get('option_a'))->toBe('mutated_from_parent');

    // 6. Removing from child deletes parent file
    expect(ChildWithUnderscoreConfig::remove())->toBeTrue();
    expect(file_exists($filePath))->toBeFalse();
});

test('handles falsy and zero values correctly without treating them as null', function () {
    SimpleAppConfig::set('is_false', false);
    SimpleAppConfig::set('zero_int', 0);
    SimpleAppConfig::set('zero_float', 0.0);
    SimpleAppConfig::set('empty_str', '');

    expect(SimpleAppConfig::has('is_false'))->toBeTrue()
        ->and(SimpleAppConfig::has('zero_int'))->toBeTrue()
        ->and(SimpleAppConfig::has('zero_float'))->toBeTrue()
        ->and(SimpleAppConfig::has('empty_str'))->toBeTrue();

    expect(SimpleAppConfig::get('is_false'))->toBeFalse()
        ->and(SimpleAppConfig::get('zero_int'))->toBe(0)
        ->and(SimpleAppConfig::get('zero_float'))->toBe(0.0)
        ->and(SimpleAppConfig::get('empty_str'))->toBe('');
});

test('handles complex data types including arrays and objects', function () {
    $object = (object) ['name' => 'John', 'role' => 'admin'];
    $dateTime = new DateTimeImmutable('2026-01-01 12:00:00');
    $array = [1, 2, [3, 4]];

    SimpleAppConfig::set('user_obj', $object);
    SimpleAppConfig::set('date', $dateTime);
    SimpleAppConfig::set('list', $array);

    expect(SimpleAppConfig::get('user_obj'))->toBe($object)
        ->and(SimpleAppConfig::get('date'))->toBe($dateTime)
        ->and(SimpleAppConfig::get('list'))->toBe($array);
});

test('handles keys with special characters like hyphens, underscores and slashes', function () {
    SimpleAppConfig::set('app-name-kebab', 'kebab-value');
    SimpleAppConfig::set('app/path/key', 'path-value');
    SimpleAppConfig::set('app:colon:key', 'colon-value');

    expect(SimpleAppConfig::get('app-name-kebab'))->toBe('kebab-value')
        ->and(SimpleAppConfig::get('app/path/key'))->toBe('path-value')
        ->and(SimpleAppConfig::get('app:colon:key'))->toBe('colon-value');

    SimpleAppConfig::forget('app-name-kebab');
    expect(SimpleAppConfig::get('app-name-kebab'))->toBeNull();
});

test('calling forget on empty data or non-existent keys does not trigger warnings', function () {
    SimpleAppConfig::clear();

    // No error on non-existent flat key
    SimpleAppConfig::forget('non_existent');
    expect(SimpleAppConfig::get('non_existent'))->toBeNull();

    // No error on non-existent nested key
    NestedInfraConfig::clear();
    NestedInfraConfig::forget('a.b.c.d.e');
    expect(NestedInfraConfig::get('a.b.c.d.e'))->toBeNull();
});

test('consecutive saves and reloads maintain consistency', function () {
    for ($i = 1; $i <= 5; $i++) {
        SimpleAppConfig::set("iteration_{$i}", $i * 10);
        expect(SimpleAppConfig::save())->toBeTrue();
        expect(SimpleAppConfig::reload())->toBeTrue();
        expect(SimpleAppConfig::get("iteration_{$i}"))->toBe($i * 10);
    }
});
