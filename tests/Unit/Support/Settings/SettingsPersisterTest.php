<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Attributes\SettingProperty;
use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\Exceptions\InvalidSettingValueException;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Support\Settings\SettingsPersister;
use GaiaTools\FulcrumSettings\Support\TypeRegistry;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->typeRegistry = app(TypeRegistry::class);
    $this->persister = new SettingsPersister($this->typeRegistry);
});

test('persist updates setting value', function () {
    $setting = Setting::create([
        'key' => 'persist.test',
        'type' => SettingType::STRING,
    ]);

    $this->persister->persist('persist.test', 'new-value');

    expect($setting->refresh()->getDefaultValue())->toBe('new-value');
});

test('persist encrypts masked setting', function () {
    $setting = Setting::create([
        'key' => 'persist.masked',
        'type' => SettingType::STRING,
        'masked' => true,
    ]);

    $this->persister->persist('persist.masked', 'secret-value');

    $storedValue = $setting->refresh()->defaultValue->getAttributes()['value'];
    expect(Crypt::decryptString($storedValue))->toBe('secret-value');
});

test('persist throws exception for invalid value', function () {
    Setting::create([
        'key' => 'persist.int',
        'type' => SettingType::INTEGER,
    ]);

    expect(fn () => $this->persister->persist('persist.int', 'not-an-int'))
        ->toThrow(InvalidSettingValueException::class);
});

test('persist throws exception if setting not found', function () {
    expect(fn () => $this->persister->persist('non.existent', 'value'))
        ->toThrow(ModelNotFoundException::class);
});

test('validateWithRules validates data with rules', function () {
    $properties = [
        'email' => new SettingProperty(key: 'user.email', rules: ['required', 'email']),
    ];

    expect(fn () => $this->persister->validateWithRules(['email' => 'invalid'], $properties))
        ->toThrow(ValidationException::class);

    $this->persister->validateWithRules(['email' => 'test@example.com'], $properties);
});

test('validateWithRules does nothing if no rules are provided', function () {
    $properties = [
        'name' => new SettingProperty(key: 'user.name'),
    ];

    // Should not throw any exception
    $this->persister->validateWithRules(['name' => 'John'], $properties);
    expect(true)->toBeTrue();
});

test('validateWithRules does nothing if properties array is empty', function () {
    $this->persister->validateWithRules([], []);
    expect(true)->toBeTrue();
});
