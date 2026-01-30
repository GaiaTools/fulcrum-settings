<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Database\Migrations;

use GaiaTools\FulcrumSettings\Models\SettingRuleRolloutVariant;
use InvalidArgumentException;

/**
 * Fluent builder for modifying an existing rollout variant in a migration.
 *
 * @example
 * ```php
 * $modifier->updateWeight(30)
 *     ->updateValue('updated_feature');
 * ```
 */
class VariantModifier
{
    /** @var array<string, mixed> */
    protected array $updates = [];

    protected mixed $newValue = null;

    protected bool $shouldUpdateValue = false;

    public function __construct(
        protected readonly SettingRuleRolloutVariant $variant
    ) {}

    /**
     * Update the variant name.
     */
    public function updateName(string $name): self
    {
        $this->updates['name'] = $name;

        return $this;
    }

    /**
     * Update the weight (as percentage 0.000 - 100.000).
     */
    public function updateWeight(float $weight): self
    {
        if ($weight < 0 || $weight > 100) {
            throw new InvalidArgumentException(
                "Variant weight must be between 0 and 100, got {$weight}"
            );
        }

        $this->updates['weight'] = (int) round($weight * 1000);

        return $this;
    }

    /**
     * Update the value returned for this variant.
     */
    public function updateValue(mixed $value): self
    {
        $this->newValue = $value;
        $this->shouldUpdateValue = true;

        return $this;
    }

    /**
     * Remove the value from this variant (will return null when selected).
     */
    public function removeValue(): self
    {
        $this->newValue = null;
        $this->shouldUpdateValue = true;

        return $this;
    }

    /**
     * Apply all modifications to the variant.
     */
    public function apply(): SettingRuleRolloutVariant
    {
        // Apply direct updates
        if (! empty($this->updates)) {
            $this->variant->update($this->updates);
        }

        // Update value
        if ($this->shouldUpdateValue) {
            $valueModel = $this->variant->value;

            if ($this->newValue === null) {
                // Remove value
                $valueModel?->delete();
            } elseif ($valueModel) {
                // Update existing value
                $valueModel->update(['value' => $this->newValue]);
            } else {
                // Create new value
                $this->variant->value()->create([
                    'valuable_type' => $this->variant->getMorphClass(),
                    'valuable_id' => $this->variant->id,
                    'value' => $this->newValue,
                ]);
            }
        }

        return $this->variant->fresh() ?? $this->variant;
    }

    /**
     * Get the underlying variant model.
     */
    public function getVariant(): SettingRuleRolloutVariant
    {
        return $this->variant;
    }
}
