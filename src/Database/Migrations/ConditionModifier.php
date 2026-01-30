<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Database\Migrations;

use GaiaTools\FulcrumSettings\Enums\ComparisonOperator;
use GaiaTools\FulcrumSettings\Models\SettingRuleCondition;

/**
 * Fluent builder for modifying an existing condition in a migration.
 *
 * @example
 * ```php
 * $modifier->updateAttribute('user.subscription_tier')
 *     ->updateOperator(ComparisonOperator::In)
 *     ->updateValue(['premium', 'enterprise']);
 * ```
 */
class ConditionModifier
{
    /** @var array<string, mixed> */
    protected array $updates = [];

    public function __construct(
        protected readonly SettingRuleCondition $condition
    ) {}

    /**
     * Update the attribute being checked.
     */
    public function updateAttribute(string $attribute): self
    {
        $this->updates['attribute'] = $attribute;

        return $this;
    }

    /**
     * Update the condition type.
     */
    public function updateType(string $type): self
    {
        $this->updates['type'] = $type;

        return $this;
    }

    /**
     * Update the comparison operator.
     */
    public function updateOperator(string|ComparisonOperator $operator): self
    {
        $this->updates['operator'] = is_string($operator)
            ? $operator
            : $operator->value;

        return $this;
    }

    /**
     * Update the value being compared against.
     */
    public function updateValue(mixed $value): self
    {
        $this->updates['value'] = $value;

        return $this;
    }

    /**
     * Update multiple fields at once.
     */
    public function update(
        ?string $attribute = null,
        string|ComparisonOperator|null $operator = null,
        mixed $value = null,
        ?string $type = null
    ): self {
        if ($attribute !== null) {
            $this->updateAttribute($attribute);
        }

        if ($operator !== null) {
            $this->updateOperator($operator);
        }

        if ($value !== null) {
            $this->updateValue($value);
        }

        if ($type !== null) {
            $this->updateType($type);
        }

        return $this;
    }

    /**
     * Apply all modifications to the condition.
     */
    public function apply(): SettingRuleCondition
    {
        if (! empty($this->updates)) {
            $this->condition->update($this->updates);
        }

        return $this->condition->fresh() ?? $this->condition;
    }

    /**
     * Get the underlying condition model.
     */
    public function getCondition(): SettingRuleCondition
    {
        return $this->condition;
    }
}
