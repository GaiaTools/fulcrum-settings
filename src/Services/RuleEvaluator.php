<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Services;

use Carbon\Carbon;
use Cron\CronExpression;
use GaiaTools\FulcrumSettings\Contracts\HolidayResolver;
use GaiaTools\FulcrumSettings\Contracts\RuleEvaluator as RuleEvaluatorContract;
use GaiaTools\FulcrumSettings\Contracts\SegmentDriver;
use GaiaTools\FulcrumSettings\Enums\ComparisonOperator;
use GaiaTools\FulcrumSettings\Enums\ConditionType;
use GaiaTools\FulcrumSettings\Models\SettingRule;
use GaiaTools\FulcrumSettings\Models\SettingRuleCondition;
use GaiaTools\FulcrumSettings\Support\ConditionTypeRegistry;
use Illuminate\Contracts\Auth\Authenticatable;

class RuleEvaluator implements RuleEvaluatorContract
{
    protected ?Authenticatable $user = null;

    protected ?HolidayResolver $holidayResolver = null;

    protected ConditionTypeRegistry $conditionTypeRegistry;

    /** @var array<string, \GaiaTools\FulcrumSettings\Contracts\ConditionTypeHandler> */
    protected array $conditionTypeHandlers = [];

    protected bool $evaluationPrepared = false;

    public function __construct(
        protected ?SegmentDriver $segmentDriver,
        ?HolidayResolver $holidayResolver = null,
        ?ConditionTypeRegistry $conditionTypeRegistry = null
    ) {
        $this->holidayResolver = $holidayResolver;
        $this->conditionTypeRegistry = $conditionTypeRegistry ?? app(ConditionTypeRegistry::class);
    }

    public function evaluateRule(SettingRule $rule, mixed $scope = null): bool
    {
        $this->prepareEvaluation();
        $this->evaluationPrepared = true;

        $conditions = $rule->conditions;

        try {
            if ($conditions->isEmpty()) {
                return true;
            }

            foreach ($conditions as $condition) {
                if (! $this->evaluateCondition($condition, $scope)) {
                    return false;
                }
            }

            return true;
        } finally {
            $this->finishEvaluation();
        }
    }

    public function evaluateCondition(SettingRuleCondition $condition, mixed $scope = null): bool
    {
        if (! $this->evaluationPrepared) {
            $this->prepareEvaluation();
            $this->evaluationPrepared = true;
        }

        $type = $condition->type ?? ConditionType::default();
        $field = $condition->attribute;
        $attributeValue = $this->resolveConditionValue($type, $field, $scope);
        $expectedValue = $condition->value;
        $operator = $condition->operator;

        if (! $operator->isSegmentOperator() && ! $attributeValue->exists) {
            return false;
        }

        return match (true) {
            $operator->isStringOperator() => $this->evaluateStringComparison($attributeValue->value, $expectedValue, $operator),
            $operator->isNumericOperator() => $this->evaluateNumericComparison($attributeValue->value, $expectedValue, $operator),
            $operator->isDateOperator() => $this->evaluateDateComparison($attributeValue->value, $expectedValue, $operator),
            $operator->isVersionOperator() => $this->evaluateVersionComparison($attributeValue->value, $expectedValue, $operator),
            $operator->isSegmentOperator() => $this->evaluateSegmentComparison($expectedValue, $operator),
            $operator->isBooleanOperator() => $this->evaluateBooleanComparison($attributeValue->value, $operator),
            $operator->isNullOperator() => $this->evaluateNullComparison($attributeValue->value, $operator),
            default => false,
        };
    }

    public function setUser(?Authenticatable $user): static
    {
        $this->user = $user;

        return $this;
    }

    protected function resolveConditionValue(string $type, string $field, mixed $scope): \GaiaTools\FulcrumSettings\Conditions\AttributeValue
    {
        $handler = $this->conditionTypeHandlers[$type]
            ??= $this->conditionTypeRegistry->getHandler($type);

        return $handler->resolve($field, $scope, $this->user);
    }

    protected function prepareEvaluation(): void
    {
        $this->conditionTypeHandlers = [];
    }

    protected function finishEvaluation(): void
    {
        $this->conditionTypeHandlers = [];
        $this->evaluationPrepared = false;
    }

    protected function evaluateStringComparison(mixed $actual, mixed $expected, ComparisonOperator $operator): bool
    {
        $actual = (string) $actual;

        return match ($operator) {
            ComparisonOperator::EQUALS => $actual === $expected,
            ComparisonOperator::NOT_EQUALS => $actual !== $expected,
            ComparisonOperator::CONTAINS_ANY => $this->containsAny($actual, (array) $expected),
            ComparisonOperator::NOT_CONTAINS_ANY => ! $this->containsAny($actual, (array) $expected),
            ComparisonOperator::STARTS_WITH_ANY => $this->startsWithAny($actual, (array) $expected),
            ComparisonOperator::ENDS_WITH_ANY => $this->endsWithAny($actual, (array) $expected),
            ComparisonOperator::MATCHES_REGEX => (bool) preg_match($expected, $actual),
            default => false,
        };
    }

    protected function evaluateNumericComparison(mixed $actual, mixed $expected, ComparisonOperator $operator): bool
    {
        $actual = (float) $actual;

        return match ($operator) {
            ComparisonOperator::NUMBER_EQUALS => $actual === (float) $expected,
            ComparisonOperator::NUMBER_NOT_EQUALS => $actual !== (float) $expected,
            ComparisonOperator::NUMBER_GT => $actual > (float) $expected,
            ComparisonOperator::NUMBER_GTE => $actual >= (float) $expected,
            ComparisonOperator::NUMBER_LT => $actual < (float) $expected,
            ComparisonOperator::NUMBER_LTE => $actual <= (float) $expected,
            ComparisonOperator::NUMBER_BETWEEN => $this->isBetween($actual, (array) $expected),
            default => false,
        };
    }

    protected function evaluateDateComparison(mixed $actual, mixed $expected, ComparisonOperator $operator): bool
    {
        try {
            $actual = Carbon::parse($actual);
        } catch (\Exception) {
            return false;
        }

        return match ($operator) {
            ComparisonOperator::DATE_EQUALS => $actual->equalTo($expected),
            ComparisonOperator::DATE_NOT_EQUALS => ! $actual->equalTo($expected),
            ComparisonOperator::DATE_GT => $actual->greaterThan($expected),
            ComparisonOperator::DATE_GTE => $actual->greaterThanOrEqualTo($expected),
            ComparisonOperator::DATE_LT => $actual->lessThan($expected),
            ComparisonOperator::DATE_LTE => $actual->lessThanOrEqualTo($expected),
            ComparisonOperator::DATE_BETWEEN => $this->isDateBetween($actual, (array) $expected),
            ComparisonOperator::TIME_BETWEEN => $this->isTimeBetween($actual, (array) $expected),
            ComparisonOperator::DAY_OF_WEEK => $this->isDayOfWeek($actual, $expected),
            ComparisonOperator::IS_BUSINESS_DAY => $this->isBusinessDay($actual),
            ComparisonOperator::IS_HOLIDAY => $this->isHoliday($actual, $expected),
            ComparisonOperator::SCHEDULE_CRON => $this->matchesCron($actual, (string) $expected),
            default => false,
        };
    }

    protected function evaluateVersionComparison(mixed $actual, mixed $expected, ComparisonOperator $operator): bool
    {
        $actual = (string) $actual;

        if ($operator === ComparisonOperator::VERSION_BETWEEN) {
            return $this->isVersionBetween($actual, (array) $expected);
        }

        $expected = (string) $expected;

        return match ($operator) {
            ComparisonOperator::VERSION_EQUALS => version_compare($actual, $expected, '='),
            ComparisonOperator::VERSION_NOT_EQUALS => version_compare($actual, $expected, '!='),
            ComparisonOperator::VERSION_GT => version_compare($actual, $expected, '>'),
            ComparisonOperator::VERSION_GTE => version_compare($actual, $expected, '>='),
            ComparisonOperator::VERSION_LT => version_compare($actual, $expected, '<'),
            ComparisonOperator::VERSION_LTE => version_compare($actual, $expected, '<='),
            default => false,
        };
    }

    protected function evaluateSegmentComparison(mixed $expected, ComparisonOperator $operator): bool
    {
        if (! $this->user) {
            return false;
        }

        $isInSegment = $this->segmentDriver
            ? $this->segmentDriver->isInSegment($this->user, (string) $expected)
            : false;

        return match ($operator) {
            ComparisonOperator::IN_SEGMENT => $isInSegment,
            ComparisonOperator::NOT_IN_SEGMENT => ! $isInSegment,
            default => false,
        };
    }

    protected function evaluateBooleanComparison(mixed $actual, ComparisonOperator $operator): bool
    {
        return match ($operator) {
            ComparisonOperator::IS_TRUE => (bool) $actual === true,
            ComparisonOperator::IS_FALSE => (bool) $actual === false,
            default => false,
        };
    }

    protected function evaluateNullComparison(mixed $actual, ComparisonOperator $operator): bool
    {
        return match ($operator) {
            ComparisonOperator::IS_NULL => $actual === null,
            ComparisonOperator::IS_NOT_NULL => $actual !== null,
            default => false,
        };
    }

    protected function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    protected function startsWithAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_starts_with($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    protected function endsWithAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_ends_with($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    protected function isBetween(float $value, array $range): bool
    {
        if (count($range) !== 2) {
            return false;
        }

        [$min, $max] = $range;

        return $value >= (float) $min && $value <= (float) $max;
    }

    protected function isDateBetween(Carbon $date, array $range): bool
    {
        if (count($range) !== 2) {
            return false;
        }

        [$start, $end] = $range;

        try {
            $start = Carbon::parse($start);
            $end = Carbon::parse($end);
        } catch (\Exception) {
            return false;
        }

        return $date->between($start, $end);
    }

    protected function isTimeBetween(Carbon $date, array $range): bool
    {
        if (count($range) !== 2) {
            return false;
        }

        $currentTime = $date->format('H:i:s');
        $startTime = Carbon::parse($range[0])->format('H:i:s');
        $endTime = Carbon::parse($range[1])->format('H:i:s');

        if ($startTime <= $endTime) {
            return $currentTime >= $startTime && $currentTime <= $endTime;
        }

        // Handles overnight ranges like 22:00 to 06:00
        return $currentTime >= $startTime || $currentTime <= $endTime;
    }

    protected function isDayOfWeek(Carbon $date, mixed $expected): bool
    {
        $days = (array) $expected;
        $currentDay = strtolower($date->format('l'));

        return in_array($currentDay, array_map('strtolower', $days), true);
    }

    protected function isBusinessDay(Carbon $date): bool
    {
        // Default implementation: Mon-Fri
        return ! $date->isWeekend();
    }

    protected function isHoliday(Carbon $date, mixed $expected): bool
    {
        if (! $this->holidayResolver) {
            return false;
        }

        $region = is_array($expected) ? ($expected[0] ?? null) : $expected;

        return $this->holidayResolver->isHoliday($date, $region);
    }

    protected function matchesCron(Carbon $date, string $expression): bool
    {
        if (! $this->isCronExpressionClassAvailable()) {
            return false;
        }

        return (new CronExpression($expression))->isDue($date->toDateTimeString());
    }

    /** @codeCoverageIgnore */
    protected function isCronExpressionClassAvailable(): bool
    {
        return class_exists(CronExpression::class);
    }

    protected function isVersionBetween(string $version, array $range): bool
    {
        if (count($range) !== 2) {
            return false;
        }

        [$min, $max] = $range;

        return version_compare($version, $min, '>=') && version_compare($version, $max, '<=');
    }
}
