<?php

use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Support\Builders\RuleBuilder;
use GaiaTools\FulcrumSettings\Support\Builders\RuleConditionBuilder;

test('it can set name', function () {
    $builder = new RuleBuilder;
    $builder->name('Test Rule');

    $setting = Setting::create(['key' => 'test', 'type' => SettingType::STRING]);
    $rule = $builder->createFor($setting);

    expect($rule->name)->toBe('Test Rule');
});

test('it can set priority', function () {
    $builder = new RuleBuilder;
    $builder->priority(10);

    $setting = Setting::create(['key' => 'test', 'type' => SettingType::STRING]);
    $rule = $builder->createFor($setting);

    expect($rule->priority)->toBe(10);
});

test('it can set value with then', function () {
    $builder = new RuleBuilder;
    $builder->then('some value');

    $setting = Setting::create(['key' => 'test', 'type' => SettingType::STRING]);
    $rule = $builder->createFor($setting);

    expect($rule->getValue())->toBe('some value');
});

test('it cannot set value if rollout is already set', function () {
    $builder = new RuleBuilder;
    $builder->rollout(fn ($r) => $r->variant('v1', 100));

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Cannot set a direct value on a rule with rollout variants');
    $builder->then('value');
});

test('it cannot set rollout if value is already set', function () {
    $builder = new RuleBuilder;
    $builder->then('value');

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Cannot add rollout variants to a rule with a direct value');
    $builder->rollout(fn ($r) => $r->variant('v1', 100));
});

test('it can set custom salt', function () {
    $builder = new RuleBuilder;
    $builder->rollout(fn ($r) => $r->variant('v1', 100))->salt('custom-salt');

    $setting = Setting::create(['key' => 'test', 'type' => SettingType::STRING]);
    $rule = $builder->createFor($setting);

    expect($rule->rollout_salt)->toBe('custom-salt');
});

test('it can add conditions with when', function () {
    $builder = new RuleBuilder;
    $builder->when('attr', 'equals', 'val');

    $setting = Setting::create(['key' => 'test', 'type' => SettingType::STRING]);
    $rule = $builder->createFor($setting);

    expect($rule->conditions)->toHaveCount(1);
    expect($rule->conditions->first()->attribute)->toBe('attr');
});

test('it can add conditions with closure', function () {
    $builder = new RuleBuilder;
    $builder->conditions(function (RuleConditionBuilder $conditions) {
        $conditions->where('attr', 'equals', 'val');
    });

    $setting = Setting::create(['key' => 'test', 'type' => SettingType::STRING]);
    $rule = $builder->createFor($setting);

    expect($rule->conditions)->toHaveCount(1);
    expect($rule->conditions->first()->attribute)->toBe('attr');
});

test('it can check if it is rollout', function () {
    $builder = new RuleBuilder;
    expect($builder->isRollout())->toBeFalse();

    $builder->rollout(fn ($r) => $r->variant('v1', 100));
    expect($builder->isRollout())->toBeTrue();
});
