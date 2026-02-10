# Targeting Rules Reference

Complete reference for all condition types, operators, and rollout strategies in Fulcrum Settings.

## Table of Contents

1. [Rule Structure](#rule-structure)
2. [Condition Types](#condition-types)
3. [String Operators](#string-operators)
4. [Numeric Operators](#numeric-operators)
5. [Date Operators](#date-operators)
6. [Version Operators](#version-operators)
7. [Segment Operators](#segment-operators)
8. [Boolean & Null Operators](#boolean--null-operators)
9. [Time & Schedule Operators](#time--schedule-operators)
10. [Rollout Configuration](#rollout-configuration)
11. [Priority & Conflict Resolution](#priority--conflict-resolution)
12. [Attribute Presence Rules](#attribute-presence-rules)

---

## Rule Structure

A rule has:
- **Conditions** — All must match (AND logic). Each condition has a type, field, operator, and expected value.
- **Value** — Returned when all conditions match (set via `->then($value)`).
- **Priority** — Lower number = evaluated first. Equal priority → earliest-created wins.

## Condition Types

Each condition has a **type** that determines how the field is resolved from context.

| Type | Resolver | Example Fields |
|------|----------|----------------|
| `user` (default) | Authenticated user or explicit scope | `id`, `email`, `subscription.plan`, any model attribute |
| `geocoding` | Configured `GeoResolver` | `country`, `city`, `region` |
| `user_agent` | Configured `UserAgentResolver` | `browser`, `os`, `device` |
| `date_time` | Configured timezone/clock | `now`, `day_of_week` |

Custom condition types can be registered in `config/fulcrum.php` under `condition_types`.

### Using explicit condition types

```php
// Shorthand (defaults to 'user' type)
$rule->whereEquals('country', 'US');

// Explicit type
$rule->whenType('geocoding', 'country', 'equals', 'UK');
$rule->whenType('user_agent', 'browser', 'equals', 'Chrome');
$rule->whenType('date_time', 'now', 'date_between', ['2025-12-01', '2025-12-31']);
```

---

## String Operators

| Operator | Builder Helper | Expected Value |
|----------|---------------|----------------|
| `EQUALS` | `whereEquals($field, $value)` | string |
| `NOT_EQUALS` | `whereNotEquals($field, $value)` | string |
| `CONTAINS_ANY` | `whereContainsAny($field, array)` | array of strings |
| `NOT_CONTAINS_ANY` | `whereNotContainsAny($field, array)` | array of strings |
| `STARTS_WITH_ANY` | `whereStartsWithAny($field, array)` | array of strings |
| `ENDS_WITH_ANY` | `whereEndsWithAny($field, array)` | array of strings |
| `MATCHES_REGEX` | `when($field, 'matches_regex', $pattern)` | regex string |

Values are cast to strings before comparison. `EQUALS` uses strict `===`.

---

## Numeric Operators

| Operator | Builder Helper | Expected Value |
|----------|---------------|----------------|
| `NUMBER_EQUALS` | `whereNumberEquals($field, $value)` | number |
| `NUMBER_NOT_EQUALS` | `whereNumberNotEquals($field, $value)` | number |
| `NUMBER_GT` | `whereNumberGreaterThan($field, $value)` | number |
| `NUMBER_GTE` | `whereNumberGreaterThanOrEqual($field, $value)` | number |
| `NUMBER_LT` | `whereNumberLessThan($field, $value)` | number |
| `NUMBER_LTE` | `whereNumberLessThanOrEqual($field, $value)` | number |
| `NUMBER_BETWEEN` | `whereNumberBetween($field, [$min, $max])` | array [min, max] |

Both actual and expected values are cast to floats.

---

## Date Operators

| Operator | Builder Helper | Expected Value |
|----------|---------------|----------------|
| `DATE_EQUALS` | `whereDateEquals($field, $value)` | date string |
| `DATE_NOT_EQUALS` | `whereDateNotEquals($field, $value)` | date string |
| `DATE_GT` | `whereDateAfter($field, $value)` | date string |
| `DATE_GTE` | `whereDateAfterOrEqual($field, $value)` | date string |
| `DATE_LT` | `whereDateBefore($field, $value)` | date string |
| `DATE_LTE` | `whereDateBeforeOrEqual($field, $value)` | date string |
| `DATE_BETWEEN` | `between($start, $end)` | array [start, end] |

Actual values are parsed into Carbon instances.

---

## Version Operators

| Operator | Description | Expected Value |
|----------|-------------|----------------|
| `VERSION_EQUALS` | SemVer `==` | version string |
| `VERSION_NOT_EQUALS` | SemVer `!=` | version string |
| `VERSION_GT` | SemVer `>` | version string |
| `VERSION_GTE` | SemVer `>=` | version string |
| `VERSION_LT` | SemVer `<` | version string |
| `VERSION_LTE` | SemVer `<=` | version string |
| `VERSION_BETWEEN` | SemVer range | array [min, max] |

Uses PHP's `version_compare()`. Ideal for mobile app version targeting.

```php
$rule->whereVersionGreaterThanOrEqual('app_version', '2.0.0')->then(true);
```

---

## Segment Operators

| Operator | Builder Helper | Expected Value |
|----------|---------------|----------------|
| `IN_SEGMENT` | `whereInSegment($field, $segment)` | segment name |
| `NOT_IN_SEGMENT` | `whereNotInSegment($field, $segment)` | segment name |

Requires a configured `segment_driver` (e.g., `SpatiePermissionSegmentDriver`).

---

## Boolean & Null Operators

| Operator | Description | Needs Expected Value? |
|----------|-------------|----------------------|
| `IS_TRUE` | Field is truthy | No |
| `IS_FALSE` | Field is falsy | No |
| `IS_NULL` | Field is null | No |
| `IS_NOT_NULL` | Field has a value | No |

---

## Time & Schedule Operators

| Operator | Description | Expected Value |
|----------|-------------|----------------|
| `TIME_BETWEEN` | Time-of-day range | array ['09:00', '17:00'] |
| `DAY_OF_WEEK` | Day match | array ['mon', 'tue'] |
| `IS_BUSINESS_DAY` | Weekday and not a holiday | optional region string |
| `IS_HOLIDAY` | Holiday check | optional region string |
| `SCHEDULE_CRON` | Cron expression match | cron string e.g. `'0 9 * * MON-FRI'` |

`IS_BUSINESS_DAY` and `IS_HOLIDAY` delegate to the configured `holiday_resolver`.

---

## Rollout Configuration

### Gradual Rollout

```php
$rule->rollout(fn ($r) => $r->gradual(10, true));   // 10% get true
$rule->rollout(fn ($r) => $r->variant('enabled', 10, true)); // equivalent
```

### Multi-Variant A/B Test

```php
$rule->rollout(fn ($r) => $r
    ->variant('control', 50, 'blue')
    ->variant('experiment_a', 25, 'green')
    ->variant('experiment_b', 25, 'red')
);
```

### 50/50 Split

```php
$rule->rollout(fn ($r) => $r->fiftyFifty('old_value', 'new_value'));
```

### Rollout Identifier Resolution Order

1. Custom `identifier_resolver` (from config)
2. Authenticated user ID
3. Explicit `scope` parameter

If no identifier is found, the rollout cannot be calculated and evaluation falls through to the next rule.

### Bucket Calculator

Default: CRC32 consistent hashing with 100,000 bucket precision. Configurable via `rollout.bucket_calculator` and `rollout.bucket_precision` in config.

---

## Priority & Conflict Resolution

- Rules evaluated in **ascending** priority order (lower number = higher priority)
- First matching rule wins
- Equal priority → earliest-created rule wins
- If no rules match → setting's default value

---

## Attribute Presence Rules

- **Missing attribute**: Condition fails (does not match)
- **Present but `null`**: Condition is evaluated with `null` — allows `IS_NULL` to match only explicitly null values
- Use dot notation for nested properties: `subscription.plan`
- Use `id` for the authenticated user's primary key
