<?php

namespace GaiaTools\FulcrumSettings\Support;

use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Models\SettingRule;
use GaiaTools\FulcrumSettings\Models\SettingRuleRolloutVariant;

final readonly class ResolutionContext
{
    public function __construct(
        public string $key,
        public mixed $value,
        public ?Setting $setting,
        public ?SettingRule $matchedRule,
        public int $rulesEvaluated,
        public string $source,
        public ?string $tenantId,
        public mixed $scope,
        public float $durationMs,
        public ?SettingRuleRolloutVariant $variant = null,
    ) {}

    public static function notFound(
        string $key,
        ?string $tenantId,
        mixed $scope,
        float $startTime
    ): self {
        return new self(
            key: $key,
            value: null,
            setting: null,
            matchedRule: null,
            rulesEvaluated: 0,
            source: 'not_found',
            tenantId: $tenantId,
            scope: $scope,
            durationMs: (microtime(true) - $startTime) * 1000,
        );
    }

    public static function fromResolution(
        string $key,
        mixed $value,
        Setting $setting,
        ?SettingRule $rule,
        int $rulesEvaluated,
        string $source,
        ?string $tenantId,
        mixed $scope,
        float $startTime,
        ?SettingRuleRolloutVariant $variant = null,
    ): self {
        return new self(
            key: $key,
            value: $value,
            setting: $setting,
            matchedRule: $rule,
            rulesEvaluated: $rulesEvaluated,
            source: $source,
            tenantId: $tenantId,
            scope: $scope,
            durationMs: (microtime(true) - $startTime) * 1000,
            variant: $variant,
        );
    }
}
