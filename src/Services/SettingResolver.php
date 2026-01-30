<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Services;

use GaiaTools\FulcrumSettings\Contracts\BucketCalculator;
use GaiaTools\FulcrumSettings\Contracts\DistributionStrategy;
use GaiaTools\FulcrumSettings\Contracts\RuleEvaluator;
use GaiaTools\FulcrumSettings\Contracts\SettingResolver as SettingResolverContract;
use GaiaTools\FulcrumSettings\Events\SettingResolved;
use GaiaTools\FulcrumSettings\Events\VariantAssigned;
use GaiaTools\FulcrumSettings\Exceptions\InvalidSettingValueException;
use GaiaTools\FulcrumSettings\Exceptions\SettingNotFoundException;
use GaiaTools\FulcrumSettings\Models\Scopes\TenantScope;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Models\SettingRule;
use GaiaTools\FulcrumSettings\Models\SettingRuleRolloutVariant;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use GaiaTools\FulcrumSettings\Support\ResolutionContext;
use GaiaTools\FulcrumSettings\Support\TypeRegistry;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;

class SettingResolver implements SettingResolverContract
{
    protected ?Authenticatable $user = null;

    protected ?string $tenantId = null;

    protected ?int $lastCalculatedBucket = null;

    public function __construct(
        protected RuleEvaluator $ruleEvaluator,
        protected BucketCalculator $bucketCalculator,
        protected DistributionStrategy $distributionStrategy,
    ) {}

    // ========================================
    // Public API
    // ========================================

    public function resolve(string $key, mixed $scope = null): mixed
    {
        $startTime = microtime(true);
        $tenantId = $this->resolveTenantId();
        $effectiveUser = $this->resolveEffectiveUser($scope);

        $setting = $this->findSettingByKey($key, $tenantId, [
            'rules.conditions',
            'rules.value',
            'rules.rolloutVariants.value',
            'defaultValue',
        ]);

        if (! $setting) {
            $context = ResolutionContext::notFound($key, $tenantId, $scope, $startTime);
            $this->recordResolution($context);

            return null;
        }

        [$rule, $variant, $count] = $this->evaluateRules($setting, $scope, $effectiveUser);
        [$value, $source] = $this->resolveValueAndSource($setting, $rule, $variant, $scope, $tenantId);

        $context = ResolutionContext::fromResolution(
            $key, $value, $setting, $rule, $count, $source, $tenantId, $scope, $startTime, $variant
        );
        $this->recordResolution($context, $effectiveUser);

        return $value;
    }

    public function get(string $key, mixed $default = null, mixed $scope = null): mixed
    {
        return $this->resolve($key, $scope) ?? $default;
    }

    public function reveal(bool $reveal = true): static
    {
        FulcrumContext::reveal($reveal);

        return $this;
    }

    public function set(string $key, mixed $value): void
    {
        $tenantId = $this->resolveTenantId();
        $setting = $this->findSettingByKey($key, $tenantId);

        if (! $setting) {
            throw new SettingNotFoundException($key, $tenantId);
        }

        $this->validateAndStoreSetting($setting, $value);
    }

    public function isActive(string $key, mixed $scope = null): bool
    {
        return (bool) $this->resolve($key, $scope);
    }

    public function forUser(?Authenticatable $user): static
    {
        $clone = clone $this;
        $clone->user = $user;

        return $clone;
    }

    public function forTenant(?string $tenantId): static
    {
        $clone = clone $this;
        $clone->tenantId = $tenantId;

        return $clone;
    }

    public function getLastCalculatedBucket(): ?int
    {
        return $this->lastCalculatedBucket;
    }

    // ========================================
    // Query Building
    // ========================================

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
                $this->isMultiTenancyEnabled() && $tenantId,
                fn (Builder $query) => $query->where(fn ($q) => $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id')
                )->orderByRaw('tenant_id IS NOT NULL DESC') // Tenant-specific first
            )
            ->when(
                $this->isMultiTenancyEnabled() && ! $tenantId,
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
            $query->with($with);
        }

        return $query->first();
    }

    // ========================================
    // Rule Evaluation
    // ========================================

    /**
     * Evaluate all rules for a setting and return the first matching rule/variant.
     *
     * @return array{0: SettingRule|null, 1: SettingRuleRolloutVariant|null, 2: int}
     */
    protected function evaluateRules(Setting $setting, mixed $scope, ?Authenticatable $user): array
    {
        $rulesEvaluated = 0;

        foreach ($setting->rules->sortBy('priority') as $rule) {
            $rulesEvaluated++;

            if (! $this->shouldEvaluateRule($rule, $scope, $user)) {
                continue;
            }

            if ($rule->hasRolloutVariants()) {
                if ($variant = $this->selectRolloutVariant($rule, $scope)) {
                    return [$rule, $variant, $rulesEvaluated];
                }

                continue;
            }

            return [$rule, null, $rulesEvaluated];
        }

        return [null, null, $rulesEvaluated];
    }

    /**
     * Determine if a rule should be evaluated.
     */
    protected function shouldEvaluateRule(SettingRule $rule, mixed $scope, ?Authenticatable $user): bool
    {
        return $rule->isActive()
            && $this->ruleEvaluator->setUser($user)->evaluateRule($rule, $scope);
    }

    /**
     * Resolve the final value and source from the evaluation results.
     *
     * @return array{0: mixed, 1: string}
     */
    protected function resolveValueAndSource(
        Setting $setting,
        ?SettingRule $rule,
        ?SettingRuleRolloutVariant $variant,
        mixed $scope,
        ?string $tenantId
    ): array {
        if ($variant !== null) {
            $this->fireVariantAssignedEvent($setting, $rule, $variant, $scope, $tenantId);

            return [$variant->getValue(), 'rollout'];
        }

        if ($rule !== null) {
            return [$rule->getValue(), 'rule'];
        }

        return [$setting->getDefaultValue(), 'default'];
    }

    // ========================================
    // Rollout Logic
    // ========================================

    /**
     * Select a rollout variant based on consistent bucketing.
     */
    protected function selectRolloutVariant(SettingRule $rule, mixed $scope): ?SettingRuleRolloutVariant
    {
        $identifier = $this->resolveRolloutIdentifier($scope);

        if ($identifier === null) {
            return null;
        }

        $this->lastCalculatedBucket = $this->calculateBucket($rule, $identifier);

        return $this->findVariantForBucket($rule, $this->lastCalculatedBucket);
    }

    /**
     * Calculate the bucket value for an identifier.
     */
    protected function calculateBucket(SettingRule $rule, string $identifier): int
    {
        $salt = $rule->getEffectiveSalt();
        $precisionConfig = config('fulcrum.rollout.bucket_precision', 100_000);
        $precision = is_numeric($precisionConfig) ? (int) $precisionConfig : 100_000;

        return $this->bucketCalculator->calculate($identifier, $salt, $precision);
    }

    /**
     * Find the variant that corresponds to a given bucket value.
     */
    protected function findVariantForBucket(SettingRule $rule, int $bucket): ?SettingRuleRolloutVariant
    {
        return $this->distributionStrategy->findVariantForBucket($rule, $bucket);
    }

    /**
     * Resolve the identifier used for bucket calculation.
     */
    protected function resolveRolloutIdentifier(mixed $scope): ?string
    {
        // Custom resolver takes precedence
        if ($customIdentifier = $this->callCustomIdentifierResolver($scope)) {
            return $customIdentifier;
        }

        // Try standard identifier sources
        return $this->extractIdentifierFromUser()
            ?? $this->extractIdentifierFromScope($scope);
    }

    /**
     * Call the custom identifier resolver if configured.
     */
    protected function callCustomIdentifierResolver(mixed $scope): ?string
    {
        $resolver = config('fulcrum.rollout.identifier_resolver');

        if (! is_callable($resolver)) {
            return null;
        }

        $result = $resolver($scope, $this->user);
        if ($result === null) {
            return null;
        }

        if (is_scalar($result)) {
            return (string) $result;
        }

        if (is_object($result) && method_exists($result, '__toString')) {
            return (string) $result;
        }

        return null;
    }

    /**
     * Extract identifier from the current user.
     */
    protected function extractIdentifierFromUser(): ?string
    {
        $identifier = $this->user?->getAuthIdentifier();

        return is_scalar($identifier) ? (string) $identifier : null;
    }

    /**
     * Extract identifier from scope (scalar, array, or object).
     */
    protected function extractIdentifierFromScope(mixed $scope): ?string
    {
        return match (true) {
            is_scalar($scope) => (string) $scope,
            is_array($scope) && isset($scope['id']) && is_scalar($scope['id']) => (string) $scope['id'],
            is_object($scope) && property_exists($scope, 'id') && is_scalar($scope->id) => (string) $scope->id,
            default => null,
        };
    }

    // ========================================
    // Event Handling
    // ========================================

    /**
     * Fire the variant assigned event for analytics integration.
     */
    protected function fireVariantAssignedEvent(
        Setting $setting,
        ?SettingRule $rule,
        SettingRuleRolloutVariant $variant,
        mixed $scope,
        ?string $tenantId,
    ): void {
        if (! config('fulcrum.rollout.fire_assignment_events', true)) {
            return;
        }

        event(new VariantAssigned(
            settingKey: $setting->key,
            ruleName: $rule && is_string($rule->name) ? $rule->name : 'unnamed',
            variantName: $variant->name,
            value: $variant->getValue(),
            identifier: $this->resolveRolloutIdentifier($scope) ?? 'unknown',
            bucket: $this->lastCalculatedBucket ?? 0,
            setting: $setting,
            rule: $rule,
            variant: $variant,
            tenantId: $tenantId,
            context: is_array($scope) ? $scope : [],
        ));
    }

    /**
     * Record the setting resolution event if enabled.
     */
    protected function recordResolution(ResolutionContext $context, ?Authenticatable $user = null): void
    {
        if (! $this->shouldRecordResolution()) {
            return;
        }

        $scopeData = null;
        if (is_array($context->scope)) {
            $scopeData = [];
            foreach ($context->scope as $key => $value) {
                if (is_string($key)) {
                    $scopeData[$key] = $value;
                }
            }
        }

        event(new SettingResolved(
            key: $context->key,
            value: $context->value,
            setting: $context->setting,
            matchedRule: $context->matchedRule,
            rulesEvaluated: $context->rulesEvaluated,
            source: $context->source,
            tenantId: $context->tenantId,
            userId: $user?->getAuthIdentifier(),
            scope: $scopeData,
            durationMs: $context->durationMs,
        ));
    }

    protected function resolveEffectiveUser(mixed $scope): ?Authenticatable
    {
        if ($this->user) {
            return $this->user;
        }

        if ($scope instanceof Authenticatable) {
            return $scope;
        }

        return auth()->user();
    }

    /**
     * Determine if resolution events should be recorded.
     */
    protected function shouldRecordResolution(): bool
    {
        return config('fulcrum.telescope.enabled', true)
            && class_exists(\Laravel\Telescope\Telescope::class);
    }

    // ========================================
    // Setting Mutation
    // ========================================

    /**
     * Validate and store a setting value.
     */
    protected function validateAndStoreSetting(Setting $setting, mixed $value): void
    {
        $handler = app(TypeRegistry::class)->getHandler($setting->type);

        if (! $handler->validate($value)) {
            throw InvalidSettingValueException::forSetting($setting->key, $setting->type, $value);
        }

        $storageValue = $handler->set($value);

        $setting->defaultValue()->updateOrCreate([
            'valuable_type' => $setting->getMorphClass(),
            'valuable_id' => $setting->getKey(),
        ], ['value' => $storageValue]);
    }

    // ========================================
    // Tenant Resolution
    // ========================================

    /**
     * Resolve the current tenant ID.
     */
    protected function resolveTenantId(): ?string
    {
        if ($this->tenantId) {
            return $this->tenantId;
        }

        if (! $this->isMultiTenancyEnabled()) {
            return null;
        }

        $resolver = config('fulcrum.multi_tenancy.tenant_resolver');

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
}
