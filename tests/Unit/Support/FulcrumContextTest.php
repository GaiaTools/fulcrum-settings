<?php

use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use Illuminate\Support\Facades\App;

beforeEach(function () {
    FulcrumContext::clear();
    config(['fulcrum.immutability.env_flag' => false]);
    config(['fulcrum.immutability.cli_flag' => 'force']);
});

test('it can set and get attributes', function () {
    FulcrumContext::set('key1', 'value1');
    FulcrumContext::set('key2', ['a' => 1]);

    expect(FulcrumContext::get('key1'))->toBe('value1');
    expect(FulcrumContext::get('key2'))->toBe(['a' => 1]);
    expect(FulcrumContext::get('non-existent', 'default'))->toBe('default');
});

test('it can get all attributes', function () {
    FulcrumContext::set('key1', 'value1');
    FulcrumContext::set('key2', 'value2');

    expect(FulcrumContext::all())->toBe([
        'key1' => 'value1',
        'key2' => 'value2',
    ]);
});

test('it can clear the context', function () {
    FulcrumContext::force(true);
    FulcrumContext::setTenantId('tenant-1');
    FulcrumContext::setGroup('general');
    FulcrumContext::set('key1', 'value1');

    FulcrumContext::clear();

    expect(FulcrumContext::shouldForce())->toBeFalse();
    expect(FulcrumContext::getTenantId())->toBeNull();
    expect(FulcrumContext::getGroup())->toBeNull();
    expect(FulcrumContext::all())->toBeEmpty();
});

test('it can set and get forced state', function () {
    expect(FulcrumContext::shouldForce())->toBeFalse();

    FulcrumContext::force(true);
    expect(FulcrumContext::shouldForce())->toBeTrue();

    FulcrumContext::force(false);
    expect(FulcrumContext::shouldForce())->toBeFalse();
});

test('it can set and get tenant id', function () {
    expect(FulcrumContext::getTenantId())->toBeNull();

    FulcrumContext::setTenantId('tenant-123');
    expect(FulcrumContext::getTenantId())->toBe('tenant-123');

    FulcrumContext::setTenantId(null);
    expect(FulcrumContext::getTenantId())->toBeNull();
});

test('it can set and get group', function () {
    expect(FulcrumContext::getGroup())->toBeNull();

    FulcrumContext::setGroup('billing');
    expect(FulcrumContext::getGroup())->toBe('billing');

    FulcrumContext::setGroup(null);
    expect(FulcrumContext::getGroup())->toBeNull();
});

test('it should force when env flag is set', function () {
    config(['fulcrum.immutability.env_flag' => true]);
    expect(FulcrumContext::shouldForce())->toBeTrue();
});

test('it should force when cli flag is present', function () {
    // Mock running in console
    App::shouldReceive('runningInConsole')->andReturn(true);

    // Backup argv
    $originalArgv = $_SERVER['argv'] ?? [];

    // Test exact flag
    $_SERVER['argv'] = ['artisan', '--force'];
    expect(FulcrumContext::shouldForce())->toBeTrue();

    // Test flag with value
    $_SERVER['argv'] = ['artisan', '--force=true'];
    expect(FulcrumContext::shouldForce())->toBeTrue();

    // Restore argv
    $_SERVER['argv'] = $originalArgv;
});

test('it should NOT force when cli flag is missing', function () {
    App::shouldReceive('runningInConsole')->andReturn(true);

    $originalArgv = $_SERVER['argv'] ?? [];
    $_SERVER['argv'] = ['artisan', '--other-flag'];

    expect(FulcrumContext::shouldForce())->toBeFalse();

    $_SERVER['argv'] = $originalArgv;
});

test('it should respect custom cli flag name', function () {
    App::shouldReceive('runningInConsole')->andReturn(true);
    config(['fulcrum.immutability.cli_flag' => 'custom-force']);

    $originalArgv = $_SERVER['argv'] ?? [];
    $_SERVER['argv'] = ['artisan', '--custom-force'];

    expect(FulcrumContext::shouldForce())->toBeTrue();

    $_SERVER['argv'] = $originalArgv;
});

test('it handles missing argv', function () {
    App::shouldReceive('runningInConsole')->andReturn(true);

    $originalArgv = $_SERVER['argv'] ?? null;
    unset($_SERVER['argv']);

    expect(FulcrumContext::shouldForce())->toBeFalse();

    if ($originalArgv !== null) {
        $_SERVER['argv'] = $originalArgv;
    }
});
