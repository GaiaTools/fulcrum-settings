# Condition Evaluation Logic

This document provides a detailed explanation of how Fulcrum evaluates targeting rule conditions. Specifically, it covers how an **Attribute**, a **Comparison Operator**, and a **Value** work together to determine if a condition matches.

## Core Evaluation Loop

When Fulcrum evaluates a setting, it iterates through all defined rules. For each rule, it evaluates every condition. If **all** conditions in a rule pass, the rule matches and its value is returned.

The logic for a single condition follows these steps:
1. **Extract Attribute Value**: Fulcrum determines the "actual" value from the current context based on the attribute name.
2. **Apply Operator**: Fulcrum compares the "actual" value with the "expected" value (defined in the condition) using the specified operator.

---

## 1. Condition Type Resolution

Each condition has a **type** that determines how the field is resolved from the evaluation context. Types map to resolver classes (similar to setting types), and the registry is configurable so you can add custom condition types.

### Built-in Condition Types
Fulcrum ships with common condition types such as:

1. **user**: Resolves attributes from the evaluation scope (authenticated user or explicit scope).
2. **geocoding**: Resolves location data (e.g., country, city) via the configured Geo resolver.
3. **user_agent**: Resolves device/browser/OS attributes via the user-agent resolver.
4. **date_time**: Resolves time-based attributes using the configured timezone and clock.

Each resolver is responsible for extracting the "actual" value used by the operator.

### Attribute Presence
Fulcrum only evaluates a condition if the attribute is actually present in the resolved context.

- **Missing attribute**: The condition fails.
- **Present but null**: The condition is evaluated with a `null` value. This allows `is_null` to match only when the attribute is explicitly set to `null`.

---

## 2. Comparison Operators

Operators determine the type of logic applied. Fulcrum groups operators into several categories to ensure type-safe comparisons.

### String Operators (`equals`, `contains_any`, `starts_with_any`, etc.)
- Values are cast to strings before comparison.
- `equals` is a strict `===` check.
- `contains_any`, `starts_with_any`, and `ends_with_any` expect an array of strings in the condition's value.

### Numeric Operators (`number_gt`, `number_between`, etc.)
- Both the actual and expected values are cast to floats.
- `number_between` expects an array with two numbers: `[min, max]`.

### Date & Time Operators (`date_gt`, `time_between`, `schedule_cron`, etc.)
- The actual value is parsed into a `Carbon` instance.
- `schedule_cron` evaluates the current time against a standard Cron expression.
- `is_business_day` and `is_holiday` use the configured `Carbon` settings.
- `is_holiday` delegates to the configured holiday resolver (if set) and may use the condition's value as a region code.

### Version Operators (`version_gt`, `version_equals`, etc.)
- Uses PHP's `version_compare()` function, making it ideal for mobile app version targeting (e.g., `1.2.0` > `1.1.9`).

### Boolean & Null Operators (`is_true`, `is_null`, etc.)
- These check the truthiness or existence of the attribute. They usually do not require an "expected value" to be set in the condition.

---

## 3. Concrete Example: `user.id` `equals` `1`

How does Fulcrum handle `user.id` `equals` `1`?

1.  **Extraction**: 
    - The condition type is `user`, so the user resolver reads from the evaluation scope (authenticated user or explicit scope).
    - It uses `data_get($user, 'user.id')`. If your user object has an `id` property, but you used `user.id`, it might return `null`.
    - **Note**: Usually, you would just use `id` for the authenticated user. If you use `id`, the resolver can map it to the primary key of the `Authenticatable` scope.

2.  **Comparison**:
    - The operator is `equals`. This is a string-based operator in Fulcrum.
    - The "actual" value (the user's ID, e.g., `1`) is cast to a string: `"1"`.
    - The "expected" value (the value in the condition, `1`) is compared: `"1" === 1`. 
    - In `RuleEvaluator::evaluateStringComparison`, it performs `$actual === $expected`. 

### Best Practices for Attributes
- Use `id` to target the current user's ID.
- Use `country` with condition type `geocoding` for geographic targeting.
- Use dot notation (e.g., `subscription.plan`) to reach into nested properties of your User model.
