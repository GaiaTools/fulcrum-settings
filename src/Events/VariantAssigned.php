<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Events;

use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Models\SettingRule;
use GaiaTools\FulcrumSettings\Models\SettingRuleRolloutVariant;

/**
 * Fired when a user is assigned to a rollout variant.
 *
 * This event enables analytics integrations for A/B testing platforms
 * like Segment, Mixpanel, Amplitude, etc.
 */
class VariantAssigned
{
    public function __construct(
        public readonly string $settingKey,
        public readonly string $ruleName,
        public readonly string $variantName,
        public readonly mixed $value,
        public readonly string $identifier,
        public readonly int $bucket,
        public readonly ?Setting $setting = null,
        public readonly ?SettingRule $rule = null,
        public readonly ?SettingRuleRolloutVariant $variant = null,
        public readonly ?string $tenantId = null,
        public readonly array $context = [],
    ) {}

    /**
     * Get a summary suitable for logging or analytics.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'setting_key' => $this->settingKey,
            'rule_name' => $this->ruleName,
            'variant_name' => $this->variantName,
            'value' => $this->value,
            'identifier' => $this->identifier,
            'bucket' => $this->bucket,
            'tenant_id' => $this->tenantId,
            'context' => $this->context,
        ];
    }

    /**
     * Get data formatted for common analytics platforms.
     *
     * @return array<string, mixed>
     */
    public function toAnalytics(): array
    {
        return [
            'experiment_key' => $this->settingKey,
            'experiment_name' => $this->ruleName,
            'variant_key' => $this->variantName,
            'variant_value' => $this->value,
            'assignment_id' => $this->identifier,
            'bucket_value' => $this->bucket,
        ];
    }
}
