# Migrations API Reference

Complete reference for the `SettingMigration` builder API in Fulcrum Settings.

## Table of Contents

1. [Generating Migrations](#generating-migrations)
2. [Creating Settings](#creating-settings)
3. [Type Helpers](#type-helpers)
4. [Setting Options](#setting-options)
5. [Inline Rules](#inline-rules)
6. [Rollout Definitions](#rollout-definitions)
7. [Modifying Settings](#modifying-settings)
8. [Managing Rules](#managing-rules)
9. [Managing Conditions](#managing-conditions)
10. [Managing Variants](#managing-variants)
11. [Upserting Settings](#upserting-settings)
12. [Deleting Settings](#deleting-settings)

---

## Generating Migrations

```bash
php artisan make:setting-migration create_api_rate_limit_setting
php artisan make:setting-migration create_api_rate_limit_setting --path=custom/path
```

Generated migrations extend `GaiaTools\FulcrumSettings\Database\Migrations\SettingMigration`.

---

## Creating Settings

```php
use GaiaTools\FulcrumSettings\Database\Migrations\SettingMigration;

return new class extends SettingMigration
{
    public function up(): void
    {
        $this->createSetting('feature.new_dashboard')
            ->description('Enable the new dashboard UI')
            ->boolean()
            ->default(false)
            ->save();
    }

    public function down(): void
    {
        $this->deleteSetting('feature.new_dashboard');
    }
};
```

The `createSetting($key)` method returns a fluent `SettingBuilder`. Call `->save()` to persist.

---

## Type Helpers

| Method | Type | Storage |
|--------|------|---------|
| `string()` | string | VARCHAR |
| `integer()` | integer | INT |
| `float()` | float | FLOAT |
| `boolean()` | boolean | BOOLEAN |
| `array()` | array | JSON |
| `json()` | json | JSON |
| `type('custom_name')` | custom | Depends on handler |

---

## Setting Options

| Method | Purpose |
|--------|---------|
| `description($text)` | Human-readable description |
| `default($value)` | Default value when no rules match |
| `masked()` | Store encrypted, return placeholder unless revealed |
| `immutable()` | Prevent modification unless forced |
| `forTenant($tenantId)` | Create tenant-specific value |

### Full example with all options

```php
$this->createSetting('billing.api_key')
    ->description('Stripe API key for billing')
    ->string()
    ->default('')
    ->masked()
    ->immutable()
    ->forTenant('tenant-123')
    ->save();
```

---

## Inline Rules

Attach rules directly when creating a setting:

```php
$this->createSetting('discount_percent')
    ->integer()
    ->default(5)
    ->rule(fn ($rule) => $rule
        ->name('Premium Customers')
        ->priority(10)
        ->whereInSegment('segment', 'premium')
        ->then(20)
    )
    ->rule(fn ($rule) => $rule
        ->name('Holiday Sale')
        ->between('2025-12-01', '2025-12-31')
        ->then(15)
        ->priority(20)
    )
    ->save();
```

### Rule Builder Methods (RuleDefinition)

**Metadata:**
- `name($name)` — Rule name (used for identification in modify/delete)
- `priority($int)` — Evaluation order (lower = first)

**Condition helpers** (see `references/targeting-rules.md` for full list):
- `whereEquals($field, $value)`
- `whereNotEquals($field, $value)`
- `whereInSegment($field, $segment)`
- `whereNotInSegment($field, $segment)`
- `whereContainsAny($field, array $values)`
- `whereNotContainsAny($field, array $values)`
- `whereStartsWithAny($field, array $values)`
- `whereEndsWithAny($field, array $values)`
- `whereNumberGreaterThan($field, $value)`
- `whereNumberGreaterThanOrEqual($field, $value)`
- `whereNumberLessThan($field, $value)`
- `whereNumberLessThanOrEqual($field, $value)`
- `whereNumberBetween($field, [$min, $max])`
- `whereDateAfter($field, $value)`
- `whereDateBefore($field, $value)`
- `whereVersionGreaterThanOrEqual($field, $value)`
- `when($field, $operator, $value)` — Manual operator
- `whenType($type, $field, $operator, $value)` — Explicit condition type

**Time shortcuts:**
- `between($start, $end)` — Date range shorthand

**Value:**
- `then($value)` — The value to return when conditions match

**Rollout:**
- `rollout(fn ($r) => ...)` — Percentage-based delivery (see below)

---

## Rollout Definitions

```php
// Gradual rollout
$rule->rollout(fn ($r) => $r->gradual(10, true)); // 10% get `true`

// Single named variant
$rule->rollout(fn ($r) => $r->variant('enabled', 10, true));

// Multi-variant A/B test
$rule->rollout(fn ($r) => $r
    ->variant('control', 50, 'blue')
    ->variant('experiment_a', 25, 'green')
    ->variant('experiment_b', 25, 'red')
);

// 50/50 split
$rule->rollout(fn ($r) => $r->fiftyFifty('old_value', 'new_value'));
```

Variant weights are percentages and should sum to ≤ 100. Users not in any variant bucket fall through to the next rule.

---

## Modifying Settings

Update an existing setting in a later migration:

```php
$this->modifySetting('feature.new_dashboard')
    ->updateDescription('Updated description')
    ->updateDefault(true)
    ->apply();
```

Available modification methods:
- `updateDescription($text)`
- `updateDefault($value)`
- `apply()` — Persist changes

---

## Managing Rules

### Add a rule to an existing setting

```php
$this->addRule('feature.new_dashboard', function ($rule) {
    $rule->name('Internal Testers')
        ->whereEquals('is_internal', true)
        ->then(true);
});
```

### Modify an existing rule

```php
$this->modifyRule('setting_key', 'Rule Name', function ($rule) {
    $rule->priority(5);
});
```

### Delete a rule

```php
$this->deleteRule('setting_key', 'Rule Name');
```

### Clear all rules

```php
$this->clearRules('setting_key');
```

---

## Managing Conditions

### Add a condition to an existing rule

```php
$this->addCondition('discount_percent', 'Premium Customers', 'min_spend', '>=', 100);
```

Parameters: `($settingKey, $ruleName, $field, $operator, $value)`

### Delete a condition

```php
$this->deleteCondition('discount_percent', 'Premium Customers', 'country');
```

Parameters: `($settingKey, $ruleName, $field)`

---

## Managing Variants

### Update a variant's weight

```php
$this->updateVariantWeight('checkout_button_color', 'Button Color Test', 'experiment_a', 50);
```

### Delete a variant

```php
$this->deleteVariant('checkout_button_color', 'Button Color Test', 'experiment_b');
```

---

## Upserting Settings

Create-or-update without failing if the setting already exists:

```php
$this->upsert('emergency_banner', function ($setting) {
    $setting->string()
        ->default('')
        ->description('Site-wide emergency message');
});
```

---

## Deleting Settings

```php
$this->deleteSetting('feature.new_dashboard');
```
