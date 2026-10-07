<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Exceptions\UnsupportedCacheStoreException;
use GaiaTools\FulcrumSettings\Support\Cache\CacheInvalidator;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

test('generation is stable until invalidation and isolated by prefix and cache store', function () {
    config(['cache.stores.secondary' => ['driver' => 'array']]);
    $a = new CacheInvalidator('a', 'array');
    $b = new CacheInvalidator('b', 'array');
    $secondary = new CacheInvalidator('a', 'secondary');
    $initial = $a->generation();
    $other = $b->generation();
    $otherStore = $secondary->generation();
    expect($a->generation())->toBe($initial);
    $a->invalidate();
    expect($a->generation())->not->toBe($initial)
        ->and($b->generation())->toBe($other)
        ->and($secondary->generation())->toBe($otherStore);
});

test('configured invalidator honors cache settings and falls back for null settings', function () {
    config(['fulcrum.cache.prefix' => 'custom', 'fulcrum.cache.store' => 'array']);
    $generation = CacheInvalidator::configured()->generation();
    expect(Cache::store('array')->get('custom:generation'))->toBe($generation);
    config(['fulcrum.cache.prefix' => null, 'fulcrum.cache.store' => null]);
    $fallback = CacheInvalidator::configured()->generation();
    expect(Cache::get('fulcrum:generation'))->toBe($fallback);
});

test('generation initialization preserves a concurrent writers generation', function () {
    $store = new class extends ArrayStore
    {
        public int $reads = 0;

        public function get($key)
        {
            return ++$this->reads === 1 ? null : 'concurrent-generation';
        }
    };
    Cache::shouldReceive('store')->with(null)->andReturn(new Repository($store));
    expect((new CacheInvalidator)->generation())->toBe('concurrent-generation');
});

test('stores without locking cannot initialize or rotate generations', function () {
    $store = Mockery::mock(Store::class);
    $store->shouldReceive('get')->with('fulcrum:generation')->andReturn(null);
    Cache::shouldReceive('store')->with(null)->andReturn(new Repository($store));
    $invalidator = new CacheInvalidator;
    expect(fn () => $invalidator->generation())->toThrow(UnsupportedCacheStoreException::class, 'supporting locks')
        ->and(fn () => $invalidator->invalidate())->toThrow(UnsupportedCacheStoreException::class, 'supporting locks');
});

test('automatic invalidation reports lock timeouts without throwing', function () {
    $lock = Mockery::mock(Lock::class);
    $lock->shouldReceive('block')->with(5)->once()->andThrow(new LockTimeoutException);
    $store = Mockery::mock(ArrayStore::class);
    $store->shouldReceive('lock')->with('fulcrum:generation:lock', 10)->andReturn($lock);
    Cache::shouldReceive('store')->with(null)->andReturn(new Repository($store));
    $handler = Mockery::mock(ExceptionHandler::class);
    $handler->shouldReceive('report')->once()->with(Mockery::type(LockTimeoutException::class));
    app()->instance(ExceptionHandler::class, $handler);
    (new CacheInvalidator)->invalidateAfterCommit(DB::connection());
});
