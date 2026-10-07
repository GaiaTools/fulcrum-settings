<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Services;

use GaiaTools\FulcrumSettings\Contracts\GroupedSettingResolver;
use GaiaTools\FulcrumSettings\Contracts\SettingResolver;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use GaiaTools\FulcrumSettings\Support\GroupedSettingResolver as GroupedSettingResolverImpl;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;

class CachedSettingResolver implements SettingResolver
{
    protected ?Authenticatable $user = null;

    protected ?string $tenantId = null;

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

        if (! $this->enabled || ! $this->isCacheable($scope)) {
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
        $clone->user = $user;
        $identifier = $user?->getAuthIdentifier();
        $clone->userIdentifier = is_scalar($identifier) ? (string) $identifier : null;

        return $clone;
    }

    public function forTenant(?string $tenantId): static
    {
        $clone = clone $this;
        $clone->resolver = $this->resolver->forTenant($tenantId);
        $clone->tenantId = $tenantId;

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
        $tenantResolver = config('fulcrum.multi_tenancy.tenant_resolver');
        $tenantId = $this->tenantId ?: (
            config()->boolean('fulcrum.multi_tenancy.enabled', false)
                ? (is_callable($tenantResolver) ? $tenantResolver() : FulcrumContext::getTenantId())
                : null
        );

        // Hash a structured payload so types and delimiters cannot collide, and
        // targeting attributes never appear in plaintext in cache keys.
        $fingerprint = hash('sha256', serialize([
            $key,
            $tenantId,
            config()->boolean('fulcrum.multi_tenancy.enabled', false),
            $this->cacheScope($scope),
            $this->userCacheIdentity($scope),
            $this->cacheScope(FulcrumContext::all()),
            request()->ip(),
            request()->userAgent(),
        ]));

        return "{$this->prefix}:v3:{$fingerprint}";
    }

    protected function isCacheable(mixed $scope): bool
    {
        // Revealed values must always go through the current Gate. Ordinary
        // user-targeted results remain cached for the configured TTL.
        $user = $this->user ?? ($scope instanceof Authenticatable ? $scope : auth()->user());

        return ! FulcrumContext::shouldReveal()
            && ($user === null || is_scalar($user->getAuthIdentifier()))
            && $this->containsOnlyCacheableValues($scope)
            && $this->containsOnlyCacheableValues(FulcrumContext::all());
    }

    /** @return array{class: string, id: mixed}|null */
    protected function userCacheIdentity(mixed $scope): ?array
    {
        $user = $this->user ?? ($scope instanceof Authenticatable ? $scope : auth()->user());

        if ($user !== null) {
            return ['class' => $user::class, 'id' => $user->getAuthIdentifier()];
        }

        return $this->userIdentifier !== null
            ? ['class' => 'identifier', 'id' => $this->userIdentifier]
            : null;
    }

    protected function cacheScope(mixed $value): mixed
    {
        if ($value instanceof Authenticatable) {
            return ['authenticatable', $value::class, $value->getAuthIdentifier()];
        }

        return is_array($value)
            ? ['array', array_map($this->cacheScope(...), $value)]
            : ['scalar', $value];
    }

    protected function containsOnlyCacheableValues(mixed $value): bool
    {
        if ($value instanceof Authenticatable) {
            return is_scalar($value->getAuthIdentifier());
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (! $this->containsOnlyCacheableValues($item)) {
                    return false;
                }
            }

            return true;
        }

        return $value === null || is_scalar($value);
    }

    protected function resolveKey(string $key): string
    {
        $group = $this->group ?? FulcrumContext::getGroup();

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
