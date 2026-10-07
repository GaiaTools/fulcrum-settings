<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Services;

use BackedEnum;
use DateTimeInterface;
use GaiaTools\FulcrumSettings\Contracts\CacheContextProvider;
use GaiaTools\FulcrumSettings\Contracts\GroupedSettingResolver;
use GaiaTools\FulcrumSettings\Contracts\SettingResolver;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use GaiaTools\FulcrumSettings\Support\GroupedSettingResolver as GroupedSettingResolverImpl;
use GaiaTools\FulcrumSettings\Support\RequestCacheDependencies;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CachedSettingResolver implements SettingResolver
{
    /**
     * Format 4 adds cached request dependencies and normalized value objects.
     * Formats 1–3 were iterations of the unreleased isolation PR.
     * Bump only for incompatible key-format or cached-value changes.
     */
    private const CACHE_KEY_VERSION = 4;

    protected ?Authenticatable $user = null;

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
        if (! $this->resolver instanceof CacheContextProvider || ! $this->isCacheable($scope)) {
            Log::debug('Fulcrum resolution cache bypassed.', ['key' => $resolvedKey, 'reason' => 'reveal_or_unsupported_context']);

            return $this->resolver->resolve($resolvedKey, $scope);
        }

        $cacheKey = $this->getCacheKey($resolvedKey, $scope, $this->resolver);

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

        // Write invalidation is separate; cached results currently retain their TTL.
    }

    public function isMultiTenancyEnabled(): bool
    {
        return $this->resolver->isMultiTenancyEnabled();
    }

    protected function getCacheKey(string $key, mixed $scope, CacheContextProvider $contextProvider): string
    {
        $tenantId = $contextProvider->currentTenantId();
        $multiTenancy = $this->resolver->isMultiTenancyEnabled();
        $metadataKey = $this->prefix.':v'.self::CACHE_KEY_VERSION.':dependencies:'.hash('sha256', serialize([$key, $tenantId, $multiTenancy]));
        $metadata = Cache::store($this->store)->remember($metadataKey, $this->ttl, function () use ($contextProvider, $key): RequestCacheDependencies {
            $inputs = $contextProvider->requestDependencies($key);
            sort($inputs);

            return new RequestCacheDependencies($inputs, hash('sha256', serialize($inputs)));
        });
        $context = FulcrumContext::all();
        ksort($context);

        // Hash a structured payload so types and delimiters cannot collide, and
        // targeting attributes never appear in plaintext in cache keys.
        $fingerprint = hash('sha256', serialize([
            $key,
            $tenantId,
            $multiTenancy,
            $this->normalizeForKey($scope),
            $this->userCacheIdentity($scope),
            $this->normalizeForKey($context),
            $metadata->revision,
            in_array('ip', $metadata->inputs, true) ? request()->ip() : null,
            in_array('user_agent', $metadata->inputs, true) ? request()->userAgent() : null,
        ]));

        return $this->prefix.':v'.self::CACHE_KEY_VERSION.':'.$fingerprint;
    }

    protected function isCacheable(mixed $scope): bool
    {
        // Revealed values must always go through the current Gate. Ordinary
        // user-targeted results remain cached for the configured TTL.
        $user = $this->effectiveUser($scope);

        return ! FulcrumContext::shouldReveal()
            && ($user === null || is_scalar($user->getAuthIdentifier()))
            && $this->containsOnlyCacheableValues($scope)
            && $this->containsOnlyCacheableValues(FulcrumContext::all());
    }

    /** @return array{class: string, id: mixed}|null */
    protected function userCacheIdentity(mixed $scope): ?array
    {
        $user = $this->effectiveUser($scope);

        if ($user !== null) {
            return ['class' => $user::class, 'id' => $user->getAuthIdentifier()];
        }

        return $this->userIdentifier !== null
            ? ['class' => 'identifier', 'id' => $this->userIdentifier]
            : null;
    }

    protected function effectiveUser(mixed $scope): ?Authenticatable
    {
        return $this->user ?? ($scope instanceof Authenticatable ? $scope : auth()->user());
    }

    protected function normalizeForKey(mixed $value): mixed
    {
        if ($value instanceof Authenticatable) {
            return ['authenticatable', $value::class, $value->getAuthIdentifier()];
        }

        return match (true) {
            $value instanceof BackedEnum => ['enum', $value::class, $value->value],
            $value instanceof DateTimeInterface => ['date', $value::class, $value->format('Y-m-d\\TH:i:s.uP'), $value->getTimezone()->getName()],
            is_array($value) => ['array', array_map($this->normalizeForKey(...), $value)],
            default => ['scalar', $value],
        };
    }

    protected function containsOnlyCacheableValues(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (! $this->containsOnlyCacheableValues($item)) {
                    return false;
                }
            }

            return true;
        }

        return $value instanceof Authenticatable
            ? is_scalar($value->getAuthIdentifier())
            : $value === null || is_scalar($value) || $value instanceof BackedEnum || $value instanceof DateTimeInterface;
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
