<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Database\Migrations;

use Closure;
use GaiaTools\FulcrumSettings\Enums\ComparisonOperator;
use GaiaTools\FulcrumSettings\Enums\ConditionType;
use GaiaTools\FulcrumSettings\Models\SettingRule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Fluent builder for modifying an existing rule in a migration.
 *
 * @example
 * ```php
 * $this->modifyRule('feature.checkout', 'premium_users', function (RuleModifier $rule) {
 *     $rule->updatePriority(5)
 *         ->updateValue(true)
 *         ->addCondition('user.active', 'is_true')
 *         ->removeCondition('user.legacy');
 * });
 * ```
 */
class RuleModifier
{
    /** @var array<string, mixed> */
    protected array $updates = [];

    /** @var array<int, array{type: string|null, attribute: string, operator: ComparisonOperator, value: mixed}> */
    protected array $conditionsToAdd = [];

    /** @var array<int, array{attribute: string, type: string|null}> */
    protected array $conditionsToRemove = [];

    /** @var array<int, array{attribute: string, type: string|null, callback: Closure}> */
    protected array $conditionsToModify = [];

    protected mixed $newValue = null;

    protected bool $shouldUpdateValue = false;

    protected ?RolloutDefinition $newRollout = null;

    protected bool $shouldClearRollout = false;

    /** @var array<string, Closure> */
    protected array $variantsToModify = [];

    /** @var array<int, string> */
    protected array $variantsToRemove = [];

    public function __construct(
        protected readonly SettingRule $rule
    ) {}

    /**
     * Update the rule name.
     */
    public function updateName(string $name): self
    {
        $this->updates['name'] = $name;

        return $this;
    }

    /**
     * Update the rule priority.
     */
    public function updatePriority(int $priority): self
    {
        $this->updates['priority'] = $priority;

        return $this;
    }

    /**
     * Update the direct value for this rule.
     */
    public function updateValue(mixed $value): self
    {
        if ($this->newRollout !== null || $this->rule->hasRolloutVariants()) {
            throw new InvalidArgumentException(
                'Cannot set a direct value on a rule with rollout variants.'
            );
        }

        $this->newValue = $value;
        $this->shouldUpdateValue = true;

        return $this;
    }

    /**
     * Update the rollout salt (re-randomizes all assignments).
     */
    public function regenerateSalt(): self
    {
        $this->updates['rollout_salt'] = Str::random(16);

        return $this;
    }

    /**
     * Set a specific salt value.
     */
    public function updateSalt(string $salt): self
    {
        $this->updates['rollout_salt'] = $salt;

        return $this;
    }

    /**
     * Update the start time.
     */
    public function updateStartsAt(string|Carbon $dateTime): self
    {
        $this->updates['starts_at'] = $dateTime instanceof Carbon
            ? $dateTime
            : ($dateTime ? Carbon::parse($dateTime) : null);

        return $this;
    }

    /**
     * Update the end time.
     */
    public function updateEndsAt(string|Carbon $dateTime): self
    {
        $this->updates['ends_at'] = $dateTime instanceof Carbon
            ? $dateTime
            : ($dateTime ? Carbon::parse($dateTime) : null);

        return $this;
    }

    /**
     * Update both start and end times.
     */
    public function updateTimeBounds(string|Carbon $start, string|Carbon $end): self
    {
        return $this->updateStartsAt($start)->updateEndsAt($end);
    }

    /**
     * Remove time bounds.
     */
    public function removeTimeBounds(): self
    {
        $this->updates['starts_at'] = null;
        $this->updates['ends_at'] = null;

        return $this;
    }

    /**
     * Add a new condition to this rule.
     */
    public function addCondition(
        string $attribute,
        string|ComparisonOperator $operator,
        mixed $value = null,
        ?string $type = null
    ): self {
        $operator = is_string($operator)
            ? ComparisonOperator::from($operator)
            : $operator;

        $this->conditionsToAdd[] = [
            'type' => $type,
            'attribute' => $attribute,
            'operator' => $operator,
            'value' => $value,
        ];

        return $this;
    }

    /**
     * Remove a condition by attribute name.
     */
    public function removeCondition(string $attribute): self
    {
        $this->conditionsToRemove[] = ['attribute' => $attribute, 'type' => null];

        return $this;
    }

    /**
     * Remove a condition by attribute and type.
     */
    public function removeConditionWithType(string $type, string $attribute): self
    {
        $this->conditionsToRemove[] = ['attribute' => $attribute, 'type' => $type];

        return $this;
    }

    /**
     * Modify an existing condition by attribute.
     */
    public function modifyCondition(string $attribute, Closure $callback): self
    {
        $this->conditionsToModify[] = [
            'attribute' => $attribute,
            'type' => null,
            'callback' => $callback,
        ];

        return $this;
    }

    /**
     * Modify an existing condition by attribute and type.
     */
    public function modifyConditionWithType(string $type, string $attribute, Closure $callback): self
    {
        $this->conditionsToModify[] = [
            'attribute' => $attribute,
            'type' => $type,
            'callback' => $callback,
        ];

        return $this;
    }

    /**
     * Clear all conditions from the rule.
     */
    public function clearConditions(): self
    {
        foreach ($this->rule->conditions as $condition) {
            $this->conditionsToRemove[] = ['attribute' => $condition->attribute, 'type' => $condition->type];
        }

        return $this;
    }

    /**
     * Replace rollout configuration with a new one.
     */
    public function replaceRollout(Closure $callback): self
    {
        if ($this->shouldUpdateValue) {
            throw new InvalidArgumentException(
                'Cannot add rollout variants when a direct value is set.'
            );
        }

        $this->shouldClearRollout = true;
        $this->newRollout = new RolloutDefinition;
        $callback($this->newRollout);

        return $this;
    }

    /**
     * Remove rollout variants and convert to direct value.
     */
    public function convertToDirectValue(mixed $value): self
    {
        $this->shouldClearRollout = true;
        $this->newValue = $value;
        $this->shouldUpdateValue = true;

        return $this;
    }

    /**
     * Modify a rollout variant by name.
     */
    public function modifyVariant(string $name, Closure $callback): self
    {
        $this->variantsToModify[$name] = $callback;

        return $this;
    }

    /**
     * Remove a rollout variant by name.
     */
    public function removeVariant(string $name): self
    {
        $this->variantsToRemove[] = $name;

        return $this;
    }

    /**
     * Apply all modifications to the rule.
     */
    public function apply(): SettingRule
    {
        // Apply direct updates
        if (! empty($this->updates)) {
            $this->rule->update($this->updates);
        }

        // Handle conditions
        $this->applyConditionChanges();

        // Handle rollout/value changes
        $this->applyValueChanges();

        return $this->rule->fresh() ?? $this->rule;
    }

    /**
     * Apply condition changes.
     */
    protected function applyConditionChanges(): void
    {
        // Remove conditions
        foreach ($this->conditionsToRemove as $condition) {
            $query = $this->rule->conditions()->where('attribute', $condition['attribute']);

            if ($condition['type']) {
                $query->where('type', $condition['type']);
            }

            $query->delete();
        }

        // Modify existing conditions
        foreach ($this->conditionsToModify as $entry) {
            $query = $this->rule->conditions()->where('attribute', $entry['attribute']);

            if ($entry['type']) {
                $query->where('type', $entry['type']);
            }

            $condition = $query->first();

            if ($condition) {
                $conditionModifier = new ConditionModifier($condition);
                ($entry['callback'])($conditionModifier);
                $conditionModifier->apply();
            }
        }

        // Add new conditions
        foreach ($this->conditionsToAdd as $conditionData) {
            $type = $conditionData['type'] ?? ConditionType::default();
            $this->rule->conditions()->create([
                'type' => $type,
                'attribute' => $conditionData['attribute'],
                'operator' => $conditionData['operator']->value,
                'value' => $conditionData['value'],
            ]);
        }
    }

    /**
     * Apply value/rollout changes.
     */
    protected function applyValueChanges(): void
    {
        // Clear existing rollout if needed
        if ($this->shouldClearRollout) {
            $this->rule->rolloutVariants()->each(function ($variant) {
                $variant->value?->delete();
                $variant->delete();
            });
            $this->rule->update(['rollout_salt' => null]);
        }

        // Handle variant modifications (if not clearing)
        if (! $this->shouldClearRollout) {
            foreach ($this->variantsToRemove as $variantName) {
                $variant = $this->rule->rolloutVariants()
                    ->where('name', $variantName)
                    ->first();
                $variant?->value?->delete();
                $variant?->delete();
            }

            foreach ($this->variantsToModify as $variantName => $callback) {
                $variant = $this->rule->rolloutVariants()
                    ->where('name', $variantName)
                    ->first();

                if ($variant) {
                    $variantModifier = new VariantModifier($variant);
                    $callback($variantModifier);
                    $variantModifier->apply();
                }
            }
        }

        // Update direct value
        if ($this->shouldUpdateValue) {
            $valueModel = $this->rule->value;

            if ($valueModel) {
                $valueModel->update(['value' => $this->newValue]);
            } else {
                $this->rule->value()->create([
                    'valuable_type' => $this->rule->getMorphClass(),
                    'valuable_id' => $this->rule->id,
                    'value' => $this->newValue,
                ]);
            }
        }

        // Create new rollout
        if ($this->newRollout !== null) {
            // Delete any existing direct value
            $this->rule->value?->delete();

            // Set salt and create variants
            $this->rule->update(['rollout_salt' => Str::random(16)]);
            $this->newRollout->createFor($this->rule);
        }
    }

    /**
     * Get the underlying rule model.
     */
    public function getRule(): SettingRule
    {
        return $this->rule;
    }
}
