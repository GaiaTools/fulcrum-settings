<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Events;

use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Models\SettingRule;

class SettingResolved
{
    /**
     * @param  array<string, mixed>|null  $scope
     */
    public function __construct(
        public readonly string $key,
        public readonly mixed $value,
        public readonly ?Setting $setting = null,
        public readonly ?SettingRule $matchedRule = null,
        public readonly int $rulesEvaluated = 0,
        public readonly string $source = 'default',
        public readonly ?string $tenantId = null,
        public readonly mixed $userId = null,
        public readonly ?array $scope = null,
        public readonly float $durationMs = 0.0,
    ) {}

    /**
     * Get a summary suitable for logging.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'value' => $this->value,
            'source' => $this->source,
            'matched_rule' => $this->matchedRule?->name,
            'matched_rule_priority' => $this->matchedRule?->priority,
            'rules_evaluated' => $this->rulesEvaluated,
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId,
            'duration_ms' => round($this->durationMs, 2),
        ];
    }
}
