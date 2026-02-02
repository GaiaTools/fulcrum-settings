<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Enums\ComparisonOperator;
use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\Support\Builders\SettingBuilder;

test('setting builder can create a setting with rules', function () {
    $setting = SettingBuilder::define('test.builder')
        ->type(SettingType::STRING)
        ->default('default')
        ->description('Test description')
        ->rule(function ($rule) {
            $rule->name('Premium Users')
                ->priority(1)
                ->when('user.premium', ComparisonOperator::IS_TRUE)
                ->then('premium-value');
        })
        ->save();

    expect($setting->key)->toBe('test.builder')
        ->and($setting->type)->toBe(SettingType::STRING)
        ->and($setting->description)->toBe('Test description')
        ->and($setting->getDefaultValue())->toBe('default');

    expect($setting->rules)->toHaveCount(1);
    $rule = $setting->rules->first();
    expect($rule->name)->toBe('Premium Users')
        ->and($rule->priority)->toBe(1)
        ->and($rule->getValue())->toBe('premium-value');

    expect($rule->conditions)->toHaveCount(1);
    $condition = $rule->conditions->first();
    expect($condition->attribute)->toBe('user.premium')
        ->and($condition->operator)->toBe(ComparisonOperator::IS_TRUE);
});

test('setting builder handles masked and immutable flags', function () {
    $setting = SettingBuilder::define('masked.immutable')
        ->masked()
        ->immutable()
        ->save();

    expect($setting->masked)->toBeTrue()
        ->and($setting->immutable)->toBeTrue();
});
