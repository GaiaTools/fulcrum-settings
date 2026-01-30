<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Services;

use GaiaTools\FulcrumSettings\Contracts\SettingResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;

class CachedSettingResolver implements SettingResolver
{
    public function __construct(
        protected SettingResolver $resolver,
        protected bool $enabled = true,
        protected string $prefix = 'fulcrum',
        protected int $ttl = 3600,
        protected ?string $store = null,
        protected ?string $userIdentifier = null
    ) {}

    public function resolve(string $key, mixed $scope = null): mixed
    {
        if (! $this->enabled) {
            return $this->resolver->resolve($key, $scope);
        }

        $cacheKey = $this->getCacheKey($key, $scope);

        return Cache::store($this->store)->remember($cacheKey, $this->ttl, function () use ($key, $scope) {
            return $this->resolver->resolve($key, $scope);
        });
    }

    public function isActive(string $key, mixed $scope = null): bool
    {
        return (bool) $this->resolve($key, $scope);
    }

    public function forUser(?Authenticatable $user): static
    {
        $clone = clone $this;
        $clone->resolver = $this->resolver->forUser($user);
        $identifier = $user?->getAuthIdentifier();
        $clone->userIdentifier = is_scalar($identifier) ? (string) $identifier : null;

        return $clone;
    }

    public function forTenant(?string $tenantId): static
    {
        $clone = clone $this;
        $clone->resolver = $this->resolver->forTenant($tenantId);

        return $clone;
    }

    public function get(string $key, mixed $default = null, mixed $scope = null): mixed
    {
        return $this->resolve($key, $scope) ?? $default;
    }

    public function reveal(bool $reveal = true): static
    {
        $this->resolver->reveal($reveal);

        return $this;
    }

    public function set(string $key, mixed $value): void
    {
        $this->resolver->set($key, $value);

        // We might want to clear cache here, but it's hard to clear specific scopes.
        // For simplicity, we can't easily clear scoped cache.
        // In a real app, you'd probably use cache tags if supported.
    }

    public function isMultiTenancyEnabled(): bool
    {
        return $this->resolver->isMultiTenancyEnabled();
    }

    protected function getCacheKey(string $key, mixed $scope): string
    {
        if ($scope === null) {
            $scopeKey = $this->resolveDefaultScopeKey();
        } else {
            $scopeKey = is_scalar($scope) ? (string) $scope : md5(serialize($scope));
        }

        return "{$this->prefix}:{$key}:{$scopeKey}";
    }

    protected function resolveDefaultScopeKey(): string
    {
        if ($this->userIdentifier !== null) {
            return 'user:'.$this->userIdentifier;
        }

        $user = auth()->user();
        if ($user) {
            $identifier = $user->getAuthIdentifier();
            if (is_scalar($identifier)) {
                return 'user:'.(string) $identifier;
            }
        }

        return 'global';
    }
}
