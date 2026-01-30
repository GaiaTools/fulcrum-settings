<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Database\Migrations;

use Closure;
use GaiaTools\FulcrumSettings\Enums\ComparisonOperator;
use GaiaTools\FulcrumSettings\Enums\ConditionType;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Models\SettingRule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Fluent builder for defining rules in migrations.
 *
 * @example
 * ```php
 * $rule->name('premium_users')
 *     ->priority(10)
 *     ->when('user.plan', 'equals', 'premium')
 *     ->when('user.active', 'is_true')
 *     ->then(true);
 * ```
 * @example With rollout
 * ```php
 * $rule->name('ab_test')
 *     ->when('user.country', 'in', ['US', 'CA'])
 *     ->rollout(function (RolloutDefinition $rollout) {
 *         $rollout->variant('control', 50, 'old_checkout')
 *             ->variant('treatment', 50, 'new_checkout');
 *     });
 * ```
 * @example With time bounds
 * ```php
 * $rule->name('holiday_promo')
 *     ->startsAt('2025-12-01')
 *     ->endsAt('2025-12-31')
 *     ->then('holiday_theme');
 * ```
 */
class RuleDefinition
{
    protected ?string $name = null;

    protected int $priority = 0;

    /** @var array<int, array{type: string|null, attribute: string, operator: ComparisonOperator, value: mixed}> */
    protected array $conditions = [];

    protected mixed $value = null;

    protected ?RolloutDefinition $rollout = null;

    protected ?string $salt = null;

    protected ?Carbon $startsAt = null;

    protected ?Carbon $endsAt = null;

    /**
     * Set the rule name.
     */
    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Set the rule priority (lower = higher priority).
     */
    public function priority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * Add a condition for when this rule should apply.
     */
    public function when(
        string $attribute,
        string|ComparisonOperator $operator,
        mixed $value = null,
        ?string $type = null
    ): self {
        $operator = is_string($operator)
            ? ComparisonOperator::from($operator)
            : $operator;

        $this->conditions[] = [
            'type' => $type,
            'attribute' => $attribute,
            'operator' => $operator,
            'value' => $value,
        ];

        return $this;
    }

    /**
     * Add a typed condition for when this rule should apply.
     */
    public function whenType(
        string $type,
        string $attribute,
        string|ComparisonOperator $operator,
        mixed $value = null
    ): self {
        return $this->when($attribute, $operator, $value, $type);
    }

    /**
     * Shorthand: attribute equals value (string comparison).
     */
    public function whereEquals(string $attribute, mixed $value): self
    {
        return $this->when($attribute, ComparisonOperator::EQUALS, $value);
    }

    /**
     * Shorthand: attribute not equals value (string comparison).
     */
    public function whereNotEquals(string $attribute, mixed $value): self
    {
        return $this->when($attribute, ComparisonOperator::NOT_EQUALS, $value);
    }

    /**
     * Shorthand: attribute is in segment.
     */
    public function whereInSegment(string $attribute, mixed $segment): self
    {
        return $this->when($attribute, ComparisonOperator::IN_SEGMENT, $segment);
    }

    /**
     * Shorthand: attribute is not in segment.
     */
    public function whereNotInSegment(string $attribute, mixed $segment): self
    {
        return $this->when($attribute, ComparisonOperator::NOT_IN_SEGMENT, $segment);
    }

    /**
     * Shorthand: attribute is truthy.
     */
    public function whereTrue(string $attribute): self
    {
        return $this->when($attribute, ComparisonOperator::IS_TRUE);
    }

    /**
     * Shorthand: attribute is falsy.
     */
    public function whereFalse(string $attribute): self
    {
        return $this->when($attribute, ComparisonOperator::IS_FALSE);
    }

    /**
     * Shorthand: attribute is null.
     */
    public function whereNull(string $attribute): self
    {
        return $this->when($attribute, ComparisonOperator::IS_NULL);
    }

    /**
     * Shorthand: attribute is not null.
     */
    public function whereNotNull(string $attribute): self
    {
        return $this->when($attribute, ComparisonOperator::IS_NOT_NULL);
    }

    /**
     * Shorthand: attribute contains any of the given strings.
     */
    public function whereContainsAny(string $attribute, array $values): self
    {
        return $this->when($attribute, ComparisonOperator::CONTAINS_ANY, $values);
    }

    /**
     * Shorthand: attribute does not contain any of the given strings.
     */
    public function whereNotContainsAny(string $attribute, array $values): self
    {
        return $this->when($attribute, ComparisonOperator::NOT_CONTAINS_ANY, $values);
    }

    /**
     * Shorthand: attribute starts with any of the given strings.
     */
    public function whereStartsWithAny(string $attribute, array $values): self
    {
        return $this->when($attribute, ComparisonOperator::STARTS_WITH_ANY, $values);
    }

    /**
     * Shorthand: attribute ends with any of the given strings.
     */
    public function whereEndsWithAny(string $attribute, array $values): self
    {
        return $this->when($attribute, ComparisonOperator::ENDS_WITH_ANY, $values);
    }

    /**
     * Shorthand: attribute matches regex pattern.
     */
    public function whereMatchesRegex(string $attribute, string $pattern): self
    {
        return $this->when($attribute, ComparisonOperator::MATCHES_REGEX, $pattern);
    }

    /**
     * Shorthand: numeric attribute equals value.
     */
    public function whereNumberEquals(string $attribute, int|float $value): self
    {
        return $this->when($attribute, ComparisonOperator::NUMBER_EQUALS, $value);
    }

    /**
     * Shorthand: numeric attribute is greater than value.
     */
    public function whereNumberGreaterThan(string $attribute, int|float $value): self
    {
        return $this->when($attribute, ComparisonOperator::NUMBER_GT, $value);
    }

    /**
     * Shorthand: numeric attribute is greater than or equal to value.
     */
    public function whereNumberGreaterThanOrEqual(string $attribute, int|float $value): self
    {
        return $this->when($attribute, ComparisonOperator::NUMBER_GTE, $value);
    }

    /**
     * Shorthand: numeric attribute is less than value.
     */
    public function whereNumberLessThan(string $attribute, int|float $value): self
    {
        return $this->when($attribute, ComparisonOperator::NUMBER_LT, $value);
    }

    /**
     * Shorthand: numeric attribute is less than or equal to value.
     */
    public function whereNumberLessThanOrEqual(string $attribute, int|float $value): self
    {
        return $this->when($attribute, ComparisonOperator::NUMBER_LTE, $value);
    }

    /**
     * Shorthand: numeric attribute is between two values (inclusive).
     */
    public function whereNumberBetween(string $attribute, int|float $min, int|float $max): self
    {
        return $this->when($attribute, ComparisonOperator::NUMBER_BETWEEN, [$min, $max]);
    }

    /**
     * Shorthand: date attribute equals value.
     */
    public function whereDateEquals(string $attribute, string $value): self
    {
        return $this->when($attribute, ComparisonOperator::DATE_EQUALS, $value);
    }

    /**
     * Shorthand: date attribute is after value.
     */
    public function whereDateAfter(string $attribute, string $value): self
    {
        return $this->when($attribute, ComparisonOperator::DATE_GT, $value);
    }

    /**
     * Shorthand: date attribute is before value.
     */
    public function whereDateBefore(string $attribute, string $value): self
    {
        return $this->when($attribute, ComparisonOperator::DATE_LT, $value);
    }

    /**
     * Shorthand: date attribute is between two dates.
     */
    public function whereDateBetween(string $attribute, string $start, string $end): self
    {
        return $this->when($attribute, ComparisonOperator::DATE_BETWEEN, [$start, $end]);
    }

    /**
     * Shorthand: version attribute equals value.
     */
    public function whereVersionEquals(string $attribute, string $value): self
    {
        return $this->when($attribute, ComparisonOperator::VERSION_EQUALS, $value);
    }

    /**
     * Shorthand: version attribute is greater than value.
     */
    public function whereVersionGreaterThan(string $attribute, string $value): self
    {
        return $this->when($attribute, ComparisonOperator::VERSION_GT, $value);
    }

    /**
     * Shorthand: version attribute is greater than or equal to value.
     */
    public function whereVersionGreaterThanOrEqual(string $attribute, string $value): self
    {
        return $this->when($attribute, ComparisonOperator::VERSION_GTE, $value);
    }

    /**
     * Shorthand: version attribute is less than value.
     */
    public function whereVersionLessThan(string $attribute, string $value): self
    {
        return $this->when($attribute, ComparisonOperator::VERSION_LT, $value);
    }

    /**
     * Shorthand: time is between two times.
     */
    public function whereTimeBetween(string $start, string $end, string $attribute = 'now'): self
    {
        if ($attribute === 'now') {
            return $this->whenType(ConditionType::DATE_TIME->value, $attribute, ComparisonOperator::TIME_BETWEEN, [$start, $end]);
        }

        return $this->when($attribute, ComparisonOperator::TIME_BETWEEN, [$start, $end]);
    }

    /**
     * Shorthand: day of week is one of given days.
     */
    public function whereDayOfWeek(array|string $days, string $attribute = 'now'): self
    {
        if ($attribute === 'now') {
            return $this->whenType(ConditionType::DATE_TIME->value, $attribute, ComparisonOperator::DAY_OF_WEEK, (array) $days);
        }

        return $this->when($attribute, ComparisonOperator::DAY_OF_WEEK, (array) $days);
    }

    /**
     * Shorthand: day is a business day (Mon-Fri).
     */
    public function whereBusinessDay(string $attribute = 'now'): self
    {
        if ($attribute === 'now') {
            return $this->whenType(ConditionType::DATE_TIME->value, $attribute, ComparisonOperator::IS_BUSINESS_DAY);
        }

        return $this->when($attribute, ComparisonOperator::IS_BUSINESS_DAY);
    }

    /**
     * Shorthand: day is a holiday.
     */
    public function whereHoliday(string $attribute = 'now'): self
    {
        if ($attribute === 'now') {
            return $this->whenType(ConditionType::DATE_TIME->value, $attribute, ComparisonOperator::IS_HOLIDAY);
        }

        return $this->when($attribute, ComparisonOperator::IS_HOLIDAY);
    }

    /**
     * Shorthand: scheduled via cron expression.
     */
    public function whereCron(string $expression, string $attribute = 'now'): self
    {
        if ($attribute === 'now') {
            return $this->whenType(ConditionType::DATE_TIME->value, $attribute, ComparisonOperator::SCHEDULE_CRON, $expression);
        }

        return $this->when($attribute, ComparisonOperator::SCHEDULE_CRON, $expression);
    }

    /**
     * Set the value returned when this rule matches.
     * Mutually exclusive with rollout().
     */
    public function then(mixed $value): self
    {
        if ($this->rollout !== null) {
            throw new InvalidArgumentException(
                'Cannot set a direct value on a rule with rollout variants. Use either then() or rollout(), not both.'
            );
        }

        $this->value = $value;

        return $this;
    }

    /**
     * Configure percentage-based rollout variants.
     * Mutually exclusive with then().
     */
    public function rollout(Closure $callback): self
    {
        if ($this->value !== null) {
            throw new InvalidArgumentException(
                'Cannot add rollout variants to a rule with a direct value. Use either then() or rollout(), not both.'
            );
        }

        $this->rollout = new RolloutDefinition;
        $callback($this->rollout);

        if ($this->salt === null) {
            $this->salt = Str::random(16);
        }

        return $this;
    }

    /**
     * Set a custom salt for rollout bucket calculation.
     */
    public function salt(string $salt): self
    {
        $this->salt = $salt;

        return $this;
    }

    /**
     * Set when this rule becomes active.
     */
    public function startsAt(string|Carbon $dateTime): self
    {
        $this->startsAt = $dateTime instanceof Carbon
            ? $dateTime
            : Carbon::parse($dateTime);

        return $this;
    }

    /**
     * Set when this rule expires.
     */
    public function endsAt(string|Carbon $dateTime): self
    {
        $this->endsAt = $dateTime instanceof Carbon
            ? $dateTime
            : Carbon::parse($dateTime);

        return $this;
    }

    /**
     * Set both start and end times for a time-bounded rule.
     */
    public function between(string|Carbon $start, string|Carbon $end): self
    {
        return $this->startsAt($start)->endsAt($end);
    }

    /**
     * Get the conditions array.
     *
     * @return array<int, array{type: string|null, attribute: string, operator: ComparisonOperator, value: mixed}>
     */
    public function getConditions(): array
    {
        return $this->conditions;
    }

    /**
     * Check if this is a rollout rule.
     */
    public function isRollout(): bool
    {
        return $this->rollout !== null;
    }

    /**
     * Create the rule and attach it to the setting.
     */
    public function createFor(Setting $setting): SettingRule
    {
        $rule = $setting->rules()->create([
            'name' => $this->name,
            'priority' => $this->priority,
            'rollout_salt' => $this->rollout !== null ? $this->salt : null,
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
        ]);

        // Create all conditions
        foreach ($this->conditions as $condition) {
            $type = $condition['type'] ?? ConditionType::default();
            $rule->conditions()->create([
                'type' => $type,
                'attribute' => $condition['attribute'],
                'operator' => $condition['operator']->value,
                'value' => $condition['value'],
            ]);
        }

        // Create rollout variants or direct value
        if ($this->rollout !== null) {
            $this->rollout->createFor($rule);
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
