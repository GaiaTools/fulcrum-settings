<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Events\VariantAssigned;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Models\SettingRule;
use GaiaTools\FulcrumSettings\Models\SettingRuleRolloutVariant;

test('variant assigned event stores properties correctly', function () {
    $setting = new Setting(['key' => 'test-setting']);
    $rule = new SettingRule(['name' => 'Test Rule']);
    $variant = new SettingRuleRolloutVariant(['name' => 'Control']);

    $event = new VariantAssigned(
        settingKey: 'test-setting',
        ruleName: 'Test Rule',
        variantName: 'Control',
        value: 'some-value',
        identifier: 'user-123',
        bucket: 500,
        setting: $setting,
        rule: $rule,
        variant: $variant,
        tenantId: 'tenant-1',
        context: ['extra' => 'data']
    );

    expect($event->settingKey)->toBe('test-setting')
        ->and($event->ruleName)->toBe('Test Rule')
        ->and($event->variantName)->toBe('Control')
        ->and($event->value)->toBe('some-value')
        ->and($event->identifier)->toBe('user-123')
        ->and($event->bucket)->toBe(500)
        ->and($event->setting)->toBe($setting)
        ->and($event->rule)->toBe($rule)
        ->and($event->variant)->toBe($variant)
        ->and($event->tenantId)->toBe('tenant-1')
        ->and($event->context)->toBe(['extra' => 'data']);
});

test('toArray returns correct summary', function () {
    $event = new VariantAssigned(
        settingKey: 'test-setting',
        ruleName: 'Test Rule',
        variantName: 'Control',
        value: 'some-value',
        identifier: 'user-123',
        bucket: 500,
        tenantId: 'tenant-1',
        context: ['extra' => 'data']
    );

    $expected = [
        'setting_key' => 'test-setting',
        'rule_name' => 'Test Rule',
        'variant_name' => 'Control',
        'value' => 'some-value',
        'identifier' => 'user-123',
        'bucket' => 500,
        'tenant_id' => 'tenant-1',
        'context' => ['extra' => 'data'],
    ];

    expect($event->toArray())->toBe($expected);
});

test('toAnalytics returns correct format', function () {
    $event = new VariantAssigned(
        settingKey: 'test-setting',
        ruleName: 'Test Rule',
        variantName: 'Control',
        value: 'some-value',
        identifier: 'user-123',
        bucket: 500
    );

    $expected = [
        'experiment_key' => 'test-setting',
        'experiment_name' => 'Test Rule',
        'variant_key' => 'Control',
        'variant_value' => 'some-value',
        'assignment_id' => 'user-123',
        'bucket_value' => 500,
    ];

    expect($event->toAnalytics())->toBe($expected);
});
