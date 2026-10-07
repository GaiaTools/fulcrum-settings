<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Contracts\SettingResolver;
use GaiaTools\FulcrumSettings\Database\Migrations\SettingMigration;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use GaiaTools\FulcrumSettings\Support\MaskedValue;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    config(['fulcrum.cache.enabled' => true, 'cache.default' => 'array']);
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
