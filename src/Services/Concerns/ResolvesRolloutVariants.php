<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Services\Concerns;

use GaiaTools\FulcrumSettings\Events\VariantAssigned;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Models\SettingRule;
use GaiaTools\FulcrumSettings\Models\SettingRuleRolloutVariant;

trait ResolvesRolloutVariants
{
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

        return match (true) {
            $result === null => null,
            is_scalar($result) => (string) $result,
            is_object($result) && method_exists($result, '__toString') => (string) $result,
            default => null,
        };
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
}
