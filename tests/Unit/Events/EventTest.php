<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Events\LoadingSettings;
use GaiaTools\FulcrumSettings\Events\SavingSettings;
use GaiaTools\FulcrumSettings\Events\SettingEvent;
use GaiaTools\FulcrumSettings\Events\SettingResolved;
use GaiaTools\FulcrumSettings\Events\SettingsLoaded;
use GaiaTools\FulcrumSettings\Events\SettingsSaved;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Models\SettingRule;

it('instantiates LoadingSettings', function () {
    $event = new LoadingSettings;
    expect($event)->toBeInstanceOf(LoadingSettings::class);
});

it('instantiates SavingSettings', function () {
    $settings = ['key' => 'value'];
    $event = new SavingSettings($settings);
    expect($event->settings)->toBe($settings);
});

it('instantiates SettingsLoaded', function () {
    $settings = ['key' => 'value'];
    $event = new SettingsLoaded($settings);
    expect($event->settings)->toBe($settings);
});

it('instantiates SettingsSaved', function () {
    $settings = ['key' => 'value'];
    $event = new SettingsSaved($settings);
    expect($event->settings)->toBe($settings);
});

it('instantiates SettingEvent', function () {
    $setting = new Setting;
    $event = new class($setting) extends SettingEvent {};
    expect($event->setting)->toBe($setting);
});

it('instantiates SettingResolved and converts to array', function () {
    $setting = new Setting;
    $rule = new SettingRule(['name' => 'test rule', 'priority' => 10]);

    $event = new SettingResolved(
        key: 'test.key',
        value: 'test.value',
        setting: $setting,
        matchedRule: $rule,
        rulesEvaluated: 5,
        source: 'database',
        tenantId: 'tenant-123',
        userId: 1,
        scope: ['foo' => 'bar'],
        durationMs: 1.234
    );

    expect($event->key)->toBe('test.key')
        ->and($event->value)->toBe('test.value')
        ->and($event->setting)->toBe($setting)
        ->and($event->matchedRule)->toBe($rule)
        ->and($event->rulesEvaluated)->toBe(5)
        ->and($event->source)->toBe('database')
        ->and($event->tenantId)->toBe('tenant-123')
        ->and($event->userId)->toBe(1)
        ->and($event->scope)->toBe(['foo' => 'bar'])
        ->and($event->durationMs)->toBe(1.234);

    $array = $event->toArray();
    expect($array)->toBe([
        'key' => 'test.key',
        'value' => 'test.value',
        'source' => 'database',
        'matched_rule' => 'test rule',
        'matched_rule_priority' => 10,
        'rules_evaluated' => 5,
        'tenant_id' => 'tenant-123',
        'user_id' => 1,
        'duration_ms' => 1.23,
    ]);
});

it('handles nulls in SettingResolved toArray', function () {
    $event = new SettingResolved(
        key: 'test.key',
        value: 'test.value',
    );

    $array = $event->toArray();
    expect($array)->toBe([
        'key' => 'test.key',
        'value' => 'test.value',
        'source' => 'default',
        'matched_rule' => null,
        'matched_rule_priority' => null,
        'rules_evaluated' => 0,
        'tenant_id' => null,
        'user_id' => null,
        'duration_ms' => 0.0,
    ]);
});
