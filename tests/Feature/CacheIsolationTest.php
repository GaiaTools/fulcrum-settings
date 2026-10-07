<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Contracts\SettingResolver;
use GaiaTools\FulcrumSettings\Contracts\TenantResolver;
use GaiaTools\FulcrumSettings\Database\Migrations\SettingMigration;
use GaiaTools\FulcrumSettings\Facades\Fulcrum;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use GaiaTools\FulcrumSettings\Support\MaskedValue;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    config(['fulcrum.cache.enabled' => true, 'cache.default' => 'array']);
    app()->forgetInstance(SettingResolver::class);
    Fulcrum::clearResolvedInstance(SettingResolver::class);
});

test('real tenant settings remain isolated with caching enabled', function () {
    $migration = new class extends SettingMigration
    {
        public function up(): void
        {
            $this->createSetting('cached_shared')->type('string')->default('global')->save();
            $this->createSetting('cached_shared')->forTenant('a')->type('string')->default('tenant-a')->save();
            $this->createSetting('cached_shared')->forTenant('b')->type('string')->default('tenant-b')->save();
        }
    };
    $migration->up();
    $resolver = app(SettingResolver::class);
    expect($resolver->resolve('cached_shared'))->toBe('global')
        ->and($resolver->forTenant('a')->resolve('cached_shared'))->toBe('tenant-a')
        ->and($resolver->forTenant('b')->resolve('cached_shared'))->toBe('tenant-b')
        ->and($resolver->resolve('cached_shared'))->toBe('global');
});

test('cached masked settings reevaluate authorization on every revealed read', function () {
    $migration = new class extends SettingMigration
    {
        public function up(): void
        {
            $this->createSetting('cached_secret')->type('string')->default('secret')->masked()->save();
        }
    };
    $migration->up();
    App::shouldReceive('runningInConsole')->andReturn(false);
    $allowed = true;
    Gate::define('viewSettingValue', function (?Authenticatable $user) use (&$allowed) {
        return $allowed;
    });
    $resolver = app(SettingResolver::class);
    expect($resolver->resolve('cached_secret'))->toBeInstanceOf(MaskedValue::class);
    $resolver->reveal();
    expect($resolver->resolve('cached_secret'))->toBe('secret');
    $allowed = false;
    expect($resolver->resolve('cached_secret'))->toBeInstanceOf(MaskedValue::class);
    FulcrumContext::reveal(false);
    expect($resolver->resolve('cached_secret'))->toBeInstanceOf(MaskedValue::class);
});

test('plain guest cache hits reuse dependency metadata without database queries', function () {
    $setting = Setting::create(['key' => 'plain', 'type' => 'string']);
    $setting->defaultValue()->create(['value' => 'shared']);
    $resolver = app(SettingResolver::class);
    request()->server->set('REMOTE_ADDR', '192.0.2.1');
    request()->headers->set('User-Agent', 'Chrome');
    expect($resolver->resolve('plain'))->toBe('shared');
    $connection = DB::connection();
    $connection->enableQueryLog();
    $connection->flushQueryLog();
    request()->server->set('REMOTE_ADDR', '192.0.2.2');
    request()->headers->set('User-Agent', 'Firefox');
    expect($resolver->resolve('plain'))->toBe('shared')
        ->and($connection->getQueryLog())->toBe([]);
});

test('request dependencies are discovered from every condition type and support overrides', function () {
    $setting = Setting::create(['key' => 'dependencies', 'type' => 'string']);
    $rule = $setting->rules()->create(['name' => 'target', 'priority' => 1]);
    $resolver = app(GaiaTools\FulcrumSettings\Services\SettingResolver::class);
    expect($resolver->requestDependencies('missing'))->toBe([])
        ->and($resolver->requestDependencies('dependencies'))->toBe([]);
    foreach (['user', 'date_time'] as $type) {
        $rule->conditions()->create(['type' => $type, 'attribute' => 'scope', 'operator' => 'equals', 'value' => 'x']);
    }
    expect($resolver->requestDependencies('dependencies'))->toBe([]);
    $rule->conditions()->create(['type' => 'geocoding', 'attribute' => 'ip', 'operator' => 'equals', 'value' => 'x']);
    expect($resolver->requestDependencies('dependencies'))->toBe(['ip']);
    $rule->conditions()->create(['type' => 'user_agent', 'attribute' => 'browser', 'operator' => 'equals', 'value' => 'x']);
    expect($resolver->requestDependencies('dependencies'))->toBe(['ip', 'user_agent']);
    config(['fulcrum.cache.request_dependencies' => ['geocoding' => [], 'user_agent' => ['user_agent', 'invalid', 123]]]);
    expect($resolver->requestDependencies('dependencies'))->toBe(['user_agent']);
    $rule->conditions()->create(['type' => 'custom', 'attribute' => 'custom', 'operator' => 'equals', 'value' => 'x']);
    expect($resolver->requestDependencies('dependencies'))->toBe(['user_agent', 'ip']);
    config(['fulcrum.cache.request_dependencies' => ['custom' => 'invalid']]);
    expect($resolver->requestDependencies('dependencies'))->toBe(['ip', 'user_agent']);
    config(['fulcrum.cache.request_dependencies' => null]);
    expect($resolver->requestDependencies('dependencies'))->toBe(['ip', 'user_agent']);
});

test('tenant zero is isolated from the ambient tenant in queries and cached values', function () {
    $zero = Setting::create(['key' => 'zero', 'type' => 'string', 'tenant_id' => '0']);
    FulcrumContext::setTenantId('0');
    $zero->defaultValue()->create(['value' => 'zero-value']);
    $ambient = Setting::create(['key' => 'zero', 'type' => 'string', 'tenant_id' => 'ambient']);
    FulcrumContext::setTenantId('ambient');
    $ambient->defaultValue()->create(['value' => 'ambient-value']);
    $resolver = app(SettingResolver::class);
    expect($resolver->resolve('zero'))->toBe('ambient-value')
        ->and($resolver->forTenant('0')->resolve('zero'))->toBe('zero-value');
    FulcrumContext::setTenantId('0');
    expect($resolver->resolve('zero'))->toBe('zero-value')
        ->and(Setting::all()->pluck('tenant_id')->all())->toBe(['0']);
});

test('class tenant resolvers provide the same tenant identity for caching and resolution', function () {
    $tenantResolver = new class implements TenantResolver
    {
        public function resolve(): ?string
        {
            return '0';
        }
    };
    app()->instance($tenantResolver::class, $tenantResolver);
    config(['fulcrum.multi_tenancy.tenant_resolver' => $tenantResolver::class]);
    $setting = Setting::create(['key' => 'class-tenant', 'type' => 'string', 'tenant_id' => '0']);
    $setting->defaultValue()->create(['value' => 'zero']);
    $base = app(GaiaTools\FulcrumSettings\Services\SettingResolver::class);
    expect($base->currentTenantId())->toBe('0')
        ->and(app(SettingResolver::class)->resolve('class-tenant'))->toBe('zero');
});

test('Chrome targeted flags return false when the same user switches to Firefox', function () {
    $setting = Setting::create(['key' => 'chrome-only', 'type' => 'boolean']);
    $setting->defaultValue()->create(['value' => false]);
    $rule = $setting->rules()->create(['name' => 'chrome', 'priority' => 1]);
    $rule->value()->create(['value' => true]);
    $rule->conditions()->create(['type' => 'user_agent', 'attribute' => 'browser', 'operator' => 'equals', 'value' => 'Chrome']);
    $user = (new User)->forceFill(['id' => 7]);
    auth()->setUser($user);
    $resolver = app(SettingResolver::class);
    request()->headers->set('User-Agent', 'Mozilla/5.0 Chrome/130.0.0.0 Safari/537.36');
    expect($resolver->isActive('chrome-only'))->toBeTrue();
    request()->headers->set('User-Agent', 'Mozilla/5.0 Firefox/130.0');
    expect($resolver->isActive('chrome-only'))->toBeFalse();
    request()->headers->set('User-Agent', 'Mozilla/5.0 Chrome/130.0.0.0 Safari/537.36');
    expect($resolver->isActive('chrome-only'))->toBeTrue();
});

test('explicit tenant zero uses its own rule dependencies despite a different ambient tenant', function () {
    FulcrumContext::setTenantId('0');
    $setting = Setting::create(['key' => 'zero-device', 'type' => 'boolean', 'tenant_id' => '0']);
    $setting->defaultValue()->create(['value' => false]);
    $rule = $setting->rules()->create(['name' => 'chrome', 'priority' => 1]);
    $rule->value()->create(['value' => true]);
    $rule->conditions()->create(['type' => 'user_agent', 'attribute' => 'browser', 'operator' => 'equals', 'value' => 'Chrome']);
    FulcrumContext::setTenantId('ambient');
    request()->headers->set('User-Agent', 'Chrome/130.0');
    expect(app(GaiaTools\FulcrumSettings\Services\SettingResolver::class)->forTenant('0')->requestDependencies('zero-device'))->toBe(['user_agent'])
        ->and(app(SettingResolver::class)->forTenant('0')->isActive('zero-device'))->toBeTrue();
    request()->headers->set('User-Agent', 'Firefox/130.0');
    expect(app(SettingResolver::class)->forTenant('0')->isActive('zero-device'))->toBeFalse();
});
