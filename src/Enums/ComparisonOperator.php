<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Enums;

enum ComparisonOperator: string
{
    // String comparisons
    case EQUALS = 'equals';
    case NOT_EQUALS = 'not_equals';
    case CONTAINS_ANY = 'contains_any';
    case NOT_CONTAINS_ANY = 'not_contains_any';
    case STARTS_WITH_ANY = 'starts_with_any';
    case ENDS_WITH_ANY = 'ends_with_any';
    case MATCHES_REGEX = 'matches_regex';

    // Numeric comparisons
    case NUMBER_EQUALS = 'number_equals';
    case NUMBER_NOT_EQUALS = 'number_not_equals';
    case NUMBER_GT = 'number_gt';
    case NUMBER_GTE = 'number_gte';
    case NUMBER_LT = 'number_lt';
    case NUMBER_LTE = 'number_lte';
    case NUMBER_BETWEEN = 'number_between';

    // Date comparisons
    case DATE_EQUALS = 'date_equals';
    case DATE_NOT_EQUALS = 'date_not_equals';
    case DATE_GT = 'date_gt';
    case DATE_GTE = 'date_gte';
    case DATE_LT = 'date_lt';
    case DATE_LTE = 'date_lte';
    case DATE_BETWEEN = 'date_between';

    // Version comparisons
    case VERSION_EQUALS = 'version_equals';
    case VERSION_NOT_EQUALS = 'version_not_equals';
    case VERSION_GT = 'version_gt';
    case VERSION_GTE = 'version_gte';
    case VERSION_LT = 'version_lt';
    case VERSION_LTE = 'version_lte';
    case VERSION_BETWEEN = 'version_between';

    // Segment comparisons
    case IN_SEGMENT = 'in_segment';
    case NOT_IN_SEGMENT = 'not_in_segment';

    // Boolean comparisons
    case IS_TRUE = 'is_true';
    case IS_FALSE = 'is_false';

    // Null comparisons
    case IS_NULL = 'is_null';
    case IS_NOT_NULL = 'is_not_null';

    // Time/Schedule comparisons
    case TIME_BETWEEN = 'time_between';
    case DAY_OF_WEEK = 'day_of_week';
    case IS_BUSINESS_DAY = 'is_business_day';
    case IS_HOLIDAY = 'is_holiday';
    case SCHEDULE_CRON = 'schedule_cron';

    public function isStringOperator(): bool
    {
        return match ($this) {
            self::EQUALS,
            self::NOT_EQUALS,
            self::CONTAINS_ANY,
            self::NOT_CONTAINS_ANY,
            self::STARTS_WITH_ANY,
            self::ENDS_WITH_ANY,
            self::MATCHES_REGEX => true,
            default => false,
        };
    }

    public function isNumericOperator(): bool
    {
        return match ($this) {
            self::NUMBER_EQUALS,
            self::NUMBER_NOT_EQUALS,
            self::NUMBER_GT,
            self::NUMBER_GTE,
            self::NUMBER_LT,
            self::NUMBER_LTE,
            self::NUMBER_BETWEEN => true,
            default => false,
        };
    }

    public function isDateOperator(): bool
    {
        return match ($this) {
            self::DATE_EQUALS,
            self::DATE_NOT_EQUALS,
            self::DATE_GT,
            self::DATE_GTE,
            self::DATE_LT,
            self::DATE_LTE,
            self::DATE_BETWEEN,
            self::TIME_BETWEEN,
            self::DAY_OF_WEEK,
            self::IS_BUSINESS_DAY,
            self::IS_HOLIDAY,
            self::SCHEDULE_CRON => true,
            default => false,
        };
    }

    public function isVersionOperator(): bool
    {
        return match ($this) {
            self::VERSION_EQUALS,
            self::VERSION_NOT_EQUALS,
            self::VERSION_GT,
            self::VERSION_GTE,
            self::VERSION_LT,
            self::VERSION_LTE,
            self::VERSION_BETWEEN => true,
            default => false,
        };
    }

    public function isSegmentOperator(): bool
    {
        return match ($this) {
            self::IN_SEGMENT,
            self::NOT_IN_SEGMENT => true,
            default => false,
        };
    }

    public function isBooleanOperator(): bool
    {
        return match ($this) {
            self::IS_TRUE,
            self::IS_FALSE => true,
            default => false,
        };
    }

    public function isNullOperator(): bool
    {
        return match ($this) {
            self::IS_NULL,
            self::IS_NOT_NULL => true,
            default => false,
        };
    }

    public function requiresValue(): bool
    {
        return ! match ($this) {
            self::IS_TRUE,
            self::IS_FALSE,
            self::IS_NULL,
            self::IS_NOT_NULL,
            self::IS_BUSINESS_DAY,
            self::IS_HOLIDAY => true,
            default => false,
        };
    }

    public function requiresArrayValue(): bool
    {
        return match ($this) {
            self::CONTAINS_ANY,
            self::NOT_CONTAINS_ANY,
            self::STARTS_WITH_ANY,
            self::ENDS_WITH_ANY,
            self::NUMBER_BETWEEN,
            self::DATE_BETWEEN,
            self::TIME_BETWEEN,
            self::VERSION_BETWEEN => true,
            default => false,
        };
    }
}
