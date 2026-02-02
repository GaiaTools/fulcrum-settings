<?php

use GaiaTools\FulcrumSettings\Enums\ComparisonOperator;
use GaiaTools\FulcrumSettings\Exceptions\InvalidSettingValueException;
use GaiaTools\FulcrumSettings\Exceptions\InvalidTypeHandlerException;
use GaiaTools\FulcrumSettings\Support\Builders\SettingBuilder;
// uses(\GaiaTools\FulcrumSettings\Tests\TestCase::class);

use GaiaTools\FulcrumSettings\Support\FulcrumContext;

test('it can define a setting with basic properties', function () {
    FulcrumContext::force();
    $builder = SettingBuilder::define('site_name')
        ->type('string')
        ->default('My App')
        ->description('The name of the site')
        ->masked()
        ->immutable();

    $setting = $builder->save();
    FulcrumContext::force(false);

    expect($setting->key)->toBe('site_name')
        ->and($setting->type->value)->toBe('string')
        ->and($setting->description)->toBe('The name of the site')
        ->and($setting->masked)->toBeTrue()
        ->and($setting->immutable)->toBeTrue();

    expect($setting->defaultValue->value)->toBe('My App');
});

test('it can define a setting with rules', function () {
    FulcrumContext::force();
    $builder = SettingBuilder::define('theme')
        ->type('string')
        ->default('light')
        ->rule(function ($rule) {
            $rule->name('Dark mode for VIPs')
                ->when('user.is_vip', ComparisonOperator::EQUALS, true)
                ->then('dark')
                ->priority(1);
        });

    $setting = $builder->save();
    FulcrumContext::force(false);

    expect($setting->rules)->toHaveCount(1);
    $rule = $setting->rules->first();
    expect($rule->name)->toBe('Dark mode for VIPs')
        ->and($rule->priority)->toBe(1);

    expect($rule->conditions)->toHaveCount(1);
    $condition = $rule->conditions->first();
    expect($condition->attribute)->toBe('user.is_vip')
        ->and($condition->operator->value)->toBe('equals')
        ->and($condition->value)->toBe(true);

    expect($rule->value->value)->toBe('dark');
});

test('it throws exception for invalid type', function () {
    expect(fn () => SettingBuilder::define('test')->type('invalid_type'))
        ->toThrow(InvalidTypeHandlerException::class);
});

test('it throws exception for invalid default value', function () {
    expect(fn () => SettingBuilder::define('test')->type('integer')->default('not an integer')->save())
        ->toThrow(InvalidSettingValueException::class);
});

test('it handles BackedEnum for type', function () {
    enum TestType: string
    {
        case STRING = 'string';
    }

    $builder = SettingBuilder::define('test')->type(TestType::STRING);
    $setting = $builder->save();

    expect($setting->type->value)->toBe('string');
});
