<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Unit\Services;

use GaiaTools\FulcrumSettings\Contracts\GeoResolver;
use GaiaTools\FulcrumSettings\Contracts\HolidayResolver;
use GaiaTools\FulcrumSettings\Contracts\SegmentDriver;
use GaiaTools\FulcrumSettings\Contracts\UserAgentResolver;
use GaiaTools\FulcrumSettings\Drivers\WeightDistributionStrategy;
use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Models\SettingRule;
use GaiaTools\FulcrumSettings\Models\SettingValue;
use GaiaTools\FulcrumSettings\Services\Crc32BucketCalculator;
use GaiaTools\FulcrumSettings\Services\RuleEvaluator;
use GaiaTools\FulcrumSettings\Services\SettingResolver;
use GaiaTools\FulcrumSettings\Tests\TestCase;
use Illuminate\Support\Carbon;

// uses(TestCase::class); // Already set in Pest.php

beforeEach(function () {
    $this->segmentDriver = \Mockery::mock(SegmentDriver::class);
    $this->geoResolver = \Mockery::mock(GeoResolver::class);
    $this->uaResolver = \Mockery::mock(UserAgentResolver::class);
    $this->holidayResolver = \Mockery::mock(HolidayResolver::class);
    $this->app->instance(GeoResolver::class, $this->geoResolver);
    $this->app->instance(UserAgentResolver::class, $this->uaResolver);
    $this->evaluator = new RuleEvaluator(
        $this->segmentDriver,
        $this->holidayResolver
    );
    $this->bucketCalculator = new Crc32BucketCalculator;
    $this->distributionStrategy = new WeightDistributionStrategy;
    $this->resolver = new SettingResolver($this->evaluator, $this->bucketCalculator, $this->distributionStrategy);
});

test('it skips rules that have not started yet', function () {
    $setting = Setting::create([
        'key' => 'time.setting',
        'type' => SettingType::STRING,
    ]);

    SettingValue::create([
        'valuable_type' => Setting::class,
        'valuable_id' => $setting->id,
        'value' => 'default-value',
    ]);

    $rule = SettingRule::create([
        'setting_id' => $setting->id,
        'starts_at' => Carbon::now()->addDay(),
        'priority' => 1,
    ]);

    SettingValue::create([
        'valuable_type' => SettingRule::class,
        'valuable_id' => $rule->id,
        'value' => 'rule-value',
    ]);

    expect($this->resolver->resolve('time.setting'))->toBe('default-value');
});

test('it skips rules that have expired', function () {
    $setting = Setting::create([
        'key' => 'time.setting',
        'type' => SettingType::STRING,
    ]);

    SettingValue::create([
        'valuable_type' => Setting::class,
        'valuable_id' => $setting->id,
        'value' => 'default-value',
    ]);

    $rule = SettingRule::create([
        'setting_id' => $setting->id,
        'ends_at' => Carbon::now()->subDay(),
        'priority' => 1,
    ]);

    SettingValue::create([
        'valuable_type' => SettingRule::class,
        'valuable_id' => $rule->id,
        'value' => 'rule-value',
    ]);

    expect($this->resolver->resolve('time.setting'))->toBe('default-value');
});

test('it applies rules within their active time range', function () {
    $setting = Setting::create([
        'key' => 'time.setting',
        'type' => SettingType::STRING,
    ]);

    SettingValue::create([
        'valuable_type' => Setting::class,
        'valuable_id' => $setting->id,
        'value' => 'default-value',
    ]);

    $rule = SettingRule::create([
        'setting_id' => $setting->id,
        'starts_at' => Carbon::now()->subDay(),
        'ends_at' => Carbon::now()->addDay(),
        'priority' => 1,
    ]);

    SettingValue::create([
        'valuable_type' => SettingRule::class,
        'valuable_id' => $rule->id,
        'value' => 'rule-value',
    ]);

    expect($this->resolver->resolve('time.setting'))->toBe('rule-value');
});

test('it applies rules with no time bounds', function () {
    $setting = Setting::create([
        'key' => 'time.setting',
        'type' => SettingType::STRING,
    ]);

    SettingValue::create([
        'valuable_type' => Setting::class,
        'valuable_id' => $setting->id,
        'value' => 'default-value',
    ]);

    $rule = SettingRule::create([
        'setting_id' => $setting->id,
        'priority' => 1,
    ]);

    SettingValue::create([
        'valuable_type' => SettingRule::class,
        'valuable_id' => $rule->id,
        'value' => 'rule-value',
    ]);

    expect($this->resolver->resolve('time.setting'))->toBe('rule-value');
});

test('it handles mixed time-bound and non-time-bound rules correctly', function () {
    $setting = Setting::create([
        'key' => 'time.setting',
        'type' => SettingType::STRING,
    ]);

    SettingValue::create([
        'valuable_type' => Setting::class,
        'valuable_id' => $setting->id,
        'value' => 'default-value',
    ]);

    // Rule 1: Priority 1, Expired
    $rule1 = SettingRule::create([
        'setting_id' => $setting->id,
        'ends_at' => Carbon::now()->subDay(),
        'priority' => 1,
    ]);
    SettingValue::create([
        'valuable_type' => SettingRule::class,
        'valuable_id' => $rule1->id,
        'value' => 'rule-1',
    ]);

    // Rule 2: Priority 2, Active
    $rule2 = SettingRule::create([
        'setting_id' => $setting->id,
        'starts_at' => Carbon::now()->subHour(),
        'priority' => 2,
    ]);
    SettingValue::create([
        'valuable_type' => SettingRule::class,
        'valuable_id' => $rule2->id,
        'value' => 'rule-2',
    ]);

    // Rule 3: Priority 3, No bounds (but Rule 2 should win)
    $rule3 = SettingRule::create([
        'setting_id' => $setting->id,
        'priority' => 3,
    ]);
    SettingValue::create([
        'valuable_type' => SettingRule::class,
        'valuable_id' => $rule3->id,
        'value' => 'rule-3',
    ]);

    expect($this->resolver->resolve('time.setting'))->toBe('rule-2');
});
