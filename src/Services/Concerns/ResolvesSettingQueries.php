<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Services\Concerns;

use GaiaTools\FulcrumSettings\Contracts\TenantResolver;
use GaiaTools\FulcrumSettings\Models\Scopes\TenantScope;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use Illuminate\Database\Eloquent\Builder;

trait ResolvesSettingQueries
{
    /**
     * @return array<int, string>
     */
    public function getGroupKeys(string $group): array
    {
        $normalized = $this->normalizeGroup($group);
        $tenantId = $this->resolveTenantId();

        $query = Setting::withoutGlobalScope(TenantScope::class)
            ->where('group', $normalized);

        if ($this->isMultiTenancyEnabled()) {
            if ($tenantId !== null) {
                $query->where(function (Builder $builder) use ($tenantId) {
                    $builder->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
                })->orderByRaw('tenant_id IS NOT NULL DESC');
            } else {
                $query->whereNull('tenant_id');
            }
        }

        $settings = $query->orderBy('id')->get(['key', 'tenant_id']);
        $keys = [];

        foreach ($settings as $setting) {
            if (! array_key_exists($setting->key, $keys)) {
                $keys[$setting->key] = true;
            }
        }

        return array_keys($keys);
    }

    /**
     * Build a base query for finding settings, respecting tenant scope.
     */
    /**
     * @return Builder<Setting>
     */
    protected function buildSettingQuery(string $key, ?string $tenantId): Builder
    {
        return Setting::withoutGlobalScope(TenantScope::class)
            ->where('key', $key)
            ->when(
                $this->isMultiTenancyEnabled() && $tenantId !== null,
                fn (Builder $query) => $query->where(fn ($q) => $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id')
                )->orderByRaw('tenant_id IS NOT NULL DESC') // Tenant-specific first
            )
            ->when(
                $this->isMultiTenancyEnabled() && $tenantId === null,
                fn (Builder $query) => $query->whereNull('tenant_id')
            );
    }

    /**
     * Find a setting by key, respecting tenant scope.
     */
    /**
     * @param  array<int, string>  $with
     */
    protected function findSettingByKey(string $key, ?string $tenantId, array $with = []): ?Setting
    {
        $query = $this->buildSettingQuery($key, $tenantId);

        if (! empty($with)) {
            // The parent query establishes the tenant boundary. Related rows
            // belong to that setting and must not inherit a different ambient tenant.
            $relations = [];
            foreach ($with as $relation) {
                $unscoped = fn ($related) => $related->getQuery()->withoutGlobalScope(TenantScope::class);
                $relations[$relation] = $unscoped;
                $relations[explode('.', $relation)[0]] = $unscoped;
            }
            $query->with($relations);
        }

        return $query->first();
    }

    /**
     * Resolve the current tenant ID.
     */
    protected function resolveTenantId(): ?string
    {
        return $this->currentTenantId();
    }

    public function currentTenantId(): ?string
    {
        if ($this->tenantId !== null || ! $this->isMultiTenancyEnabled()) {
            return $this->tenantId;
        }

        $resolver = config('fulcrum.multi_tenancy.tenant_resolver');
        if (is_string($resolver) && class_exists($resolver) && ($instance = app($resolver)) instanceof TenantResolver) {
            return $instance->resolve();
        }

        return is_callable($resolver)
            ? $resolver()
            : FulcrumContext::getTenantId();
    }

    /**
     * Check if multi-tenancy is enabled.
     */
    public function isMultiTenancyEnabled(): bool
    {
        return config()->boolean('fulcrum.multi_tenancy.enabled', false);
    }

    /** @return list<string> */
    public function requestDependencies(string $key): array
    {
        $setting = $this->findSettingByKey($this->resolveKey($key), $this->currentTenantId(), ['rules.conditions']);
        $dependencies = [];
        foreach ($setting->rules ?? [] as $rule) {
            foreach ($rule->conditions as $condition) {
                $type = $condition->type ?? config()->string('fulcrum.condition_types_default', 'user');
                $dependencies = array_merge($dependencies, $this->conditionRequestInputs($type));
            }
        }

        return array_values(array_unique($dependencies));
    }

    /** @return list<string> */
    protected function conditionRequestInputs(string $type): array
    {
        $defaults = ['user' => [], 'date_time' => [], 'geocoding' => ['ip'], 'user_agent' => ['user_agent']];
        $overrides = config('fulcrum.cache.request_dependencies', []);
        $overrides = is_array($overrides) ? $overrides : [];
        $inputs = $overrides[$type] ?? $defaults[$type] ?? ['ip', 'user_agent'];
        $inputs = is_array($inputs) ? $inputs : ['ip', 'user_agent'];

        return array_values(array_filter($inputs, fn ($input) => is_string($input) && in_array($input, ['ip', 'user_agent'], true)));
    }

    protected function resolveGroup(): ?string
    {
        if ($this->group !== null) {
            return $this->group;
        }

        return FulcrumContext::getGroup();
    }

    protected function resolveKey(string $key): string
    {
        $group = $this->resolveGroup();

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
