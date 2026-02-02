<?php

namespace GaiaTools\FulcrumSettings\Support\Builders;

use Closure;
use GaiaTools\FulcrumSettings\Enums\ComparisonOperator;
use GaiaTools\FulcrumSettings\Enums\ConditionType;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Models\SettingRule;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RuleBuilder
{
    protected ?string $name = null;

    protected RuleConditionBuilder $conditionBuilder;

    protected mixed $value = null;

    protected int $priority = 0;

    protected ?RolloutBuilder $rolloutBuilder = null;

    protected ?string $rolloutSalt = null;

    public function __construct()
    {
        $this->conditionBuilder = new RuleConditionBuilder;
    }

    /**
     * Set the rule name.
     */
    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Build conditions using a closure.
     * Useful for complex condition groups.
     */
    public function conditions(Closure $callback): self
    {
        $callback($this->conditionBuilder);

        return $this;
    }

    /**
     * Shorthand for adding a single condition.
     * Delegates to the condition builder.
     */
    public function when(
        string $attribute,
        string|ComparisonOperator $operator,
        mixed $value = null,
        ?string $type = null
    ): self {
        $this->conditionBuilder->where($attribute, $operator, $value, $type);

        return $this;
    }

    /**
     * Shorthand for adding a typed condition.
     */
    public function whenType(
        string $type,
        string $attribute,
        string|ComparisonOperator $operator,
        mixed $value = null
    ): self {
        $this->conditionBuilder->whereType($type, $attribute, $operator, $value);

        return $this;
    }

    /**
     * Set the value returned when this rule matches.
     * Mutually exclusive with rollout().
     */
    public function then(mixed $value): self
    {
        if ($this->rolloutBuilder !== null) {
            throw new InvalidArgumentException(
                'Cannot set a direct value on a rule with rollout variants. Use either then() or rollout(), not both.'
            );
        }

        $this->value = $value;

        return $this;
    }

    /**
     * Switch to rollout mode for percentage-based variants.
     * Mutually exclusive with then().
     *
     * Usage:
     * ```php
     * $builder->rollout(function (RolloutBuilder $rollout) {
     *     $rollout
     *         ->variant('control', 50, 'old-checkout')
     *         ->variant('treatment', 50, 'new-checkout');
     * });
     * ```
     */
    public function rollout(Closure $callback): self
    {
        if ($this->value !== null) {
            throw new InvalidArgumentException(
                'Cannot add rollout variants to a rule with a direct value. Use either then() or rollout(), not both.'
            );
        }

        $this->rolloutBuilder = new RolloutBuilder;
        $callback($this->rolloutBuilder);

        // Generate a salt if not explicitly set
        if ($this->rolloutSalt === null) {
            $this->rolloutSalt = Str::random(16);
        }

        return $this;
    }

    /**
     * Set a custom salt for rollout bucket calculation.
     * Changing the salt will re-randomize all user assignments.
     */
    public function salt(string $salt): self
    {
        $this->rolloutSalt = $salt;

        return $this;
    }

    /**
     * Set rule priority (lower = higher priority, evaluated first).
     */
    public function priority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * Check if this builder is configured for rollout variants.
     */
    public function isRollout(): bool
    {
        return $this->rolloutBuilder !== null;
    }

    /**
     * Create the rule and attach it to the setting.
     */
    public function createFor(Setting $setting): SettingRule
    {
        $rule = $setting->rules()->create([
            'name' => $this->name,
            'priority' => $this->priority,
            'rollout_salt' => $this->rolloutBuilder !== null ? $this->rolloutSalt : null,
        ]);

        // Create all conditions
        foreach ($this->conditionBuilder->getConditions() as $condition) {
            $type = $condition['type'] ?? ConditionType::default();
            $rule->conditions()->create([
                'type' => $type,
                'attribute' => $condition['attribute'],
                'operator' => $condition['operator']->value,
                'value' => $condition['value'],
            ]);
        }

        if ($this->rolloutBuilder !== null) {
            $this->rolloutBuilder->createFor($rule);
        } elseif ($this->value !== null) {
            $rule->value()->create([
                'valuable_type' => $rule->getMorphClass(),
                'valuable_id' => $rule->id,
                'value' => $this->value,
            ]);
        }

        return $rule;
    }
}
