<?php

use GaiaTools\FulcrumSettings\Contracts\SettingResolver;
use GaiaTools\FulcrumSettings\Services\CachedSettingResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->innerResolver = Mockery::mock(SettingResolver::class);
    $this->cachedResolver = new CachedSettingResolver(
        $this->innerResolver,
        true, // enabled
        'test_prefix',
        3600 // ttl
    );
});

test('it resolves from cache and calls inner resolver when cache is empty', function () {
    Cache::shouldReceive('store')->andReturnSelf();
    Cache::shouldReceive('remember')->once()->andReturnUsing(function ($key, $ttl, $callback) {
        return $callback();
    });

    $this->innerResolver->shouldReceive('resolve')->with('some_key', null)->once()->andReturn('fresh_value');

    $value = $this->cachedResolver->resolve('some_key');

    expect($value)->toBe('fresh_value');
});

test('it resolves from cache and does NOT call inner resolver when cache is hit', function () {
    Cache::shouldReceive('store')->andReturnSelf();
    Cache::shouldReceive('remember')->once()->andReturn('cached_value');

    $this->innerResolver->shouldNotReceive('resolve');

    $value = $this->cachedResolver->resolve('some_key');

    expect($value)->toBe('cached_value');
});

test('it bypasses cache when disabled', function () {
    $this->cachedResolver = new CachedSettingResolver($this->innerResolver, false);

    $this->innerResolver->shouldReceive('resolve')->with('some_key', null)->andReturn('fresh_value');

    $value = $this->cachedResolver->resolve('some_key');

    expect($value)->toBe('fresh_value');
});

test('it handles isActive', function () {
    Cache::shouldReceive('store')->andReturnSelf();
    Cache::shouldReceive('remember')->andReturn(true);

    expect($this->cachedResolver->isActive('some_key'))->toBeTrue();
});

test('it handles get with default', function () {
    Cache::shouldReceive('store')->andReturnSelf();
    Cache::shouldReceive('remember')->andReturn(null);

    expect($this->cachedResolver->get('some_key', 'default'))->toBe('default');
});

test('it handles set by delegating and not touching cache', function () {
    $this->innerResolver->shouldReceive('set')->with('some_key', 'new_value')->once();

    $this->cachedResolver->set('some_key', 'new_value');
});

test('it clones with forUser', function () {
    $user = Mockery::mock(Authenticatable::class);
    $user->shouldReceive('getAuthIdentifier')->andReturn(123);
    $newInner = Mockery::mock(SettingResolver::class);
    $this->innerResolver->shouldReceive('forUser')->with($user)->andReturn($newInner);

    $cloned = $this->cachedResolver->forUser($user);

    expect($cloned)->not->toBe($this->cachedResolver);
    expect($cloned)->toBeInstanceOf(CachedSettingResolver::class);
});

test('it clones with forTenant', function () {
    $tenantId = 'tenant-1';
    $newInner = Mockery::mock(SettingResolver::class);
    $this->innerResolver->shouldReceive('forTenant')->with($tenantId)->andReturn($newInner);

    $cloned = $this->cachedResolver->forTenant($tenantId);

    expect($cloned)->not->toBe($this->cachedResolver);
    expect($cloned)->toBeInstanceOf(CachedSettingResolver::class);
});

test('it clones with forGroup', function () {
    $group = 'billing';
    $newInner = Mockery::mock(SettingResolver::class);
    $this->innerResolver->shouldReceive('forGroup')->with($group)->andReturn($newInner);

    $cloned = $this->cachedResolver->forGroup($group);

    expect($cloned)->not->toBe($this->cachedResolver);
    expect($cloned)->toBeInstanceOf(CachedSettingResolver::class);
});

test('it builds grouped resolver', function () {
    $group = 'my_links';
    $newInner = Mockery::mock(SettingResolver::class);
    $newInner->shouldReceive('forGroup')->with($group)->andReturn($newInner);
    $newInner->shouldReceive('getGroupKeys')->with($group)->andReturn([]);
    $this->innerResolver->shouldReceive('forGroup')->with($group)->andReturn($newInner);
    $this->innerResolver->shouldReceive('getGroupKeys')->with($group)->andReturn([]);

    $grouped = $this->cachedResolver->group($group);

    expect($grouped->all())->toBe([]);
});
