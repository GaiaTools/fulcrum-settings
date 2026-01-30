---
title: Comparison Operators
description: All available operators for rule conditions
---

# Comparison Operators

Rule conditions use operators to compare a field value against an expected value.

## String Operators

| Operator | Description | Example |
|----------|-------------|---------|
| `EQUALS` | Exact match | `email equals 'admin@example.com'` |
| `NOT_EQUALS` | Negated match | `status not_equals 'banned'` |
| `CONTAINS_ANY` | Value contains any of the strings | `tags contains_any ['beta', 'vip']` |
| `NOT_CONTAINS_ANY` | Value does not contain any of the strings | `tags not_contains_any ['banned']` |
| `STARTS_WITH_ANY` | Value starts with any prefix | `email starts_with_any ['admin@']` |
| `ENDS_WITH_ANY` | Value ends with any suffix | `email ends_with_any ['@company.com']` |
| `MATCHES_REGEX` | Regex match | `phone matches_regex '/^\+1/'` |

## Numeric Operators

| Operator | Description | Example |
|----------|-------------|---------|
| `NUMBER_EQUALS` | `=` | `age number_equals 18` |
| `NUMBER_NOT_EQUALS` | `!=` | `age number_not_equals 0` |
| `NUMBER_GT` | `>` | `orders number_gt 10` |
| `NUMBER_GTE` | `>=` | `orders number_gte 10` |
| `NUMBER_LT` | `<` | `cart_total number_lt 100` |
| `NUMBER_LTE` | `<=` | `cart_total number_lte 100` |
| `NUMBER_BETWEEN` | Range | `age number_between [18, 35]` |

## Date Operators

| Operator | Description | Example |
|----------|-------------|---------|
| `DATE_EQUALS` | Same date | `created_at date_equals '2024-01-01'` |
| `DATE_NOT_EQUALS` | Different date | `created_at date_not_equals '2024-01-01'` |
| `DATE_GT` | After date | `created_at date_gt '2024-01-01'` |
| `DATE_GTE` | On/after date | `created_at date_gte '2024-01-01'` |
| `DATE_LT` | Before date | `created_at date_lt '2024-01-01'` |
| `DATE_LTE` | On/before date | `created_at date_lte '2024-01-01'` |
| `DATE_BETWEEN` | Date range | `now date_between ['2024-12-01', '2024-12-31']` |

## Version Operators

| Operator | Description | Example |
|----------|-------------|---------|
| `VERSION_EQUALS` | SemVer equals | `app.version version_equals '2.1.0'` |
| `VERSION_NOT_EQUALS` | SemVer not equals | `app.version version_not_equals '2.1.0'` |
| `VERSION_GT` | SemVer `>` | `app.version version_gt '2.0.0'` |
| `VERSION_GTE` | SemVer `>=` | `app.version version_gte '2.0.0'` |
| `VERSION_LT` | SemVer `<` | `app.version version_lt '3.0.0'` |
| `VERSION_LTE` | SemVer `<=` | `app.version version_lte '3.0.0'` |
| `VERSION_BETWEEN` | SemVer range | `app.version version_between ['2.0.0', '3.0.0']` |

## Segment Operators

| Operator | Description | Example |
|----------|-------------|---------|
| `IN_SEGMENT` | User is in segment | `segment in_segment 'beta'` |
| `NOT_IN_SEGMENT` | User is not in segment | `segment not_in_segment 'beta'` |

## Boolean Operators

| Operator | Description | Example |
|----------|-------------|---------|
| `IS_TRUE` | Field is truthy | `is_internal is_true` |
| `IS_FALSE` | Field is falsy | `is_internal is_false` |

## Null Operators

| Operator | Description | Example |
|----------|-------------|---------|
| `IS_NULL` | Field is null | `deleted_at is_null` |
| `IS_NOT_NULL` | Field has value | `email_verified_at is_not_null` |

## Time and Schedule Operators

| Operator | Description | Example |
|----------|-------------|---------|
| `TIME_BETWEEN` | Time of day range | `now time_between ['09:00', '17:00']` |
| `DAY_OF_WEEK` | Day match | `now day_of_week ['mon', 'tue']` |
| `IS_BUSINESS_DAY` | Weekday (non-holiday) | `now is_business_day` |
| `IS_HOLIDAY` | Holiday check | `now is_holiday 'US'` |
| `SCHEDULE_CRON` | Cron expression | `now schedule_cron '0 9 * * MON-FRI'` |

::: warning
Operators like `CONTAINS_ANY`, `NUMBER_BETWEEN`, `DATE_BETWEEN`, and `VERSION_BETWEEN` expect array values.
:::

::: tip
Operators `IS_TRUE`, `IS_FALSE`, `IS_NULL`, and `IS_NOT_NULL` ignore the condition value. `IS_BUSINESS_DAY` and `IS_HOLIDAY` allow an optional value (for example, a region code for holidays).
:::
