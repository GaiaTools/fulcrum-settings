<?php

use GaiaTools\FulcrumSettings\Contracts\SettingResolver;
use GaiaTools\FulcrumSettings\Services\CachedSettingResolver;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
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

test('cached values stay isolated when the tenant context changes', function () {
    $this->innerResolver->shouldReceive('resolve')->with('shared', null)->twice()
        ->andReturn('tenant-a-value', 'tenant-b-value');

    FulcrumContext::setTenantId('a');
    expect($this->cachedResolver->resolve('shared'))->toBe('tenant-a-value');
    FulcrumContext::setTenantId('b');
    expect($this->cachedResolver->resolve('shared'))->toBe('tenant-b-value');
    FulcrumContext::setTenantId('a');
    expect($this->cachedResolver->resolve('shared'))->toBe('tenant-a-value');
});

test('explicit tenants use distinct cache entries', function () {
    $a = Mockery::mock(SettingResolver::class);
    $b = Mockery::mock(SettingResolver::class);
    $this->innerResolver->shouldReceive('forTenant')->with('a')->andReturn($a);
    $this->innerResolver->shouldReceive('forTenant')->with('b')->andReturn($b);
    $a->shouldReceive('resolve')->with('shared', null)->once()->andReturn('a');
    $b->shouldReceive('resolve')->with('shared', null)->once()->andReturn('b');

    expect($this->cachedResolver->forTenant('a')->resolve('shared'))->toBe('a')
        ->and($this->cachedResolver->forTenant('b')->resolve('shared'))->toBe('b')
        ->and($this->cachedResolver->forTenant('a')->resolve('shared'))->toBe('a');
});

test('configured tenant resolver participates in cache isolation', function () {
    $tenant = 'a';
    config(['fulcrum.multi_tenancy.tenant_resolver' => function () use (&$tenant) {
        return $tenant;
    }]);
    $this->innerResolver->shouldReceive('resolve')->twice()->andReturn('a', 'b');
    expect($this->cachedResolver->resolve('shared'))->toBe('a');
    $tenant = 'b';
    expect($this->cachedResolver->resolve('shared'))->toBe('b');
});

test('scope types and delimiters cannot collide', function () {
    $this->innerResolver->shouldReceive('resolve')->with('key', 1)->once()->andReturn('integer');
    $this->innerResolver->shouldReceive('resolve')->with('key', '1')->once()->andReturn('string');
    $this->innerResolver->shouldReceive('resolve')->with('key:a', 'b')->once()->andReturn('first');
    $this->innerResolver->shouldReceive('resolve')->with('key', 'a:b')->once()->andReturn('second');
    expect($this->cachedResolver->resolve('key', 1))->toBe('integer')
        ->and($this->cachedResolver->resolve('key', '1'))->toBe('string')
        ->and($this->cachedResolver->resolve('key:a', 'b'))->toBe('first')
        ->and($this->cachedResolver->resolve('key', 'a:b'))->toBe('second');
});

test('custom targeting attributes participate in cache isolation', function () {
    $this->innerResolver->shouldReceive('resolve')->twice()->andReturn('basic', 'premium');
    FulcrumContext::set('plan', 'basic');
    expect($this->cachedResolver->resolve('shared'))->toBe('basic');
    FulcrumContext::set('plan', 'premium');
    expect($this->cachedResolver->resolve('shared'))->toBe('premium');
});

test('request targeting inputs participate in cache isolation', function () {
    $this->innerResolver->shouldReceive('resolve')->times(3)->andReturn('first', 'second', 'third');
    request()->server->set('REMOTE_ADDR', '192.0.2.1');
    request()->headers->set('User-Agent', 'first-browser');
    expect($this->cachedResolver->resolve('shared'))->toBe('first');
    request()->server->set('REMOTE_ADDR', '192.0.2.2');
    expect($this->cachedResolver->resolve('shared'))->toBe('second');
    request()->headers->set('User-Agent', 'second-browser');
    expect($this->cachedResolver->resolve('shared'))->toBe('third');
});

test('revealed values bypass cached results and are never stored', function () {
    $this->innerResolver->shouldReceive('resolve')->times(3)->andReturn('masked', 'secret', 'denied');
    expect($this->cachedResolver->resolve('shared'))->toBe('masked');
    FulcrumContext::reveal();
    expect($this->cachedResolver->resolve('shared'))->toBe('secret')
        ->and($this->cachedResolver->resolve('shared'))->toBe('denied');
    FulcrumContext::reveal(false);
    expect($this->cachedResolver->resolve('shared'))->toBe('masked');
});

test('object and unserializable contexts bypass caching', function () {
    Cache::shouldReceive('store')->never();
    $this->innerResolver->shouldReceive('resolve')->times(4)->andReturn('fresh');
    $scope = (object) ['id' => 1];
    expect($this->cachedResolver->resolve('shared', $scope))->toBe('fresh');
    $scope->id = 2;
    expect($this->cachedResolver->resolve('shared', $scope))->toBe('fresh');
    expect($this->cachedResolver->resolve('shared', ['callback' => fn () => true]))->toBe('fresh');
    FulcrumContext::set('callback', fn () => true);
    expect($this->cachedResolver->resolve('shared'))->toBe('fresh');
});

test('explicit user resolutions reuse results until the TTL expires', function () {
    $user = Mockery::mock(Authenticatable::class);
    $user->shouldReceive('getAuthIdentifier')->andReturn(1);
    $this->innerResolver->shouldReceive('forUser')->with($user)->andReturnSelf();
    $this->innerResolver->shouldReceive('resolve')->twice()->andReturn(true, false);
    $resolver = $this->cachedResolver->forUser($user);
    expect($resolver->resolve('shared'))->toBeTrue()
        ->and($resolver->resolve('shared'))->toBeTrue();
    $this->travel(3601)->seconds();
    expect($resolver->resolve('shared'))->toBeFalse();
});

test('authenticated resolutions reuse results and isolate different users and guests', function () {
    $a = Mockery::mock(Authenticatable::class);
    $a->shouldReceive('getAuthIdentifier')->andReturn(1);
    $b = Mockery::mock(Authenticatable::class);
    $b->shouldReceive('getAuthIdentifier')->andReturn(2);
    $this->innerResolver->shouldReceive('resolve')->times(3)->andReturn('a', 'b', 'guest');
    auth()->setUser($a);
    expect($this->cachedResolver->resolve('shared'))->toBe('a')
        ->and($this->cachedResolver->resolve('shared'))->toBe('a');
    auth()->setUser($b);
    expect($this->cachedResolver->resolve('shared'))->toBe('b');
    auth()->forgetUser();
    expect($this->cachedResolver->resolve('shared'))->toBe('guest');
    auth()->setUser($a);
    expect($this->cachedResolver->resolve('shared'))->toBe('a');
});

test('user scope takes precedence over the authenticated user in cache identity', function () {
    $a = Mockery::mock(Authenticatable::class);
    $a->shouldReceive('getAuthIdentifier')->andReturn(1);
    $b = Mockery::mock(Authenticatable::class);
    $b->shouldReceive('getAuthIdentifier')->andReturn(2);
    $this->innerResolver->shouldReceive('resolve')->with('shared', $a)->once()->andReturn('a');
    auth()->setUser($b);
    expect($this->cachedResolver->resolve('shared', $a))->toBe('a');
    auth()->forgetUser();
    expect($this->cachedResolver->resolve('shared', $a))->toBe('a');
});

test('explicit user takes precedence over authentication with scalar scopes', function () {
    $a = Mockery::mock(Authenticatable::class);
    $a->shouldReceive('getAuthIdentifier')->andReturn(1);
    $b = Mockery::mock(Authenticatable::class);
    $b->shouldReceive('getAuthIdentifier')->andReturn(2);
    $this->innerResolver->shouldReceive('forUser')->with($a)->andReturnSelf();
    $this->innerResolver->shouldReceive('resolve')->with('shared', 'scope')->once()->andReturn('a');
    $resolver = $this->cachedResolver->forUser($a);
    auth()->setUser($b);
    expect($resolver->resolve('shared', 'scope'))->toBe('a');
    auth()->forgetUser();
    expect($resolver->resolve('shared', 'scope'))->toBe('a');
});

test('users without a stable identifier bypass caching', function () {
    Cache::shouldReceive('store')->never();
    $user = Mockery::mock(Authenticatable::class);
    $user->shouldReceive('getAuthIdentifier')->andReturn(null);
    auth()->setUser($user);
    $this->innerResolver->shouldReceive('resolve')->twice()->andReturn(true, false);
    expect($this->cachedResolver->resolve('shared'))->toBeTrue()
        ->and($this->cachedResolver->resolve('shared'))->toBeFalse();
});

test('it delegates the multi tenancy capability to the inner resolver', function () {
    $this->innerResolver->shouldReceive('isMultiTenancyEnabled')->once()->andReturn(true);
    expect($this->cachedResolver->isMultiTenancyEnabled())->toBeTrue();
});

test('grouped resolutions qualify local keys and leave qualified keys intact', function () {
    $this->innerResolver->shouldReceive('forGroup')->with('billing')->andReturnSelf();
    $this->innerResolver->shouldReceive('resolve')->with('billing.currency', null)->once()->andReturn('USD');
    $this->innerResolver->shouldReceive('resolve')->with('general.language', null)->once()->andReturn('en');
    $resolver = $this->cachedResolver->forGroup('billing');
    expect($resolver->resolve('currency'))->toBe('USD')
        ->and($resolver->resolve('general.language'))->toBe('en');
});

test('empty group names are rejected', function () {
    expect(fn () => $this->cachedResolver->group(" .\t\n"))
        ->toThrow(InvalidArgumentException::class, 'Group name cannot be empty.');
});

test('constructor user identifiers isolate cached results', function () {
    $a = new CachedSettingResolver($this->innerResolver, userIdentifier: 'a');
    $b = new CachedSettingResolver($this->innerResolver, userIdentifier: 'b');
    $this->innerResolver->shouldReceive('resolve')->twice()->andReturn('a', 'b');
    expect($a->resolve('shared'))->toBe('a')
        ->and($b->resolve('shared'))->toBe('b')
        ->and($a->resolve('shared'))->toBe('a');
});

test('global caching ignores ambient tenant context when multi tenancy is disabled', function () {
    config(['fulcrum.multi_tenancy.enabled' => false]);
    $this->innerResolver->shouldReceive('resolve')->once()->andReturn('global');
    FulcrumContext::setTenantId('a');
    expect($this->cachedResolver->resolve('shared'))->toBe('global');
    FulcrumContext::setTenantId('b');
    expect($this->cachedResolver->resolve('shared'))->toBe('global');
});

test('nested user scopes reuse stable identities without serializing their objects', function () {
    $user = Mockery::mock(Authenticatable::class);
    $user->shouldReceive('getAuthIdentifier')->andReturn(1);
    $scope = ['user' => $user, 'plan' => 'premium'];
    $this->innerResolver->shouldReceive('resolve')->with('shared', $scope)->once()->andReturn('premium');
    expect($this->cachedResolver->resolve('shared', $scope))->toBe('premium')
        ->and($this->cachedResolver->resolve('shared', $scope))->toBe('premium');
});
