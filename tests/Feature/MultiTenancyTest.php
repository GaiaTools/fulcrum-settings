<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Feature;

use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['fulcrum.multi_tenancy.enabled' => true]);
    FulcrumContext::setTenantId(null);
});

test('setting all returns only global settings when no tenant is set', function () {
    // Create settings for different tenants
    Setting::create([
        'key' => 'global-setting',
        'type' => SettingType::BOOLEAN,
        'tenant_id' => null,
    ]);

    Setting::create([
        'key' => 'tenant-1-setting',
        'type' => SettingType::BOOLEAN,
        'tenant_id' => 'tenant-1',
    ]);

    Setting::create([
        'key' => 'tenant-2-setting',
        'type' => SettingType::BOOLEAN,
        'tenant_id' => 'tenant-2',
    ]);

    // When no tenant is set, only global settings (tenant_id IS NULL) should be returned
    $allSettings = Setting::all();

    expect($allSettings)->toHaveCount(1)
        ->and($allSettings->first()->key)->toBe('global-setting');
});

test('setting rules are also tenant scoped', function () {
    // Disable tenant scope for creation or set the tenant
    FulcrumContext::setTenantId('tenant-1');

    $tenant1Setting = Setting::create([
        'key' => 'tenant-1-setting',
        'type' => SettingType::BOOLEAN,
        'tenant_id' => 'tenant-1',
    ]);

    $tenant1Setting->rules()->create([
        'name' => 'Rule 1',
        'priority' => 1,
    ]);

    FulcrumContext::setTenantId('tenant-2');

    $tenant2Setting = Setting::create([
        'key' => 'tenant-2-setting',
        'type' => SettingType::BOOLEAN,
        'tenant_id' => 'tenant-2',
    ]);

    $tenant2Setting->rules()->create([
        'name' => 'Rule 2',
        'priority' => 1,
    ]);

    // Set current tenant to tenant-1
    FulcrumContext::setTenantId('tenant-1');

    // SettingRule::all() should only return Rule 1
    $rules = \GaiaTools\FulcrumSettings\Models\SettingRule::all();

    expect($rules->contains('name', 'Rule 1'))->toBeTrue()
        ->and($rules->contains('name', 'Rule 2'))->toBeFalse();

    // Even if we try to query Rule 2 directly, it should be filtered out
    $rule2 = \GaiaTools\FulcrumSettings\Models\SettingRule::where('name', 'Rule 2')->first();
    expect($rule2)->toBeNull();
});
