<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Enums\ComparisonOperator;
use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\Exceptions\ImmutableSettingException;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Models\SettingRule;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use GaiaTools\FulcrumSettings\Support\MaskedValue;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;

test('setting rule can be created', function () {
    $setting = Setting::create([
        'key' => 'test.setting',
        'type' => SettingType::STRING,
    ]);

    $rule = SettingRule::create([
        'setting_id' => $setting->id,
        'name' => 'Test Rule',
        'priority' => 10,
    ]);

    expect($rule->name)->toBe('Test Rule')
        ->and($rule->priority)->toBe(10)
        ->and($rule->setting->id)->toBe($setting->id);
});

test('setting rule has empty rules relationship', function () {
    $setting = Setting::create([
        'key' => 'test.setting',
        'type' => SettingType::STRING,
    ]);

    $rule = SettingRule::create([
        'setting_id' => $setting->id,
        'name' => 'Test Rule',
    ]);

    expect($rule->rules)->toHaveCount(0);
});

test('setting rule has relationships', function () {
    $setting = Setting::create([
        'key' => 'test.setting',
        'type' => SettingType::STRING,
    ]);

    $rule = SettingRule::create([
        'setting_id' => $setting->id,
        'name' => 'Test Rule',
        'priority' => 10,
    ]);

    // conditions
    $condition = $rule->conditions()->create([
        'attribute' => 'user_id',
        'operator' => ComparisonOperator::EQUALS,
        'value' => '1',
    ]);
    expect($rule->conditions->first()->id)->toBe($condition->id);

    // rules (virtual/empty)
    expect($rule->rules)->toHaveCount(0);

    // value
    $value = $rule->value()->create([
        'valuable_type' => SettingRule::class,
        'valuable_id' => $rule->id,
        'value' => 'rule value',
    ]);
    expect($rule->value->id)->toBe($value->id);
});

test('getValue returns cast value', function () {
    $setting = Setting::create([
        'key' => 'test.integer',
        'type' => SettingType::INTEGER,
    ]);

    $rule = SettingRule::create([
        'setting_id' => $setting->id,
        'name' => 'Integer Rule',
        'priority' => 1,
    ]);

    $rule->value()->create([
        'valuable_type' => SettingRule::class,
        'valuable_id' => $rule->id,
        'value' => '123',
    ]);

    expect($rule->getValue())->toBe(123);
});

test('getValue returns null if no value', function () {
    $setting = Setting::create([
        'key' => 'test.string',
        'type' => SettingType::STRING,
    ]);

    $rule = SettingRule::create([
        'setting_id' => $setting->id,
        'name' => 'No Value Rule',
        'priority' => 1,
    ]);

    expect($rule->getValue())->toBeNull();
});

test('getValue respects masking when unauthorized', function () {
    Config::set('fulcrum.masking.mask_in_resolver', true);
    Config::set('fulcrum.masking.ability', 'viewSettingValue');
    Config::set('fulcrum.masking.placeholder', 'HIDDEN');

    $setting = Setting::create([
        'key' => 'test.secret.unauth',
        'type' => SettingType::STRING,
        'masked' => true,
    ]);

    $rule = SettingRule::create([
        'setting_id' => $setting->id,
        'priority' => 1,
    ]);

    $rule->value()->create([
        'valuable_type' => SettingRule::class,
        'valuable_id' => $rule->id,
        'value' => 'secret-value',
    ]);

    // Denied
    Gate::define('viewSettingValue', fn () => false);
    $value = $rule->getValue();
    expect($value)->toBeInstanceOf(MaskedValue::class)
        ->and((string) $value)->toBe('HIDDEN');
});

test('getValue respects masking when authorized', function () {
    Config::set('fulcrum.masking.mask_in_resolver', true);
    Config::set('fulcrum.masking.ability', 'viewSettingValue');

    $setting = Setting::create([
        'key' => 'test.secret.auth',
        'type' => SettingType::STRING,
        'masked' => true,
    ]);

    $rule = SettingRule::create([
        'setting_id' => $setting->id,
        'priority' => 1,
    ]);

    $rule->value()->create([
        'valuable_type' => SettingRule::class,
        'valuable_id' => $rule->id,
        'value' => 'secret-value',
    ]);

    // Allowed
    Gate::define('viewSettingValue', function ($user = null) {
        return true;
    });
    FulcrumContext::reveal(true);
    expect($rule->fresh()->getValue())->toBe('secret-value');
    FulcrumContext::reveal(false);
});

test('creating rule inherits tenant_id from setting', function () {
    FulcrumContext::setTenantId('999');
    $setting = Setting::create([
        'key' => 'test.setting',
        'type' => SettingType::STRING,
        'tenant_id' => '999',
    ]);

    $rule = SettingRule::create([
        'setting_id' => $setting->id,
        'name' => 'Tenant Rule',
        'priority' => 1,
    ]);

    expect((string) $rule->tenant_id)->toBe('999');
});

test('cannot create rule for immutable setting', function () {
    $setting = Setting::create([
        'key' => 'test.immutable',
        'type' => SettingType::STRING,
        'immutable' => true,
    ]);

    $this->expectException(ImmutableSettingException::class);

    SettingRule::create([
        'setting_id' => $setting->id,
        'priority' => 1,
    ]);
});

test('cannot update rule for immutable setting', function () {
    $setting = Setting::create([
        'key' => 'test.immutable',
        'type' => SettingType::STRING,
    ]);

    $rule = SettingRule::create([
        'setting_id' => $setting->id,
        'priority' => 1,
    ]);

    // Use force to make the setting immutable without triggering its own guard
    FulcrumContext::force(true);
    $setting->update(['immutable' => true]);
    FulcrumContext::force(false);

    $rule->setRelation('setting', $setting->fresh());

    $this->expectException(ImmutableSettingException::class);

    $rule->update(['priority' => 2]);
});

test('cannot delete rule for immutable setting when unauthorized', function () {
    Config::set('fulcrum.immutability.delete_ability', 'delete-immutable');

    $setting = Setting::create([
        'key' => 'test.immutable',
        'type' => SettingType::STRING,
    ]);

    $rule = SettingRule::create([
        'setting_id' => $setting->id,
        'priority' => 1,
    ]);

    FulcrumContext::force(true);
    $setting->update(['immutable' => true]);
    FulcrumContext::force(false);

    $rule->setRelation('setting', $setting->fresh());

    // Denied deletion
    Gate::define('delete-immutable', fn () => false);

    $this->expectException(ImmutableSettingException::class);
    $rule->delete();
});

test('can delete rule for immutable setting when authorized', function () {
    Config::set('fulcrum.immutability.delete_ability', 'delete-immutable');

    $setting = Setting::create([
        'key' => 'test.immutable',
        'type' => SettingType::STRING,
    ]);

    $rule = SettingRule::create([
        'setting_id' => $setting->id,
        'priority' => 1,
    ]);

    FulcrumContext::force(true);
    $setting->update(['immutable' => true]);
    FulcrumContext::force(false);

    $rule->setRelation('setting', $setting->fresh());

    // Allowed deletion
    Gate::define('delete-immutable', function ($user = null) {
        return true;
    });
    expect($rule->delete())->toBeTrue();
});

test('force allows changing rules of immutable settings', function () {
    $setting = Setting::create([
        'key' => 'test.immutable',
        'type' => SettingType::STRING,
        'immutable' => true,
    ]);

    FulcrumContext::force(true);
    $rule = SettingRule::create([
        'setting_id' => $setting->id,
        'priority' => 1,
    ]);

    expect($rule->exists)->toBeTrue();

    $rule->update(['priority' => 5]);
    expect($rule->fresh()->priority)->toBe(5);

    $rule->delete();
    expect($rule->fresh())->toBeNull();
    FulcrumContext::force(false);
});

test('rollout variants relationships and helpers', function () {
    $setting = Setting::create([
        'key' => 'test.rollout',
        'type' => SettingType::STRING,
    ]);

    $rule = SettingRule::create([
        'setting_id' => $setting->id,
        'name' => 'Rollout Rule',
    ]);

    expect($rule->hasRolloutVariants())->toBeFalse()
        ->and($rule->hasDirectValue())->toBeTrue();

    $variant1 = $rule->rolloutVariants()->create([
        'name' => 'A',
        'weight' => 25000, // 25%
    ]);

    $variant2 = $rule->rolloutVariants()->create([
        'name' => 'B',
        'weight' => 75000, // 75%
    ]);

    // Test relation and hasRolloutVariants (uncached)
    expect($rule->rolloutVariants)->toHaveCount(2)
        ->and($rule->hasRolloutVariants())->toBeTrue()
        ->and($rule->hasDirectValue())->toBeFalse();

    // Test total weight accessors
    expect($rule->total_rollout_weight)->toBe(100000)
        ->and($rule->total_rollout_percentage)->toBe(100.0);

    // Test relationLoaded branch
    $ruleWithRelations = SettingRule::with('rolloutVariants')->find($rule->id);
    expect($ruleWithRelations->relationLoaded('rolloutVariants'))->toBeTrue()
        ->and($ruleWithRelations->hasRolloutVariants())->toBeTrue()
        ->and($ruleWithRelations->total_rollout_weight)->toBe(100000);

    // Test getTotalRolloutWeightAttribute without relation loaded
    $ruleUnloaded = SettingRule::find($rule->id);
    expect($ruleUnloaded->relationLoaded('rolloutVariants'))->toBeFalse()
        ->and($ruleUnloaded->total_rollout_weight)->toBe(100000);
});

test('reset rollout assignments', function () {
    $setting = Setting::create([
        'key' => 'test.rollout',
        'type' => SettingType::STRING,
    ]);

    $rule = SettingRule::create([
        'setting_id' => $setting->id,
        'rollout_salt' => 'old-salt',
    ]);

    $rule->resetRolloutAssignments();

    expect($rule->rollout_salt)->not->toBe('old-salt')
        ->and(strlen($rule->rollout_salt))->toBe(16);
});

test('get effective salt', function () {
    $setting = Setting::create([
        'key' => 'test.setting',
        'type' => SettingType::STRING,
    ]);

    $rule = new SettingRule(['setting_id' => $setting->id]);
    $rule->id = 123;
    $rule->setRelation('setting', $setting);

    // Fallback to setting key
    expect($rule->getEffectiveSalt())->toBe('test.setting');

    // Custom salt
    $rule->rollout_salt = 'custom-salt';
    expect($rule->getEffectiveSalt())->toBe('custom-salt');
});

test('booted hooks handle missing setting relation gracefully', function () {
    $setting = Setting::create([
        'key' => 'test.setting',
        'type' => SettingType::STRING,
    ]);

    $rule = SettingRule::create([
        'setting_id' => $setting->id,
        'name' => 'Test Rule',
    ]);

    // Unset the relation to test the 'if ($model->setting)' check in hooks
    $rule->unsetRelation('setting');

    // This should not throw even if the setting would be immutable,
    // because the relation is not loaded and it might not find it if it doesn't query.
    // Actually the hooks use $model->setting which triggers a query if not loaded.

    $rule->name = 'Updated Name';
    expect($rule->save())->toBeTrue();
});
