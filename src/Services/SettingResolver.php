<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Services;

use GaiaTools\FulcrumSettings\Contracts\BucketCalculator;
use GaiaTools\FulcrumSettings\Contracts\CacheContextProvider;
use GaiaTools\FulcrumSettings\Contracts\DistributionStrategy;
use GaiaTools\FulcrumSettings\Contracts\GroupedSettingResolver;
use GaiaTools\FulcrumSettings\Contracts\RuleEvaluator;
use GaiaTools\FulcrumSettings\Contracts\SettingResolver as SettingResolverContract;
use GaiaTools\FulcrumSettings\Events\SettingResolved;
use GaiaTools\FulcrumSettings\Exceptions\InvalidSettingValueException;
use GaiaTools\FulcrumSettings\Exceptions\SettingNotFoundException;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Models\SettingRule;
use GaiaTools\FulcrumSettings\Models\SettingRuleRolloutVariant;
use GaiaTools\FulcrumSettings\Services\Concerns\ResolvesRolloutVariants;
use GaiaTools\FulcrumSettings\Services\Concerns\ResolvesSettingQueries;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use GaiaTools\FulcrumSettings\Support\GroupedSettingResolver as GroupedSettingResolverImpl;
use GaiaTools\FulcrumSettings\Support\ResolutionContext;
use GaiaTools\FulcrumSettings\Support\TypeRegistry;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Telescope\Telescope;

class SettingResolver implements CacheContextProvider, SettingResolverContract
{
    use ResolvesRolloutVariants;
    use ResolvesSettingQueries;

    protected ?Authenticatable $user = null;

    protected ?string $tenantId = null;

    protected ?string $group = null;

    protected ?int $lastCalculatedBucket = null;

    public function __construct(
        protected RuleEvaluator $ruleEvaluator,
        protected BucketCalculator $bucketCalculator,
        protected DistributionStrategy $distributionStrategy,
    ) {}

    public function resolve(string $key, mixed $scope = null): mixed
    {
        $startTime = microtime(true);
        $tenantId = $this->resolveTenantId();
        $effectiveUser = $this->resolveEffectiveUser($scope);
        $resolvedKey = $this->resolveKey($key);

        $setting = $this->findSettingByKey($resolvedKey, $tenantId, [
            'rules.conditions',
            'rules.value',
            'rules.rolloutVariants.value',
            'defaultValue',
        ]);

        if (! $setting) {
            $context = ResolutionContext::notFound($resolvedKey, $tenantId, $scope, $startTime);
            $this->recordResolution($context);

            return null;
        }

        [$rule, $variant, $count] = $this->evaluateRules($setting, $scope, $effectiveUser);
        [$value, $source] = $this->resolveValueAndSource($setting, $rule, $variant, $scope, $tenantId);

        $context = ResolutionContext::fromResolution(
            $resolvedKey, $value, $setting, $rule, $count, $source, $tenantId, $scope, $startTime, $variant
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
        $resolvedKey = $this->resolveKey($key);
        $setting = $this->findSettingByKey($resolvedKey, $tenantId);

        if (! $setting) {
            throw new SettingNotFoundException($resolvedKey, $tenantId);
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

    public function forGroup(?string $group): static
    {
        $clone = clone $this;
        $clone->group = $group;

        return $clone;
    }

    public function group(string $group): GroupedSettingResolver
    {
        $normalized = $this->normalizeGroup($group);

        return new GroupedSettingResolverImpl($this->forGroup($normalized), $normalized);
    }

    public function getLastCalculatedBucket(): ?int
    {
        return $this->lastCalculatedBucket;
    }

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
            && class_exists(Telescope::class);
    }

    /**
     * Validate and store a setting value.
     */
    protected function validateAndStoreSetting(Setting $setting, mixed $value): void
    {
        $handler = app(TypeRegistry::class)->getHandler($setting->type);

        if (! $handler->validate($value)) {
            throw InvalidSettingValueException::forSetting($setting->key, $setting->type, $value);
        }

        // Pass the raw value through. The SettingValue model's `value` mutator
        // serializes it via the type handler. Pre-encoding here would cause a
        // double-encode (e.g. arrays/json become JSON-stringified twice and
        // read back as empty/broken); scalars happen to be idempotent and hide it.
        $setting->defaultValue()->updateOrCreate([
            'valuable_type' => $setting->getMorphClass(),
            'valuable_id' => $setting->getKey(),
        ], ['value' => $value]);
    }
}
