<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Contracts\TenantResolver;
use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use Illuminate\Support\Facades\Config;

test('tenant scope filters by tenant_id when enabled', function () {
    Config::set('fulcrum.multi_tenancy.enabled', true);
    FulcrumContext::setTenantId('tenant-1');

    $setting1 = Setting::create(['key' => 's1', 'type' => SettingType::STRING, 'tenant_id' => 'tenant-1']);
    $setting2 = Setting::create(['key' => 's2', 'type' => SettingType::STRING, 'tenant_id' => 'tenant-2']);
    $setting3 = Setting::create(['key' => 's3', 'type' => SettingType::STRING, 'tenant_id' => null]);

    $results = Setting::all();

    expect($results)->toHaveCount(2)
        ->and($results->pluck('key'))->toContain('s1', 's3')
        ->and($results->pluck('key'))->not->toContain('s2');
});

test('tenant scope handles null tenant_id when enabled', function () {
    Config::set('fulcrum.multi_tenancy.enabled', true);
    FulcrumContext::setTenantId(null);

    $setting1 = Setting::create(['key' => 's1', 'type' => SettingType::STRING, 'tenant_id' => 'tenant-1']);
    $setting2 = Setting::create(['key' => 's2', 'type' => SettingType::STRING, 'tenant_id' => null]);

    $results = Setting::all();

    expect($results)->toHaveCount(1)
        ->and($results->first()->key)->toBe('s2');
});

test('tenant scope uses class-based resolver', function () {
    Config::set('fulcrum.multi_tenancy.enabled', true);

    $resolver = new class implements TenantResolver
    {
        public function resolve(): ?string
        {
            return 'custom-tenant';
        }
    };

    $resolverClass = get_class($resolver);
    app()->instance($resolverClass, $resolver);
    Config::set('fulcrum.multi_tenancy.tenant_resolver', $resolverClass);

    Setting::create(['key' => 's1', 'type' => SettingType::STRING, 'tenant_id' => 'custom-tenant']);
    Setting::create(['key' => 's2', 'type' => SettingType::STRING, 'tenant_id' => 'other-tenant']);

    $results = Setting::all();

    expect($results)->toHaveCount(1)
        ->and($results->first()->key)->toBe('s1');
});

test('tenant scope uses callable resolver', function () {
    Config::set('fulcrum.multi_tenancy.enabled', true);
    Config::set('fulcrum.multi_tenancy.tenant_resolver', fn () => 'callable-tenant');

    Setting::create(['key' => 's1', 'type' => SettingType::STRING, 'tenant_id' => 'callable-tenant']);
    Setting::create(['key' => 's2', 'type' => SettingType::STRING, 'tenant_id' => 'other-tenant']);

    $results = Setting::all();

    expect($results)->toHaveCount(1)
        ->and($results->first()->key)->toBe('s1');
});

test('tenant scope falls back to FulcrumContext when resolver is invalid', function () {
    Config::set('fulcrum.multi_tenancy.enabled', true);
    Config::set('fulcrum.multi_tenancy.tenant_resolver', 'InvalidResolverClass');
    FulcrumContext::setTenantId('context-tenant');

    Setting::create(['key' => 's1', 'type' => SettingType::STRING, 'tenant_id' => 'context-tenant']);

    expect(Setting::all())->toHaveCount(1);
});

test('tenant scope handles resolver that does not implement TenantResolver', function () {
    Config::set('fulcrum.multi_tenancy.enabled', true);

    $resolver = new class
    {
        public function resolve(): ?string
        {
            return 'should-not-be-used';
        }
    };

    app()->instance('InvalidTenantResolver', $resolver);
    Config::set('fulcrum.multi_tenancy.tenant_resolver', 'InvalidTenantResolver');
    FulcrumContext::setTenantId('context-tenant');

    Setting::create(['key' => 's1', 'type' => SettingType::STRING, 'tenant_id' => 'context-tenant']);

    expect(Setting::all())->toHaveCount(1);
});

test('tenant scope is ignored when disabled', function () {
    Config::set('fulcrum.multi_tenancy.enabled', false);
    FulcrumContext::setTenantId('tenant-1');

    Setting::create(['key' => 's1', 'type' => SettingType::STRING, 'tenant_id' => 'tenant-1']);
    Setting::create(['key' => 's2', 'type' => SettingType::STRING, 'tenant_id' => 'tenant-2']);

    expect(Setting::all())->toHaveCount(2);
});

test('withoutTenant macro bypasses scope', function () {
    Config::set('fulcrum.multi_tenancy.enabled', true);
    FulcrumContext::setTenantId('tenant-1');

    Setting::create(['key' => 's1', 'type' => SettingType::STRING, 'tenant_id' => 'tenant-1']);
    Setting::create(['key' => 's2', 'type' => SettingType::STRING, 'tenant_id' => 'tenant-2']);

    expect(Setting::withoutTenant()->get())->toHaveCount(2);
});
