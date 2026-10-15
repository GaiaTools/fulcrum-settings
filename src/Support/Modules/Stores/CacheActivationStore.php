<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\Modules\Stores;

use GaiaTools\FulcrumSettings\Contracts\Modules\ActivationStore;
use Illuminate\Cache\CacheManager;
use Illuminate\Config\Repository;
use RuntimeException;

class CacheActivationStore implements ActivationStore
{
    public function __construct(private readonly Repository $config, private readonly CacheManager $cache) {}

    public function read(): ?array
    {
        $state = $this->cache->store($this->cacheStore())
            ->get($this->config->string('fulcrum.modules.storage.cache_key'));
        if ($state !== null && ! is_array($state)) {
            throw new RuntimeException('Invalid cached module activation document.');
        }

        return $state;
    }

    public function write(array $state): void
    {
        if (! $this->cache->store($this->cacheStore())
            ->forever($this->config->string('fulcrum.modules.storage.cache_key'), $state)) {
            throw new RuntimeException('Cannot publish module activation to cache.');
        }
    }

    private function cacheStore(): ?string
    {
        $name = $this->config->get('fulcrum.modules.storage.cache_store');
        if ($name !== null && ! is_string($name)) {
            throw new RuntimeException('Invalid module activation storage configuration.');
        }

        return $name;
    }
}
