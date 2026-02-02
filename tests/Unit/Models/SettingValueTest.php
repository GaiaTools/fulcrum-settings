<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\Exceptions\ImmutableSettingException;
use GaiaTools\FulcrumSettings\Exceptions\SettingNotFoundException;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Models\SettingRule;
use GaiaTools\FulcrumSettings\Models\SettingRuleRolloutVariant;
use GaiaTools\FulcrumSettings\Models\SettingValue;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;

test('setting value can be created for a setting', function () {
    $setting = Setting::create([
        'key' => 'test.setting',
        'type' => SettingType::STRING,
    ]);

    $value = SettingValue::create([
        'valuable_type' => Setting::class,
        'valuable_id' => $setting->id,
        'value' => 'test-value',
    ]);

    expect($value->value)->toBe('test-value')
        ->and($value->valuable->id)->toBe($setting->id);
});

test('setting value handles boolean types', function () {
    $setting = Setting::create([
        'key' => 'test.bool',
        'type' => SettingType::BOOLEAN,
    ]);

    $value = SettingValue::create([
        'valuable_type' => Setting::class,
        'valuable_id' => $setting->id,
        'value' => true,
    ]);

    expect($value->value)->toBe(true);

    $value->value = false;
    $value->save();
    expect($value->fresh()->value)->toBe(false);
});

test('setting value handles json types', function () {
    $setting = Setting::create([
        'key' => 'test.json',
        'type' => SettingType::JSON,
    ]);

    $data = ['foo' => 'bar'];
    $value = SettingValue::create([
        'valuable_type' => Setting::class,
        'valuable_id' => $setting->id,
        'value' => $data,
    ]);

    expect($value->getAttributes()['value'])->toBe(json_encode($data))
        ->and($value->value)->toBe($data);
});

test('setting value handles encryption when masked', function () {
    $setting = Setting::create([
        'key' => 'test.masked',
        'type' => SettingType::STRING,
        'masked' => true,
    ]);

    $value = SettingValue::create([
        'valuable_type' => Setting::class,
        'valuable_id' => $setting->id,
        'value' => 'secret',
    ]);

    expect($value->getAttributes()['value'])->not->toBe('secret');
    expect(Crypt::decryptString($value->getAttributes()['value']))->toBe('secret');
    expect($value->value)->toBe('secret');
});

test('decrypted returns null when value is null', function () {
    $value = new SettingValue;
    expect($value->decrypted())->toBeNull();
});

test('decrypted returns cast value', function () {
    $setting = Setting::create([
        'key' => 'test.decrypted',
        'type' => SettingType::BOOLEAN,
    ]);

    $value = SettingValue::create([
        'valuable_type' => Setting::class,
        'valuable_id' => $setting->id,
        'value' => true,
    ]);

    expect($value->decrypted())->toBe(true);
});

test('setting value inherits tenant_id from setting', function () {
    FulcrumContext::setTenantId('tenant-1');
    $setting = Setting::create([
        'key' => 'test.tenant',
        'type' => SettingType::STRING,
        'tenant_id' => 'tenant-1',
    ]);

    $value = SettingValue::create([
        'valuable_type' => Setting::class,
        'valuable_id' => $setting->id,
        'value' => 'foo',
    ]);

    expect((string) $value->tenant_id)->toBe('tenant-1');
});

test('can create value for immutable setting', function () {
    $setting = Setting::create([
        'key' => 'test.immutable',
        'type' => SettingType::STRING,
        'immutable' => true,
    ]);

    $value = SettingValue::create([
        'valuable_type' => Setting::class,
        'valuable_id' => $setting->id,
        'value' => 'foo',
    ]);

    expect($value->value)->toBe('foo');
});

test('cannot update value for immutable setting', function () {
    $setting = Setting::create([
        'key' => 'test.immutable',
        'type' => SettingType::STRING,
    ]);

    $value = SettingValue::create([
        'valuable_type' => Setting::class,
        'valuable_id' => $setting->id,
        'value' => 'foo',
    ]);

    FulcrumContext::force(true);
    $setting->update(['immutable' => true]);
    FulcrumContext::force(false);

    $value->setRelation('valuable', $setting->fresh());

    $this->expectException(ImmutableSettingException::class);
    $value->update(['value' => 'bar']);
});

test('cannot delete value for immutable setting when unauthorized', function () {
    Config::set('fulcrum.immutability.allow_delete_via_gate', true);
    Config::set('fulcrum.immutability.delete_ability', 'delete-immutable');

    $setting = Setting::create([
        'key' => 'test.immutable',
        'type' => SettingType::STRING,
    ]);

    $value = SettingValue::create([
        'valuable_type' => Setting::class,
        'valuable_id' => $setting->id,
        'value' => 'foo',
    ]);

    FulcrumContext::force(true);
    $setting->update(['immutable' => true]);
    FulcrumContext::force(false);

    $value->setRelation('valuable', $setting->fresh());

    Gate::define('delete-immutable', function ($user = null) {
        return false;
    });

    $this->expectException(ImmutableSettingException::class);
    $value->delete();
});

test('can delete value for immutable setting when authorized via gate', function () {
    Config::set('fulcrum.immutability.allow_delete_via_gate', true);
    Config::set('fulcrum.immutability.delete_ability', 'delete-immutable');

    $setting = Setting::create([
        'key' => 'test.immutable',
        'type' => SettingType::STRING,
    ]);

    $value = SettingValue::create([
        'valuable_type' => Setting::class,
        'valuable_id' => $setting->id,
        'value' => 'foo',
    ]);

    FulcrumContext::force(true);
    $setting->update(['immutable' => true]);
    FulcrumContext::force(false);

    $value->setRelation('valuable', $setting->fresh());

    Gate::define('delete-immutable', function ($user = null) {
        return true;
    });

    expect($value->delete())->toBeTrue();
    expect(SettingValue::find($value->id))->toBeNull();
});

test('can delete value for immutable setting with force', function () {
    $setting = Setting::create([
        'key' => 'test.immutable',
        'type' => SettingType::STRING,
        'immutable' => true,
    ]);

    FulcrumContext::force(true);
    $value = SettingValue::create([
        'valuable_type' => Setting::class,
        'valuable_id' => $setting->id,
        'value' => 'foo',
    ]);

    expect($value->delete())->toBeTrue();
    FulcrumContext::force(false);
});

test('resolveSetting handles SettingRule correctly when relation is not loaded', function () {
    $setting = Setting::create([
        'key' => 'test.rule.setting.noload',
        'type' => SettingType::STRING,
    ]);

    $rule = SettingRule::create([
        'setting_id' => $setting->id,
        'priority' => 1,
    ]);

    $value = new SettingValue;
    $value->valuable_type = SettingRule::class;
    $value->valuable_id = $rule->id;
    $value->value = 'rule-value';

    // Explicitly null out the relation to ensure it's not loaded
    $value->setRelation('valuable', null);

    $value->save();

    // Verify it resolved the setting (and thus tenant_id) via ID because relation wasn't loaded
    expect((string) $value->tenant_id)->toBe((string) $setting->tenant_id);
});

test('decryption failure returns raw value', function () {
    $setting = Setting::create([
        'key' => 'test.masked.fail',
        'type' => SettingType::STRING,
        'masked' => true,
    ]);

    $value = new SettingValue([
        'valuable_type' => Setting::class,
        'valuable_id' => $setting->id,
    ]);
    // Bypassing mutator to set raw unencrypted value
    $value->setRawAttributes([
        'valuable_type' => Setting::class,
        'valuable_id' => $setting->id,
        'value' => 'not-encrypted',
    ]);
    $value->save();

    expect($value->fresh()->value)->toBe('not-encrypted');
});

test('it throws exception when setting is not found during set', function () {
    $value = new SettingValue;
    $value->valuable_type = 'NonExistent';
    $value->valuable_id = 999;

    $this->expectException(SettingNotFoundException::class);
    $value->value = 'some-value';
});

test('resolveSetting handles SettingRuleRolloutVariant correctly when relation is not loaded', function () {
    $setting = Setting::create([
        'key' => 'test.variant.setting.noload',
        'type' => SettingType::STRING,
    ]);

    $rule = SettingRule::create([
        'setting_id' => $setting->id,
        'priority' => 1,
    ]);

    $variant = SettingRuleRolloutVariant::create([
        'setting_rule_id' => $rule->id,
        'name' => 'variant-a',
        'weight' => 50000,
    ]);

    $value = new SettingValue;
    $value->valuable_type = SettingRuleRolloutVariant::class;
    $value->valuable_id = $variant->id;
    $value->value = 'variant-value';

    // Explicitly null out the relation to ensure it's not loaded
    $value->setRelation('valuable', null);

    $value->save();

    expect((string) $value->tenant_id)->toBe((string) $setting->tenant_id);
});

test('value accessor returns raw value when no setting is associated', function () {
    // This triggers line 72 in SettingValue.php: return $value; (when $setting is null)
    $value = new SettingValue;
    $value->setRawAttributes(['value' => 'raw-value']);

    expect($value->value)->toBe('raw-value');
});

test('resolveSetting handles relation loading failure', function () {
    $value = new SettingValue;
    // We want to trigger the catch block in resolveSetting:
    // try { $owner = $this->valuable; } catch (\Throwable) { $owner = null; }

    // It's hard to make $this->valuable throw an exception without mocking the whole model,
    // but maybe we can trigger it by having invalid relation data.
    $value->valuable_type = 'InvalidClass';
    $value->valuable_id = 1;

    // If it doesn't throw, it will at least fall through to IDs resolution which should also fail
    expect($value->value)->toBeNull();
});

test('set mutator fallbacks to manual resolution via instance IDs', function () {
    $setting = Setting::create([
        'key' => 'test.fallback',
        'type' => SettingType::STRING,
    ]);

    $value = new SettingValue;
    $value->valuable_type = Setting::class;
    $value->valuable_id = $setting->id;

    // Setting value when it's not in $attributes yet but already in $this->attributes
    $value->value = 'fallback-value';

    expect($value->value)->toBe('fallback-value');
});
