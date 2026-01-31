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
        $actual = $this->stringifyValue($actual);
        $expectedString = $this->stringifyValue($expected);

        $expectedList = array_values((array) $expected);

        return match ($operator) {
            ComparisonOperator::EQUALS => $actual === $expectedString,
            ComparisonOperator::NOT_EQUALS => $actual !== $expectedString,
            ComparisonOperator::CONTAINS_ANY => $this->containsAny($actual, $expectedList),
            ComparisonOperator::NOT_CONTAINS_ANY => ! $this->containsAny($actual, $expectedList),
            ComparisonOperator::STARTS_WITH_ANY => $this->startsWithAny($actual, $expectedList),
            ComparisonOperator::ENDS_WITH_ANY => $this->endsWithAny($actual, $expectedList),
            ComparisonOperator::MATCHES_REGEX => is_string($expected) && preg_match($expected, $actual) === 1,
            default => false,
        };
    }

    protected function evaluateNumericComparison(mixed $actual, mixed $expected, ComparisonOperator $operator): bool
    {
        $actualNumber = $this->toFloat($actual);
        if ($actualNumber === null) {
            return false;
        }

        return match ($operator) {
            ComparisonOperator::NUMBER_EQUALS => $actualNumber === $this->toFloat($expected),
            ComparisonOperator::NUMBER_NOT_EQUALS => $actualNumber !== $this->toFloat($expected),
            ComparisonOperator::NUMBER_GT => $actualNumber > ($this->toFloat($expected) ?? PHP_FLOAT_MAX),
            ComparisonOperator::NUMBER_GTE => $actualNumber >= ($this->toFloat($expected) ?? PHP_FLOAT_MAX),
            ComparisonOperator::NUMBER_LT => $actualNumber < ($this->toFloat($expected) ?? -PHP_FLOAT_MAX),
            ComparisonOperator::NUMBER_LTE => $actualNumber <= ($this->toFloat($expected) ?? -PHP_FLOAT_MAX),
            ComparisonOperator::NUMBER_BETWEEN => $this->isBetween($actualNumber, array_values((array) $expected)),
            default => false,
        };
    }

    protected function evaluateDateComparison(mixed $actual, mixed $expected, ComparisonOperator $operator): bool
    {
        $actualDate = $this->normalizeDateValue($actual);
        if (! $actualDate) {
            return false;
        }

        return match ($operator) {
            ComparisonOperator::DATE_EQUALS => $this->compareDate($actualDate, $expected, 'equal'),
            ComparisonOperator::DATE_NOT_EQUALS => ! $this->compareDate($actualDate, $expected, 'equal'),
            ComparisonOperator::DATE_GT => $this->compareDate($actualDate, $expected, 'gt'),
            ComparisonOperator::DATE_GTE => $this->compareDate($actualDate, $expected, 'gte'),
            ComparisonOperator::DATE_LT => $this->compareDate($actualDate, $expected, 'lt'),
            ComparisonOperator::DATE_LTE => $this->compareDate($actualDate, $expected, 'lte'),
            ComparisonOperator::DATE_BETWEEN => $this->isDateBetween($actualDate, array_values((array) $expected)),
            ComparisonOperator::TIME_BETWEEN => $this->isTimeBetween($actualDate, array_values((array) $expected)),
            ComparisonOperator::DAY_OF_WEEK => $this->isDayOfWeek($actualDate, $expected),
            ComparisonOperator::IS_BUSINESS_DAY => $this->isBusinessDay($actualDate),
            ComparisonOperator::IS_HOLIDAY => $this->isHoliday($actualDate, $expected),
            ComparisonOperator::SCHEDULE_CRON => is_string($expected) && $this->matchesCron($actualDate, $expected),
            default => false,
        };
    }

    protected function evaluateVersionComparison(mixed $actual, mixed $expected, ComparisonOperator $operator): bool
    {
        $actual = $this->stringifyValue($actual);

        if ($operator === ComparisonOperator::VERSION_BETWEEN) {
            return $this->isVersionBetween($actual, array_values(array_filter((array) $expected, 'is_string')));
        }

        $expected = $this->stringifyValue($expected);

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

        $expectedString = $this->stringifyValue($expected);
        $isInSegment = $this->segmentDriver
            ? $this->segmentDriver->isInSegment($this->user, $expectedString)
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

    /**
     * @param  array<int, mixed>  $needles
     */
    protected function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (! is_string($needle)) {
                continue;
            }
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, mixed>  $needles
     */
    protected function startsWithAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (! is_string($needle)) {
                continue;
            }
            if (str_starts_with($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, mixed>  $needles
     */
    protected function endsWithAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (! is_string($needle)) {
                continue;
            }
            if (str_ends_with($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, mixed>  $range
     */
    protected function isBetween(float $value, array $range): bool
    {
        if (count($range) !== 2) {
            return false;
        }

        [$min, $max] = $range;
        $minValue = $this->toFloat($min);
        $maxValue = $this->toFloat($max);
        if ($minValue === null || $maxValue === null) {
            return false;
        }

        return $value >= $minValue && $value <= $maxValue;
    }

    /**
     * @param  array<int, mixed>  $range
     */
    protected function isDateBetween(Carbon $date, array $range): bool
    {
        if (count($range) !== 2) {
            return false;
        }

        [$start, $end] = $range;

        $startDate = $this->normalizeDateValue($start);
        $endDate = $this->normalizeDateValue($end);
        if (! $startDate || ! $endDate) {
            return false;
        }

        return $date->between($startDate, $endDate);
    }

    /**
     * @param  array<int, mixed>  $range
     */
    protected function isTimeBetween(Carbon $date, array $range): bool
    {
        if (count($range) !== 2) {
            return false;
        }

        $startDate = $this->normalizeDateValue($range[0] ?? null);
        $endDate = $this->normalizeDateValue($range[1] ?? null);
        if (! $startDate || ! $endDate) {
            return false;
        }

        $currentTime = $date->format('H:i:s');
        $startTime = $startDate->format('H:i:s');
        $endTime = $endDate->format('H:i:s');

        return $this->isTimeWithinRange($currentTime, $startTime, $endTime);
    }

    protected function isDayOfWeek(Carbon $date, mixed $expected): bool
    {
        $days = array_map(fn ($day) => strtolower($this->stringifyValue($day)), (array) $expected);
        $currentDay = strtolower($date->format('l'));

        return in_array($currentDay, $days, true);
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

        $region = null;
        if (is_array($expected)) {
            $regionValue = $expected[0] ?? null;
            $region = is_string($regionValue) ? $regionValue : null;
        } elseif (is_string($expected)) {
            $region = $expected;
        }

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

    /**
     * @param  array<int, string>  $range
     */
    protected function isVersionBetween(string $version, array $range): bool
    {
        if (count($range) !== 2) {
            return false;
        }

        [$min, $max] = $range;

        return version_compare($version, $min, '>=') && version_compare($version, $max, '<=');
    }

    protected function stringifyValue(mixed $value): string
    {
        return match (true) {
            is_string($value) => $value,
            is_scalar($value) => (string) $value,
            is_object($value) && method_exists($value, '__toString') => (string) $value,
            default => $this->encodeJsonValue($value),
        };
    }

    protected function toFloat(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    protected function normalizeDateValue(mixed $value): ?Carbon
    {
        return match (true) {
            $value instanceof \DateTimeInterface => Carbon::instance($value),
            is_string($value) || is_int($value) || is_float($value) => $this->parseCarbonValue($value),
            default => null,
        };
    }

    protected function isTimeWithinRange(string $current, string $start, string $end): bool
    {
        $within = false;

        if ($start <= $end) {
            $within = $current >= $start && $current <= $end;
        } else {
            // Handles overnight ranges like 22:00 to 06:00
            $within = $current >= $start || $current <= $end;
        }

        return $within;
    }

    protected function parseCarbonValue(mixed $value): ?Carbon
    {
        $parsed = null;

        try {
            $parsed = Carbon::parse($value);
        } catch (\Exception) {
            $parsed = null;
        }

        return $parsed;
    }

    protected function encodeJsonValue(mixed $value): string
    {
        $encoded = json_encode($value);

        return $encoded === false ? '' : $encoded;
    }

    protected function compareDate(Carbon $actual, mixed $expected, string $operator): bool
    {
        $expectedDate = $this->normalizeDateValue($expected);
        if (! $expectedDate) {
            return false;
        }

        return match ($operator) {
            'equal' => $actual->equalTo($expectedDate),
            'gt' => $actual->greaterThan($expectedDate),
            'gte' => $actual->greaterThanOrEqualTo($expectedDate),
            'lt' => $actual->lessThan($expectedDate),
            'lte' => $actual->lessThanOrEqualTo($expectedDate),
            default => false,
        };
    }
}
