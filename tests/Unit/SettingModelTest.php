<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Models\SettingRule;
use GaiaTools\FulcrumSettings\Models\SettingValue;

describe('Setting Model', function () {
    it('can create a setting with basic attributes', function () {
        $setting = Setting::create([
            'key' => 'test-setting',
            'type' => SettingType::BOOLEAN,
            'description' => 'A test setting',
        ]);

        expect($setting->key)->toBe('test-setting');
        expect($setting->group)->toBeNull();
        expect($setting->type)->toBe(SettingType::BOOLEAN);
        expect($setting->description)->toBe('A test setting');
    });

    it('casts type to SettingType enum', function () {
        $setting = Setting::create([
            'key' => 'test-setting',
            'type' => SettingType::STRING,
        ]);

        expect($setting->type)->toBeInstanceOf(SettingType::class);
        expect($setting->type)->toBe(SettingType::STRING);
    });

    it('derives group from key', function () {
        $setting = Setting::create([
            'key' => 'services.someService.access_token',
            'type' => SettingType::STRING,
        ]);

        expect($setting->group)->toBe('services.someService');
    });

    it('has many rules', function () {
        $setting = Setting::create([
            'key' => 'test-setting',
            'type' => SettingType::BOOLEAN,
        ]);

        $rule1 = $setting->rules()->create(['priority' => 1]);
        $rule2 = $setting->rules()->create(['priority' => 2]);

        expect($setting->rules)->toHaveCount(2);
        expect($setting->rules->first())->toBeInstanceOf(SettingRule::class);
    });

    it('has one default value through polymorphic relationship', function () {
        $setting = Setting::create([
            'key' => 'test-setting',
            'type' => SettingType::BOOLEAN,
        ]);

        $setting->defaultValue()->create([
            'valuable_type' => Setting::class,
            'valuable_id' => $setting->id,
            'value' => true,
        ]);

        expect($setting->defaultValue)->toBeInstanceOf(SettingValue::class);
        expect($setting->getDefaultValue())->toBe(true);
    });

    it('gets default value correctly', function () {
        $setting = Setting::create([
            'key' => 'test-setting',
            'type' => SettingType::BOOLEAN,
        ]);

        $setting->defaultValue()->create([
            'valuable_type' => Setting::class,
            'valuable_id' => $setting->id,
            'value' => true,
        ]);

        expect($setting->getDefaultValue())->toBe(true);
    });

    it('returns null when no default value exists', function () {
        $setting = Setting::create([
            'key' => 'test-setting',
            'type' => SettingType::BOOLEAN,
        ]);

        expect($setting->getDefaultValue())->toBeNull();
    });

    it('casts boolean values correctly', function () {
        $setting = Setting::create([
            'key' => 'test-setting',
            'type' => SettingType::BOOLEAN,
        ]);

        $valueModel = new SettingValue([
            'valuable_type' => Setting::class,
            'valuable_id' => $setting->id,
        ]);

        $valueModel->value = 1;
        expect($valueModel->value)->toBe(true);

        $valueModel->value = 0;
        expect($valueModel->value)->toBe(false);

        $valueModel->value = 'true';
        expect($valueModel->value)->toBe(true);

        $valueModel->value = null;
        expect($valueModel->value)->toBeNull();
    });

    it('casts integer values correctly', function () {
        $setting = Setting::create([
            'key' => 'test-setting',
            'type' => SettingType::INTEGER,
        ]);

        $valueModel = new SettingValue([
            'valuable_type' => Setting::class,
            'valuable_id' => $setting->id,
        ]);

        $valueModel->value = '42';
        expect($valueModel->value)->toBe(42);

        $valueModel->value = 42.7;
        expect($valueModel->value)->toBe(42);

        $valueModel->value = '0';
        expect($valueModel->value)->toBe(0);
    });

    it('casts float values correctly', function () {
        $setting = Setting::create([
            'key' => 'test-setting',
            'type' => SettingType::FLOAT,
        ]);

        $valueModel = new SettingValue([
            'valuable_type' => Setting::class,
            'valuable_id' => $setting->id,
        ]);

        $valueModel->value = '42.5';
        expect($valueModel->value)->toBe(42.5);

        $valueModel->value = 42;
        expect($valueModel->value)->toBe(42.0);

        $valueModel->value = '0.1';
        expect($valueModel->value)->toBe(0.1);
    });

    it('casts string values correctly', function () {
        $setting = Setting::create([
            'key' => 'test-setting',
            'type' => SettingType::STRING,
        ]);

        $valueModel = new SettingValue([
            'valuable_type' => Setting::class,
            'valuable_id' => $setting->id,
        ]);

        $valueModel->value = 42;
        expect($valueModel->value)->toBe('42');

        $valueModel->value = true;
        expect($valueModel->value)->toBe('1');

        $valueModel->value = 'hello';
        expect($valueModel->value)->toBe('hello');
    });

    it('casts JSON values correctly', function () {
        $setting = Setting::create([
            'key' => 'test-setting',
            'type' => SettingType::JSON,
        ]);

        $valueModel = new SettingValue([
            'valuable_type' => Setting::class,
            'valuable_id' => $setting->id,
        ]);

        $data = ['key' => 'value', 'number' => 42];

        $valueModel->value = $data;
        expect($valueModel->value)->toBe($data);
    });

    it('handles JSON values that are already decoded', function () {
        $setting = Setting::create([
            'key' => 'test-setting',
            'type' => SettingType::JSON,
        ]);

        $valueModel = new SettingValue([
            'valuable_type' => Setting::class,
            'valuable_id' => $setting->id,
        ]);

        $data = ['key' => 'value', 'number' => 42];

        $valueModel->value = $data;
        expect($valueModel->value)->toBe($data);
    });
});
