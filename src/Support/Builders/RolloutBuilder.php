<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\Builders;

use GaiaTools\FulcrumSettings\Models\SettingRule;
use GaiaTools\FulcrumSettings\Models\SettingRuleRolloutVariant;
use InvalidArgumentException;

class RolloutBuilder
{
    /**
     * @var array<int, array{name: string, weight: int, value: mixed}>
     */
    protected array $variants = [];

    /**
     * Add a variant with the given weight percentage.
     *
     * @param  string  $name  Unique name for this variant
     * @param  float  $weight  Weight as percentage (0.000 - 100.000)
     * @param  mixed  $value  The value to return when this variant is selected
     */
    public function variant(string $name, float $weight, mixed $value = null): self
    {
        if ($weight < 0 || $weight > 100) {
            throw new InvalidArgumentException(
                "Variant weight must be between 0 and 100, got {$weight}"
            );
        }

        // Check for duplicate names
        foreach ($this->variants as $variant) {
            if ($variant['name'] === $name) {
                throw new InvalidArgumentException(
                    "Duplicate variant name: {$name}"
                );
            }
        }

        $this->variants[] = [
            'name' => $name,
            'weight' => (int) round($weight * 1000), // Convert to basis points
            'value' => $value,
        ];

        return $this;
    }

    /**
     * Get the total weight of all variants.
     */
    public function getTotalWeight(): int
    {
        return array_sum(array_column($this->variants, 'weight'));
    }

    /**
     * Get the total weight as a percentage.
     */
    public function getTotalPercentage(): float
    {
        return $this->getTotalWeight() / 1000;
    }

    /**
     * Check if the total weight exceeds 100%.
     */
    public function exceedsMaxWeight(): bool
    {
        return $this->getTotalWeight() > SettingRuleRolloutVariant::WEIGHT_PRECISION;
    }

    /**
     * Validate the rollout configuration.
     *
     * @throws InvalidArgumentException
     */
    public function validate(): void
    {
        if (empty($this->variants)) {
            throw new InvalidArgumentException(
                'At least one variant must be defined'
            );
        }

        if ($this->exceedsMaxWeight()) {
            throw new InvalidArgumentException(
                sprintf(
                    'Total variant weight (%.3f%%) exceeds 100%%',
                    $this->getTotalPercentage()
                )
            );
        }
    }

    /**
     * Get the configured variants.
     *
     * @return array<int, array{name: string, weight: int, value: mixed}>
     */
    public function getVariants(): array
    {
        return $this->variants;
    }

    /**
     * Create the rollout variants for the given rule.
     *
     * @return array<int, SettingRuleRolloutVariant>
     */
    public function createFor(SettingRule $rule): array
    {
        $this->validate();

        $created = [];

        foreach ($this->variants as $variantData) {
            $variant = $rule->rolloutVariants()->create([
                'name' => $variantData['name'],
                'weight' => $variantData['weight'],
            ]);

            // Create the value if provided
            if ($variantData['value'] !== null) {
                $variant->value()->create([
                    'valuable_type' => $variant->getMorphClass(),
                    'valuable_id' => $variant->id,
                    'value' => $variantData['value'],
                ]);
            }

            $created[] = $variant;
        }

        return $created;
    }

    /**
     * Reset the builder state.
     */
    public function reset(): self
    {
        $this->variants = [];

        return $this;
    }
}
