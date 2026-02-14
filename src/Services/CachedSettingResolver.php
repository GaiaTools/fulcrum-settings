<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Services;

use GaiaTools\FulcrumSettings\Contracts\GroupedSettingResolver;
use GaiaTools\FulcrumSettings\Contracts\SettingResolver;
use GaiaTools\FulcrumSettings\Support\GroupedSettingResolver as GroupedSettingResolverImpl;
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
        protected ?string $userIdentifier = null,
        protected ?string $group = null
    ) {}

    public function resolve(string $key, mixed $scope = null): mixed
    {
        $resolvedKey = $this->resolveKey($key);

        if (! $this->enabled) {
            return $this->resolver->resolve($resolvedKey, $scope);
        }

        $cacheKey = $this->getCacheKey($resolvedKey, $scope);

        return Cache::store($this->store)->remember($cacheKey, $this->ttl, function () use ($resolvedKey, $scope) {
            return $this->resolver->resolve($resolvedKey, $scope);
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

    public function forGroup(?string $group): static
    {
        $clone = clone $this;
        $clone->group = $group;
        $clone->resolver = $this->resolver->forGroup($group);

        return $clone;
    }

    public function group(string $group): GroupedSettingResolver
    {
        $normalized = $this->normalizeGroup($group);

        return new GroupedSettingResolverImpl($this->forGroup($normalized), $normalized);
    }

    /**
     * @return array<int, string>
     */
    public function getGroupKeys(string $group): array
    {
        return $this->resolver->getGroupKeys($group);
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
        $this->resolver->set($this->resolveKey($key), $value);

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
            $scopeKey = is_scalar($scope) ? (string) $scope : hash('sha256', serialize($scope));
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

    protected function resolveKey(string $key): string
    {
        $group = $this->group ?? \GaiaTools\FulcrumSettings\Support\FulcrumContext::getGroup();

        if ($group && ! str_contains($key, '.')) {
            return $group.'.'.$key;
        }

        return $key;
    }

    protected function normalizeGroup(string $group): string
    {
        $normalized = trim($group, " .\t\n\r\0\x0B");

        if ($normalized === '') {
            throw new \InvalidArgumentException('Group name cannot be empty.');
        }

        return $normalized;
    }
}
