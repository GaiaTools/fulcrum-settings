<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Attributes\SettingProperty;
use GaiaTools\FulcrumSettings\Contracts\SettingResolver;
use GaiaTools\FulcrumSettings\Support\Settings\SettingsContextState;
use Illuminate\Contracts\Auth\Authenticatable;

test('context state configures resolver based on user and tenant', function () {
    $resolver = Mockery::mock(SettingResolver::class);
    $user = Mockery::mock(Authenticatable::class);

    $resolver->shouldReceive('forUser')->once()->with($user)->andReturn($resolver);
    $resolver->shouldReceive('forTenant')->once()->with('tenant-1')->andReturn($resolver);

    $state = new SettingsContextState($resolver, $user, 'tenant-1');
    $config = new SettingProperty(key: 'test.tenant', tenantScoped: true);

    expect($state->configuredResolver($config))->toBe($resolver);
});

test('context state returns custom context before user', function () {
    $resolver = Mockery::mock(SettingResolver::class);
    $user = Mockery::mock(Authenticatable::class);
    $context = ['foo' => 'bar'];

    $state = new SettingsContextState($resolver, $user, null, $context);

    expect($state->context())->toBe($context);
});

test('context state returns user when no custom context is set', function () {
    $resolver = Mockery::mock(SettingResolver::class);
    $user = Mockery::mock(Authenticatable::class);

    $state = new SettingsContextState($resolver, $user);

    expect($state->context())->toBe($user);
});

test('context state runs callbacks with timezone bound in container', function () {
    $resolver = Mockery::mock(SettingResolver::class);
    $state = new SettingsContextState($resolver, null, null, null, 'UTC');

    $result = $state->runWithTimezone(function () {
        expect(app()->bound('fulcrum.context.timezone'))->toBeTrue();
        expect(app('fulcrum.context.timezone'))->toBe('UTC');

        return 'ok';
    });

    expect($result)->toBe('ok');
    expect(app()->bound('fulcrum.context.timezone'))->toBeFalse();
});

test('context state clones with new context values', function () {
    $resolver = Mockery::mock(SettingResolver::class);
    $state = new SettingsContextState($resolver, null, null, null, 'UTC');

    $user = Mockery::mock(Authenticatable::class);
    $clone = $state->cloneWith($user, 'tenant-2', ['a' => 1], 'America/New_York');

    expect($clone->context())->toBe(['a' => 1]);

    $result = $clone->runWithTimezone(fn () => app('fulcrum.context.timezone'));
    expect($result)->toBe('America/New_York');
});
