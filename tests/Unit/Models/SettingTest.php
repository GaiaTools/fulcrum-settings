<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\Exceptions\ImmutableSettingException;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use GaiaTools\FulcrumSettings\Support\MaskedValue;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;

test('setting can be created', function () {
    $setting = Setting::create([
        'key' => 'test.setting',
        'type' => SettingType::STRING,
        'description' => 'A test setting',
    ]);

    expect($setting->key)->toBe('test.setting')
        ->and($setting->type)->toBe(SettingType::STRING)
        ->and($setting->description)->toBe('A test setting');
});

test('setting immutability prevents updates', function () {
    $setting = Setting::create([
        'key' => 'immutable.setting',
        'type' => SettingType::STRING,
        'immutable' => true,
    ]);

    expect(fn () => $setting->update(['description' => 'new description']))
        ->toThrow(ImmutableSettingException::class);
});

test('setting immutability can be bypassed with force', function () {
    $setting = Setting::create([
        'key' => 'immutable.setting',
        'type' => SettingType::STRING,
        'immutable' => true,
    ]);

    FulcrumContext::force(true);
    $setting->update(['description' => 'new description']);
    FulcrumContext::force(false);

    expect($setting->fresh()->description)->toBe('new description');
});

test('setting immutability prevents deletion', function () {
    Config::set('fulcrum.immutability.allow_delete_via_gate', false);

    $setting = Setting::create([
        'key' => 'immutable.setting',
        'type' => SettingType::STRING,
        'immutable' => true,
    ]);

    expect(fn () => $setting->delete())
        ->toThrow(ImmutableSettingException::class);
});

test('setting immutability deletion can be bypassed with force', function () {
    $setting = Setting::create([
        'key' => 'immutable.setting',
        'type' => SettingType::STRING,
        'immutable' => true,
    ]);

    FulcrumContext::force(true);
    $setting->delete();
    FulcrumContext::force(false);

    expect(Setting::find($setting->id))->toBeNull();
});

test('setting can have rules', function () {
    $setting = Setting::create(['key' => 'rules.test', 'type' => SettingType::STRING]);
    $setting->rules()->create(['priority' => 1]);

    expect($setting->rules)->toHaveCount(1);
});

test('setting can have default value', function () {
    $setting = Setting::create(['key' => 'default.test', 'type' => SettingType::STRING]);
    $setting->defaultValue()->create([
        'valuable_type' => Setting::class,
        'valuable_id' => $setting->id,
        'value' => 'my-default',
    ]);

    expect($setting->defaultValue->value)->toBe('my-default');
});

test('get default value returns null when no default value exists', function () {
    $setting = Setting::create(['key' => 'no.default', 'type' => SettingType::STRING]);
    expect($setting->getDefaultValue())->toBeNull();
});

test('get default value returns casted value', function () {
    $setting = Setting::create(['key' => 'cast.test', 'type' => SettingType::INTEGER]);
    $setting->defaultValue()->create([
        'valuable_type' => Setting::class,
        'valuable_id' => $setting->id,
        'value' => '123',
    ]);

    expect($setting->getDefaultValue())->toBe(123);
});

test('get default value returns masked value when configured and denied', function () {
    Config::set('fulcrum.masking.mask_in_resolver', true);
    Config::set('fulcrum.masking.placeholder', 'HIDDEN');

    $setting = Setting::create([
        'key' => 'masked.test',
        'type' => SettingType::STRING,
        'masked' => true,
    ]);
    $setting->defaultValue()->create([
        'valuable_type' => Setting::class,
        'valuable_id' => $setting->id,
        'value' => 'secret',
    ]);

    Gate::define('viewSettingValue', function () {
        return false;
    });

    $value = $setting->getDefaultValue();
    expect($value)->toBeInstanceOf(MaskedValue::class)
        ->and((string) $value)->toBe('HIDDEN');
});

test('get default value returns decrypted value when configured and allowed', function () {
    Config::set('fulcrum.masking.mask_in_resolver', true);

    $setting = Setting::create([
        'key' => 'allowed.test',
        'type' => SettingType::STRING,
        'masked' => true,
    ]);
    $setting->defaultValue()->create([
        'valuable_type' => Setting::class,
        'valuable_id' => $setting->id,
        'value' => 'secret',
    ]);

    Gate::define('viewSettingValue', function ($user = null, $model = null) {
        return true;
    });

    FulcrumContext::reveal(true);
    expect($setting->fresh()->getDefaultValue())->toBe('secret');
    FulcrumContext::reveal(false);
});

test('deletion can be allowed via gate for immutable settings', function () {
    Config::set('fulcrum.immutability.allow_delete_via_gate', true);
    Config::set('fulcrum.immutability.delete_ability', 'delete-it');

    $setting = Setting::create([
        'key' => 'gate.immutable',
        'type' => SettingType::STRING,
        'immutable' => true,
    ]);

    Gate::define('delete-it', function ($user = null, $model = null) {
        return true;
    });

    $setting->delete();
    expect(Setting::find($setting->id))->toBeNull();
});

test('setting immutability allows deletion for non-immutable settings', function () {
    $setting = Setting::create([
        'key' => 'non.immutable',
        'type' => SettingType::STRING,
        'immutable' => false,
    ]);

    $setting->delete();
    expect(Setting::find($setting->id))->toBeNull();
});
