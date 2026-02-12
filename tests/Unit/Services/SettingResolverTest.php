<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

require_once __DIR__.'/../../Helpers/NamespaceMocks.php';

use GaiaTools\FulcrumSettings\Contracts\GeoResolver;
use GaiaTools\FulcrumSettings\Contracts\HolidayResolver;
use GaiaTools\FulcrumSettings\Contracts\SegmentDriver;
use GaiaTools\FulcrumSettings\Contracts\UserAgentResolver;
use GaiaTools\FulcrumSettings\Drivers\WeightDistributionStrategy;
use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\Events\SettingResolved;
use GaiaTools\FulcrumSettings\Events\VariantAssigned;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Models\SettingRule;
use GaiaTools\FulcrumSettings\Models\SettingValue;
use GaiaTools\FulcrumSettings\Services\Crc32BucketCalculator;
use GaiaTools\FulcrumSettings\Services\RuleEvaluator;
use GaiaTools\FulcrumSettings\Services\SettingResolver;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use ReflectionClass;

uses(MockeryPHPUnitIntegration::class);

beforeEach(function () {
    config(['fulcrum.multi_tenancy.enabled' => true]);
    FulcrumContext::setTenantId(null);
    FulcrumContext::setGroup(null);
    unset($GLOBALS['mock_telescope_missing']);
    $this->segmentDriver = Mockery::mock(SegmentDriver::class);
    $this->geoResolver = Mockery::mock(GeoResolver::class);
    $this->uaResolver = Mockery::mock(UserAgentResolver::class);
    $this->holidayResolver = Mockery::mock(HolidayResolver::class);
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

test('setting resolver resolves default value when no rules match', function () {
    $setting = Setting::create([
        'key' => 'test.setting',
        'type' => SettingType::STRING,
    ]);

    SettingValue::create([
        'valuable_type' => Setting::class,
        'valuable_id' => $setting->id,
        'value' => 'default-value',
    ]);

    expect($this->resolver->resolve('test.setting'))->toBe('default-value');
});

test('it prefixes group for ungrouped keys', function () {
    $setting = Setting::create([
        'key' => 'general.site_name',
        'type' => SettingType::STRING,
    ]);

    SettingValue::create([
        'valuable_type' => Setting::class,
        'valuable_id' => $setting->id,
        'value' => 'My App',
    ]);

    FulcrumContext::setGroup('general');

    expect($this->resolver->resolve('site_name'))->toBe('My App');
    expect($this->resolver->resolve('general.site_name'))->toBe('My App');
});

test('it supports forGroup scoping', function () {
    $setting = Setting::create([
        'key' => 'billing.tax_rate',
        'type' => SettingType::FLOAT,
    ]);

    SettingValue::create([
        'valuable_type' => Setting::class,
        'valuable_id' => $setting->id,
        'value' => 0.07,
    ]);

    $grouped = $this->resolver->forGroup('billing');

    expect($grouped->resolve('tax_rate'))->toBe(0.07);
});

test('it resolves group values', function () {
    $twitter = Setting::create([
        'key' => 'my_links.twitter',
        'type' => SettingType::STRING,
    ]);
    SettingValue::create([
        'valuable_type' => Setting::class,
        'valuable_id' => $twitter->id,
        'value' => 'https://twitter.com/example',
    ]);

    $facebook = Setting::create([
        'key' => 'my_links.facebook',
        'type' => SettingType::STRING,
    ]);
    SettingValue::create([
        'valuable_type' => Setting::class,
        'valuable_id' => $facebook->id,
        'value' => 'https://facebook.com/example',
    ]);

    $links = $this->resolver->group('my_links')->all();

    expect($links)->toBe([
        'twitter' => 'https://twitter.com/example',
        'facebook' => 'https://facebook.com/example',
    ]);
});

test('setting resolver returns null for non-existent setting', function () {
    expect($this->resolver->resolve('non.existent'))->toBeNull();
});

test('it resolves tenant-scoped setting over shared setting', function () {
    config(['fulcrum.multi_tenancy.enabled' => true]);

    // Shared setting
    $shared = Setting::create([
        'key' => 'scoped.setting',
        'type' => SettingType::STRING,
        'tenant_id' => null,
    ]);
    SettingValue::create([
        'valuable_type' => Setting::class,
        'valuable_id' => $shared->id,
        'value' => 'shared-value',
    ]);

    // Tenant-scoped setting
    $tenantScoped = Setting::create([
        'key' => 'scoped.setting',
        'type' => SettingType::STRING,
        'tenant_id' => 'tenant-1',
    ]);
    FulcrumContext::setTenantId('tenant-1');
    SettingValue::create([
        'valuable_type' => Setting::class,
        'valuable_id' => $tenantScoped->id,
        'value' => 'tenant-value',
    ]);
    FulcrumContext::setTenantId(null);

    // Test with tenant-1
    $resolver = $this->resolver->forTenant('tenant-1');
    expect($resolver->resolve('scoped.setting'))->toBe('tenant-value');

    // Test fallback to shared when tenant setting doesn't exist
    $resolver = $this->resolver->forTenant('tenant-2');
    expect($resolver->resolve('scoped.setting'))->toBe('shared-value');

    // Test with no tenant (should get shared)
    $resolver = $this->resolver->forTenant(null);
    expect($resolver->resolve('scoped.setting'))->toBe('shared-value');
});

test('it evaluates rules in priority order', function () {
    $setting = Setting::create([
        'key' => 'rule.setting',
        'type' => SettingType::STRING,
    ]);

    SettingValue::create([
        'valuable_type' => Setting::class,
        'valuable_id' => $setting->id,
        'value' => 'default',
    ]);

    // Priority 2 rule (matches)
    $rule2 = SettingRule::create([
        'setting_id' => $setting->id,
        'priority' => 2,
    ]);
    SettingValue::create([
        'valuable_type' => SettingRule::class,
        'valuable_id' => $rule2->id,
        'value' => 'priority-2',
    ]);

    // Priority 1 rule (matches)
    $rule1 = SettingRule::create([
        'setting_id' => $setting->id,
        'priority' => 1,
    ]);
    SettingValue::create([
        'valuable_type' => SettingRule::class,
        'valuable_id' => $rule1->id,
        'value' => 'priority-1',
    ]);

    // Should return priority-1 because it's evaluated first
    expect($this->resolver->resolve('rule.setting'))->toBe('priority-1');
});

test('it dispatches SettingResolved event', function () {
    Event::fake();
    config(['fulcrum.telescope.enabled' => true]);

    Setting::create([
        'key' => 'event.setting',
        'type' => SettingType::STRING,
    ]);

    $this->resolver->resolve('event.setting');

    Event::assertDispatched(SettingResolved::class, function ($event) {
        return $event->key === 'event.setting'
            && $event->source === 'default'
            && $event->rulesEvaluated === 0
            && $event->durationMs > 0;
    });
});

test('it does not dispatch event when telescope is disabled', function () {
    Event::fake();
    config(['fulcrum.telescope.enabled' => false]);

    Setting::create([
        'key' => 'no.event.setting',
        'type' => SettingType::STRING,
    ]);

    $this->resolver->resolve('no.event.setting');

    Event::assertNotDispatched(SettingResolved::class);
});

test('it handles utility methods', function () {
    $setting = Setting::create([
        'key' => 'util.setting',
        'type' => SettingType::BOOLEAN,
    ]);
    SettingValue::create([
        'valuable_type' => Setting::class,
        'valuable_id' => $setting->id,
        'value' => true,
    ]);

    expect($this->resolver->isActive('util.setting'))->toBeTrue();
    expect($this->resolver->get('util.setting', false))->toBeTrue();
    expect($this->resolver->get('non.existent', 'default'))->toBe('default');
});

test('it handles forUser cloning', function () {
    $user = Mockery::mock(Authenticatable::class);
    $newResolver = $this->resolver->forUser($user);

    expect($newResolver)->not->toBe($this->resolver);
    // Use reflection to check protected property
    $reflection = new ReflectionClass($newResolver);
    $property = $reflection->getProperty('user');
    $property->setAccessible(true);
    expect($property->getValue($newResolver))->toBe($user);
});

test('it resolves tenant id from various sources', function () {
    // 1. Explicitly set
    $resolver = $this->resolver->forTenant('explicit-tenant');
    $reflection = new ReflectionClass($resolver);
    $method = $reflection->getMethod('resolveTenantId');
    $method->setAccessible(true);
    expect($method->invoke($resolver))->toBe('explicit-tenant');

    // 2. From callback
    config(['fulcrum.multi_tenancy.enabled' => true]);
    config(['fulcrum.multi_tenancy.tenant_resolver' => fn () => 'callback-tenant']);
    $resolver = new SettingResolver($this->evaluator, $this->bucketCalculator, $this->distributionStrategy);
    expect($method->invoke($resolver))->toBe('callback-tenant');

    // 3. From FulcrumContext
    config(['fulcrum.multi_tenancy.tenant_resolver' => null]);
    FulcrumContext::setTenantId('context-tenant');
    $resolver = new SettingResolver($this->evaluator, $this->bucketCalculator, $this->distributionStrategy);
    expect($method->invoke($resolver))->toBe('context-tenant');
});

test('it can set a setting value', function () {
    $setting = Setting::create([
        'key' => 'set.setting',
        'type' => SettingType::STRING,
    ]);

    $this->resolver->set('set.setting', 'new-value');

    expect($this->resolver->resolve('set.setting'))->toBe('new-value');
});

test('it throws exception when setting to set not found', function () {
    $this->expectException(\GaiaTools\FulcrumSettings\Exceptions\SettingNotFoundException::class);
    $this->resolver->set('non.existent', 'value');
});

test('it throws exception for invalid value type during set', function () {
    Setting::create([
        'key' => 'invalid.set',
        'type' => SettingType::INTEGER,
    ]);

    $this->expectException(\GaiaTools\FulcrumSettings\Exceptions\InvalidSettingValueException::class);
    $this->resolver->set('invalid.set', 'not-an-integer');
});

test('it does not dispatch event when telescope class is missing', function () {
    $GLOBALS['mock_telescope_missing'] = true;
    Event::fake();
    config(['fulcrum.telescope.enabled' => true]);

    Setting::create([
        'key' => 'missing.telescope.setting',
        'type' => SettingType::STRING,
    ]);

    $this->resolver->resolve('missing.telescope.setting');

    Event::assertNotDispatched(SettingResolved::class);
});

test('it resolves rollout variants', function () {
    Event::fake();
    $setting = Setting::create([
        'key' => 'rollout.setting',
        'type' => SettingType::STRING,
    ]);

    $rule = SettingRule::create([
        'setting_id' => $setting->id,
        'priority' => 1,
        'rollout_salt' => 'test-salt',
    ]);

    $variant1 = $rule->rolloutVariants()->create([
        'name' => 'v1',
        'weight' => 50000, // 50%
    ]);
    $variant1->value()->create([
        'valuable_type' => \GaiaTools\FulcrumSettings\Models\SettingRuleRolloutVariant::class,
        'valuable_id' => $variant1->id,
        'value' => 'value-v1',
    ]);

    $variant2 = $rule->rolloutVariants()->create([
        'name' => 'v2',
        'weight' => 50000, // 50%
    ]);
    $variant2->value()->create([
        'valuable_type' => \GaiaTools\FulcrumSettings\Models\SettingRuleRolloutVariant::class,
        'valuable_id' => $variant2->id,
        'value' => 'value-v2',
    ]);

    // Mock rule evaluation to pass
    $this->segmentDriver->shouldReceive('evaluate')->andReturn(true);

    // Identifier 'user-1' with 'test-salt' and 100000 precision
    $bucket = $this->bucketCalculator->calculate('user-1', 'test-salt', 100000);

    $expectedVariant = $bucket < 50000 ? 'value-v1' : 'value-v2';

    expect($this->resolver->resolve('rollout.setting', 'user-1'))->toBe($expectedVariant);
    expect($this->resolver->getLastCalculatedBucket())->toBe($bucket);

    Event::assertDispatched(VariantAssigned::class, function ($event) use ($bucket) {
        return $event->settingKey === 'rollout.setting'
            && $event->bucket === $bucket
            && $event->variantName === 'v1' || $event->variantName === 'v2';
    });

    Event::assertDispatched(SettingResolved::class, function ($event) {
        return $event->key === 'rollout.setting'
            && $event->source === 'rollout'
            && $event->rulesEvaluated === 1;
    });
});

test('it resolves rollout identifier from different scope types', function () {
    $setting = Setting::create([
        'key' => 'scope.setting',
        'type' => SettingType::STRING,
    ]);
    $rule = SettingRule::create(['setting_id' => $setting->id, 'rollout_salt' => 'salt']);
    $variant = $rule->rolloutVariants()->create(['name' => 'v', 'weight' => 100000]);
    $variant->value()->create([
        'valuable_type' => \GaiaTools\FulcrumSettings\Models\SettingRuleRolloutVariant::class,
        'valuable_id' => $variant->id,
        'value' => 'hit',
    ]);
    $this->segmentDriver->shouldReceive('evaluate')->andReturn(true);

    // Scalar scope
    expect($this->resolver->resolve('scope.setting', 'scalar-id'))->toBe('hit');

    // Array scope with id
    expect($this->resolver->resolve('scope.setting', ['id' => 'array-id']))->toBe('hit');

    // Object scope with id property
    $obj = new \stdClass;
    $obj->id = 'object-id';
    expect($this->resolver->resolve('scope.setting', $obj))->toBe('hit');

    // User from resolver
    $user = Mockery::mock(Authenticatable::class);
    $user->shouldReceive('getAuthIdentifier')->andReturn('user-id');
    expect($this->resolver->forUser($user)->resolve('scope.setting'))->toBe('hit');

    // Custom resolver
    config(['fulcrum.rollout.identifier_resolver' => fn ($s) => 'custom-'.$s]);
    expect($this->resolver->resolve('scope.setting', 'foo'))->toBe('hit');

    // No identifier found
    config(['fulcrum.rollout.identifier_resolver' => null]);
    expect($this->resolver->resolve('scope.setting', []))->toBeNull(); // Falls through because no ID
});

test('it respects rollout event config', function () {
    Event::fake();
    config(['fulcrum.rollout.fire_assignment_events' => false]);

    $setting = Setting::create(['key' => 'no.event.rollout', 'type' => SettingType::STRING]);
    $rule = SettingRule::create(['setting_id' => $setting->id]);
    $variant = $rule->rolloutVariants()->create(['name' => 'v', 'weight' => 100000]);
    $variant->value()->create([
        'valuable_type' => \GaiaTools\FulcrumSettings\Models\SettingRuleRolloutVariant::class,
        'valuable_id' => $variant->id,
        'value' => 'val',
    ]);
    $this->segmentDriver->shouldReceive('evaluate')->andReturn(true);

    $this->resolver->resolve('no.event.rollout', 'user-1');

    Event::assertNotDispatched(VariantAssigned::class);
});

test('it falls through if bucket exceeds cumulative weight', function () {
    $setting = Setting::create(['key' => 'fallthrough.rollout', 'type' => SettingType::STRING]);
    SettingValue::create([
        'valuable_type' => Setting::class,
        'valuable_id' => $setting->id,
        'value' => 'default',
    ]);
    $rule = SettingRule::create(['setting_id' => $setting->id, 'rollout_salt' => 'salt']);
    $rule->rolloutVariants()->create(['name' => 'v1', 'weight' => 1000]); // Only 1%
    $this->segmentDriver->shouldReceive('evaluate')->andReturn(true);

    // Force a high bucket value
    $mockCalculator = Mockery::mock(\GaiaTools\FulcrumSettings\Contracts\BucketCalculator::class);
    $mockCalculator->shouldReceive('calculate')->andReturn(50000); // 50%

    $resolver = new SettingResolver($this->evaluator, $mockCalculator, $this->distributionStrategy);
    expect($resolver->resolve('fallthrough.rollout', 'any-id'))->toBe('default');
});

test('it continues to next rule if rollout fails to select variant', function () {
    $setting = Setting::create(['key' => 'next.rule.rollout', 'type' => SettingType::STRING]);

    // Rule 1: Rollout that falls through (10% weight, 50% bucket)
    $rule1 = SettingRule::create(['setting_id' => $setting->id, 'priority' => 1, 'rollout_salt' => 'salt1']);
    $rule1->rolloutVariants()->create(['name' => 'v1', 'weight' => 10000]);

    // Rule 2: Direct value rule (priority 2)
    $rule2 = SettingRule::create(['setting_id' => $setting->id, 'priority' => 2]);
    SettingValue::create([
        'valuable_type' => SettingRule::class,
        'valuable_id' => $rule2->id,
        'value' => 'rule2-value',
    ]);

    $this->segmentDriver->shouldReceive('setUser')->andReturn($this->evaluator);
    $this->segmentDriver->shouldReceive('evaluate')->andReturn(true);

    $mockCalculator = Mockery::mock(\GaiaTools\FulcrumSettings\Contracts\BucketCalculator::class);
    $mockCalculator->shouldReceive('calculate')->andReturn(50000); // 50%

    $resolver = new SettingResolver($this->evaluator, $mockCalculator, $this->distributionStrategy);
    expect($resolver->resolve('next.rule.rollout', 'any-id'))->toBe('rule2-value');
});
