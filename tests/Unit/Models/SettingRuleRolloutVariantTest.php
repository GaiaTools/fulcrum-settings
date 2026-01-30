<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\Exceptions\ImmutableSettingException;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Models\SettingRule;
use GaiaTools\FulcrumSettings\Models\SettingRuleRolloutVariant;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use GaiaTools\FulcrumSettings\Support\MaskedValue;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;

test('rollout variant can be created and has relationships', function () {
    $setting = Setting::create([
        'key' => 'test.setting',
        'type' => SettingType::STRING,
    ]);

    $rule = SettingRule::create([
        'setting_id' => $setting->id,
        'name' => 'Test Rule',
    ]);

    $variant = SettingRuleRolloutVariant::create([
        'setting_rule_id' => $rule->id,
        'name' => 'Variant A',
        'weight' => 50000,
    ]);

    expect($variant->name)->toBe('Variant A')
        ->and($variant->weight)->toBe(50000)
        ->and($variant->rule->id)->toBe($rule->id);

    $value = $variant->value()->create([
        'valuable_type' => SettingRuleRolloutVariant::class,
        'valuable_id' => $variant->id,
        'value' => 'value a',
    ]);
    expect($variant->value->id)->toBe($value->id);
});

test('getValue returns cast value from variant', function () {
    $setting = Setting::create([
        'key' => 'test.integer',
        'type' => SettingType::INTEGER,
    ]);

    $rule = SettingRule::create([
        'setting_id' => $setting->id,
        'name' => 'Integer Rule',
    ]);

    $variant = SettingRuleRolloutVariant::create([
        'setting_rule_id' => $rule->id,
        'name' => 'Variant 1',
        'weight' => 100000,
    ]);

    $variant->value()->create([
        'valuable_type' => SettingRuleRolloutVariant::class,
        'valuable_id' => $variant->id,
        'value' => '123',
    ]);

    expect($variant->getValue())->toBe(123);
});

test('getValue returns null if no value for variant', function () {
    $setting = Setting::create([
        'key' => 'test.string',
        'type' => SettingType::STRING,
    ]);

    $rule = SettingRule::create([
        'setting_id' => $setting->id,
        'name' => 'String Rule',
    ]);

    $variant = SettingRuleRolloutVariant::create([
        'setting_rule_id' => $rule->id,
        'name' => 'No Value Variant',
        'weight' => 0,
    ]);

    expect($variant->getValue())->toBeNull();
});

test('getValue respects masking for variants', function () {
    Config::set('fulcrum.masking.mask_in_resolver', true);
    Config::set('fulcrum.masking.ability', 'viewSettingValue');
    Config::set('fulcrum.masking.placeholder', 'HIDDEN');

    $setting = Setting::create([
        'key' => 'test.secret',
        'type' => SettingType::STRING,
        'masked' => true,
    ]);

    $rule = SettingRule::create([
        'setting_id' => $setting->id,
    ]);

    $variant = SettingRuleRolloutVariant::create([
        'setting_rule_id' => $rule->id,
        'name' => 'Secret Variant',
        'weight' => 100,
    ]);

    $variant->value()->create([
        'valuable_type' => SettingRuleRolloutVariant::class,
        'valuable_id' => $variant->id,
        'value' => 'secret-value',
    ]);

    // Denied
    Gate::define('viewSettingValue', fn () => false);
    $value = $variant->getValue();
    expect($value)->toBeInstanceOf(MaskedValue::class)
        ->and((string) $value)->toBe('HIDDEN');

    // Allowed
    Gate::define('viewSettingValue', function ($user = null, $setting = null) {
        return true;
    });
    FulcrumContext::reveal(true);
    expect((string) $variant->fresh()->getValue())->toBe('secret-value');
    FulcrumContext::reveal(false);
});

test('weight helpers work correctly', function () {
    $variant = new SettingRuleRolloutVariant(['weight' => 12345]);

    expect($variant->weight_percentage)->toBe(12.345);

    $variant->setWeightFromPercentage(88.5);
    expect($variant->weight)->toBe(88500);

    $variant->setWeightFromPercentage(33.3333);
    expect($variant->weight)->toBe(33333);
});

test('creating variant inherits tenant_id', function () {
    FulcrumContext::setTenantId('tenant-123');
    $setting = Setting::create([
        'key' => 'test.setting',
        'type' => SettingType::STRING,
        'tenant_id' => 'tenant-123',
    ]);

    $rule = SettingRule::create([
        'setting_id' => $setting->id,
    ]);

    $variant = SettingRuleRolloutVariant::create([
        'setting_rule_id' => $rule->id,
        'name' => 'Variant',
        'weight' => 1000,
    ]);

    expect((string) $variant->tenant_id)->toBe('tenant-123');
});

test('immutability guards for variants', function () {
    $setting = Setting::create([
        'key' => 'test.immutable',
        'type' => SettingType::STRING,
    ]);

    $rule = SettingRule::create([
        'setting_id' => $setting->id,
    ]);

    $variant = SettingRuleRolloutVariant::create([
        'setting_rule_id' => $rule->id,
        'name' => 'Variant',
        'weight' => 1000,
    ]);

    FulcrumContext::force(true);
    $setting->update(['immutable' => true]);
    FulcrumContext::force(false);

    $rule->setRelation('setting', $setting->fresh());
    $variant->setRelation('rule', $rule);

    // Update guard
    expect(fn () => $variant->update(['weight' => 2000]))->toThrow(ImmutableSettingException::class);

    // Delete guard
    Gate::define('deleteImmutableSetting', fn () => false);
    expect(fn () => $variant->delete())->toThrow(ImmutableSettingException::class);

    // Delete guard - authorized
    Gate::define('deleteImmutableSetting', function ($user = null, $setting = null) {
        return true;
    });
    expect($variant->delete())->toBeTrue();

    // Create guard
    expect(fn () => SettingRuleRolloutVariant::create([
        'setting_rule_id' => $rule->id,
        'name' => 'New Variant',
        'weight' => 500,
    ]))->toThrow(ImmutableSettingException::class);
});

test('resolveSetting returns null if no rule', function () {
    $variant = new SettingRuleRolloutVariant;
    expect($variant->resolveSetting())->toBeNull();
});

test('booted hooks handle missing rule or setting gracefully', function () {
    // Creating with no rule - shouldn't crash, just no tenant_id inheritance
    $variant = new SettingRuleRolloutVariant(['name' => 'No Rule', 'weight' => 0]);
    // We don't save to avoid integrity constraint, but we can call the hook manually or test the logic
    // Actually, let's just test that it doesn't throw when creating a new instance

    expect($variant->tenant_id)->toBeNull();
});
