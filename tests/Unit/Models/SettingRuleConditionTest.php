<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Enums\ComparisonOperator;
use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\Exceptions\ImmutableSettingException;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Models\SettingRule;
use GaiaTools\FulcrumSettings\Models\SettingRuleCondition;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;

test('setting rule condition can be created', function () {
    $setting = Setting::create([
        'key' => 'test.setting',
        'type' => SettingType::STRING,
    ]);

    $rule = SettingRule::create([
        'setting_id' => $setting->id,
        'name' => 'Test Rule',
        'priority' => 10,
    ]);

    $condition = SettingRuleCondition::create([
        'setting_rule_id' => $rule->id,
        'attribute' => 'user_id',
        'operator' => ComparisonOperator::EQUALS,
        'value' => '123',
    ]);

    expect($condition->attribute)->toBe('user_id')
        ->and($condition->operator)->toBe(ComparisonOperator::EQUALS)
        ->and($condition->value)->toBe('123')
        ->and($condition->rule->id)->toBe($rule->id);
});

test('setting rule condition inherits tenant_id from setting', function () {
    FulcrumContext::setTenantId('tenant-123');
    $setting = Setting::create([
        'key' => 'test.setting',
        'type' => SettingType::STRING,
        'tenant_id' => 'tenant-123',
    ]);

    $rule = SettingRule::create([
        'setting_id' => $setting->id,
        'priority' => 1,
    ]);

    $condition = SettingRuleCondition::create([
        'setting_rule_id' => $rule->id,
        'attribute' => 'user_id',
        'operator' => ComparisonOperator::EQUALS,
        'value' => '123',
    ]);

    expect((string) $condition->tenant_id)->toBe('tenant-123');
});

test('cannot create condition for immutable setting', function () {
    $setting = Setting::create([
        'key' => 'test.immutable',
        'type' => SettingType::STRING,
        'immutable' => true,
    ]);

    // We have to bypass guard to create the rule first
    FulcrumContext::force(true);
    $rule = SettingRule::create([
        'setting_id' => $setting->id,
        'priority' => 1,
    ]);
    FulcrumContext::force(false);

    $this->expectException(ImmutableSettingException::class);

    SettingRuleCondition::create([
        'setting_rule_id' => $rule->id,
        'attribute' => 'attr',
        'operator' => ComparisonOperator::EQUALS,
        'value' => 'val',
    ]);
});

test('cannot update condition for immutable setting', function () {
    $setting = Setting::create([
        'key' => 'test.immutable',
        'type' => SettingType::STRING,
    ]);

    $rule = SettingRule::create([
        'setting_id' => $setting->id,
        'priority' => 1,
    ]);

    $condition = SettingRuleCondition::create([
        'setting_rule_id' => $rule->id,
        'attribute' => 'attr',
        'operator' => ComparisonOperator::EQUALS,
        'value' => 'val',
    ]);

    FulcrumContext::force(true);
    $setting->update(['immutable' => true]);
    FulcrumContext::force(false);

    $condition->setRelation('rule', $rule->setRelation('setting', $setting->fresh()));

    $this->expectException(ImmutableSettingException::class);

    $condition->update(['value' => 'new-val']);
});

test('cannot delete condition for immutable setting', function () {
    $setting = Setting::create([
        'key' => 'test.immutable',
        'type' => SettingType::STRING,
    ]);

    $rule = SettingRule::create([
        'setting_id' => $setting->id,
        'priority' => 1,
    ]);

    $condition = SettingRuleCondition::create([
        'setting_rule_id' => $rule->id,
        'attribute' => 'attr',
        'operator' => ComparisonOperator::EQUALS,
        'value' => 'val',
    ]);

    FulcrumContext::force(true);
    $setting->update(['immutable' => true]);
    FulcrumContext::force(false);

    $condition->setRelation('rule', $rule->setRelation('setting', $setting->fresh()));

    $this->expectException(ImmutableSettingException::class);

    $condition->delete();
});

test('can bypass immutability guard for condition with force', function () {
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

    $condition = SettingRuleCondition::create([
        'setting_rule_id' => $rule->id,
        'attribute' => 'attr',
        'operator' => ComparisonOperator::EQUALS,
        'value' => 'val',
    ]);

    $condition->update(['value' => 'new-val']);
    expect($condition->fresh()->value)->toBe('new-val');

    $condition->delete();
    expect(SettingRuleCondition::find($condition->id))->toBeNull();

    FulcrumContext::force(false);
});
