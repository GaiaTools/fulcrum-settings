<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Models\SettingRule;
use GaiaTools\FulcrumSettings\Models\SettingRuleRolloutVariant;
use GaiaTools\FulcrumSettings\Support\Builders\RolloutBuilder;

test('it can add variants', function () {
    $builder = new RolloutBuilder;
    $builder->variant('v1', 25.5, 'val1');
    $builder->variant('v2', 50, 'val2');

    $variants = $builder->getVariants();
    expect($variants)->toHaveCount(2)
        ->and($variants[0]['name'])->toBe('v1')
        ->and($variants[0]['weight'])->toBe(25500)
        ->and($variants[0]['value'])->toBe('val1')
        ->and($variants[1]['name'])->toBe('v2')
        ->and($variants[1]['weight'])->toBe(50000)
        ->and($variants[1]['value'])->toBe('val2');
});

test('it throws exception for invalid weights', function ($weight) {
    $builder = new RolloutBuilder;
    expect(fn () => $builder->variant('v1', $weight))
        ->toThrow(InvalidArgumentException::class, 'Variant weight must be between 0 and 100');
})->with([-0.1, 100.1]);

test('it throws exception for duplicate variant names', function () {
    $builder = new RolloutBuilder;
    $builder->variant('v1', 10);
    expect(fn () => $builder->variant('v1', 20))
        ->toThrow(InvalidArgumentException::class, 'Duplicate variant name: v1');
});

test('it calculates total weight and percentage', function () {
    $builder = new RolloutBuilder;
    $builder->variant('v1', 10.5);
    $builder->variant('v2', 20);

    expect($builder->getTotalWeight())->toBe(30500)
        ->and($builder->getTotalPercentage())->toBe(30.5);
});

test('it detects when total weight exceeds max weight', function () {
    $builder = new RolloutBuilder;
    $builder->variant('v1', 60);
    expect($builder->exceedsMaxWeight())->toBeFalse();

    $builder->variant('v2', 40.1);
    expect($builder->exceedsMaxWeight())->toBeTrue();
});

test('validate throws exception for empty variants', function () {
    $builder = new RolloutBuilder;
    expect(fn () => $builder->validate())
        ->toThrow(InvalidArgumentException::class, 'At least one variant must be defined');
});

test('validate throws exception when sum of weights exceeds 100%', function () {
    $builder = new RolloutBuilder;
    $builder->variant('v1', 60);
    $builder->variant('v2', 41);

    expect(fn () => $builder->validate())
        ->toThrow(InvalidArgumentException::class, 'Total variant weight (101.000%) exceeds 100%');
});

test('it can create models for a rule', function () {
    $setting = Setting::create([
        'key' => 'test',
        'type' => SettingType::STRING,
    ]);

    $rule = SettingRule::create([
        'setting_id' => $setting->id,
        'priority' => 1,
    ]);

    $builder = new RolloutBuilder;
    $builder->variant('v1', 25, 'val1');
    $builder->variant('v2', 75);

    $created = $builder->createFor($rule);

    expect($created)->toHaveCount(2)
        ->and($created[0])->toBeInstanceOf(SettingRuleRolloutVariant::class)
        ->and($created[0]->name)->toBe('v1')
        ->and($created[0]->weight)->toBe(25000)
        ->and($created[0]->getValue())->toBe('val1')
        ->and($created[1]->name)->toBe('v2')
        ->and($created[1]->weight)->toBe(75000)
        ->and($created[1]->getValue())->toBeNull();
});

test('it can reset its state', function () {
    $builder = new RolloutBuilder;
    $builder->variant('v1', 50);
    expect($builder->getVariants())->toHaveCount(1);

    $builder->reset();
    expect($builder->getVariants())->toHaveCount(0);
});
