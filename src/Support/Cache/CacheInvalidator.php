<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\Cache;

use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CacheInvalidator
{
    public function __construct(protected string $prefix = 'fulcrum', protected ?string $store = null) {}

    public static function configured(): self
    {
        $prefix = config('fulcrum.cache.prefix');
        $store = config('fulcrum.cache.store');

        return new self(is_string($prefix) ? $prefix : 'fulcrum', is_string($store) ? $store : null);
    }

    public function generation(): string
    {
        $cache = Cache::store($this->store);
        $key = $this->prefix.':generation';
        $generation = $cache->get($key);
        if (is_string($generation)) {
            return $generation;
        }

        // Serialize initialization with invalidation so a late cache miss cannot
        // overwrite a generation published by a concurrent writer.
        return $this->withLock(function () use ($cache, $key): string {
            $generation = $cache->get($key);
            if (! is_string($generation)) {
                $generation = (string) Str::uuid();
                $cache->forever($key, $generation);
            }

            return $generation;
        });
    }

    public function invalidate(): void
    {
        $cache = Cache::store($this->store);
        $key = $this->prefix.':generation';
        $this->withLock(function () use ($cache, $key): void {
            $cache->forever($key, (string) Str::uuid());
        });
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function withLock(Closure $callback): mixed
    {
        $store = Cache::store($this->store)->getStore();
        if (! $store instanceof LockProvider) {
            throw new \RuntimeException('Fulcrum cache invalidation requires a cache store supporting locks.');
        }

        $lock = $store->lock($this->prefix.':generation:lock', 10);
        $lock->block(5);

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    public function invalidateAfterCommit(Connection $connection): void
    {
        if ($connection->transactionLevel() > 0) {
            $connection->afterCommit(fn () => $this->invalidate());

            return;
        }

        $this->invalidate();
    }
}
