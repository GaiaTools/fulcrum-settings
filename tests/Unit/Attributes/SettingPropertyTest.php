<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Attributes\SettingProperty;

it('can be instantiated with all properties', function () {
    $attribute = new SettingProperty(
        key: 'test.key',
        default: 'default_value',
        rules: ['required', 'string'],
        readOnly: true,
        lazy: true,
        cast: 'json',
        tenantScoped: true,
    );

    expect($attribute->key)->toBe('test.key')
        ->and($attribute->default)->toBe('default_value')
        ->and($attribute->rules)->toBe(['required', 'string'])
        ->and($attribute->readOnly)->toBeTrue()
        ->and($attribute->lazy)->toBeTrue()
        ->and($attribute->cast)->toBe('json')
        ->and($attribute->tenantScoped)->toBeTrue();
});

it('has default values', function () {
    $attribute = new SettingProperty(key: 'test.key');

    expect($attribute->key)->toBe('test.key')
        ->and($attribute->default)->toBeNull()
        ->and($attribute->rules)->toBe([])
        ->and($attribute->readOnly)->toBeFalse()
        ->and($attribute->lazy)->toBeFalse()
        ->and($attribute->cast)->toBeNull()
        ->and($attribute->tenantScoped)->toBeFalse();
});
