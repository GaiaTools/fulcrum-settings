---
title: Database Migrations
description: Define settings, rules, and rollouts using migrations
---

# Database Migrations

Fulcrum lets you manage settings, targeting rules, and rollouts using standard Laravel migrations. This is the recommended way to keep settings in sync across environments.

## Generating Migrations

Fulcrum provides a specialized command to generate setting migrations that extend `GaiaTools\FulcrumSettings\Database\Migrations\SettingMigration`.

```bash
php artisan make:setting-migration create_search_feature_flag
```

## Creating Settings

Use the `createSetting()` method to define a new setting. It provides a fluent interface to configure the setting's properties.

```php
use GaiaTools\FulcrumSettings\Database\Migrations\SettingMigration;

return new class extends SettingMigration
{
    public function up(): void
    {
        $this->createSetting('search_v2_enabled')
            ->description('Enable the new AI-powered search engine')
            ->boolean()
            ->default(false)
            ->save();
    }

    public function down(): void
    {
        $this->deleteSetting('search_v2_enabled');
    }
};
```

### Supported Types

You can specify the type of the setting using the following methods:

- `string()`
- `integer()`
- `float()`
- `boolean()`
- `array()`
- `json()`
- `type('custom_type_name')`

### Additional Options

- `masked()` -- Store the setting encrypted and return a masked placeholder unless explicitly revealed.
- `immutable()` -- Prevent modification via CLI or UI (unless forced).
- `forTenant($tenantId)` -- Create a tenant-specific value.

## Defining Targeting Rules

Rules allow you to override the default value based on the evaluation context.

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
    ->save();
```

### Conditions

The `RuleDefinition` provides helper methods for common operators:

- `whereEquals($attribute, $value)`
- `whereNotEquals($attribute, $value)`
- `whereInSegment($attribute, $segment)`
- `whereContainsAny($attribute, array $values)`
- `whereNumberGreaterThan($attribute, $value)`
- `whereDateBefore($attribute, $value)`
- `whereVersionGreaterThanOrEqual($attribute, $value)`
- `when($attribute, $operator, $value)` (manual operator selection)

### Multiple Conditions

By default, multiple conditions in a single rule are combined using **AND**.

```php
$rule->whereEquals('country', 'US')
     ->whereNumberGreaterThan('order_count', 5)
     ->then(15);
```

## Rollouts and A/B Tests

You can define percentage-based rollouts and A/B tests directly in your migrations.

### Gradual Rollout

Enable a feature for a percentage of users.

```php
$this->createSetting('new_ui_enabled')
    ->boolean()
    ->default(false)
    ->rule(fn ($rule) => $rule
        ->name('10% Beta Rollout')
        ->rollout(fn ($rollout) => $rollout->gradual(10, true))
    )
    ->save();
```

### A/B Testing (Variants)

Split users between different values.

```php
$this->createSetting('checkout_button_color')
    ->string()
    ->default('blue')
    ->rule(fn ($rule) => $rule
        ->name('Button Color Test')
        ->rollout(fn ($rollout) => $rollout
            ->variant('control', 50, 'blue')
            ->variant('experiment_a', 25, 'green')
            ->variant('experiment_b', 25, 'red')
        )
    )
    ->save();
```

You can also use the `fiftyFifty()` helper for simple A/B tests:

```php
$rollout->fiftyFifty('old_value', 'new_value');
```

## Scheduled Rules

Rules can be constrained by time, making them perfect for promotions or scheduled maintenance.

```php
$rule->name('Holiday Special')
    ->between('2025-12-01', '2025-12-26')
    ->then(25);
```

## Modifying Existing Settings

To update a setting in a later migration, use the `modifySetting()` method.

```php
public function up(): void
{
    $this->modifySetting('search_v2_enabled')
        ->updateDescription('Updated description')
        ->updateDefault(true)
        ->apply();
}
```

### Managing Rules on Existing Settings

`SettingMigration` provides methods to add, modify, or delete rules on existing settings.

```php
// Add a new rule
$this->addRule('search_v2_enabled', function ($rule) {
    $rule->name('Internal Testers')
        ->whereEquals('is_internal', true)
        ->then(true);
});

// Update an existing rule
$this->modifyRule('search_v2_enabled', 'Internal Testers', function ($rule) {
    $rule->priority(5);
});

// Delete a rule
$this->deleteRule('search_v2_enabled', 'Internal Testers');

// Clear all rules
$this->clearRules('search_v2_enabled');
```

### Managing Conditions

```php
$this->addCondition('discount_percent', 'Premium Customers', 'min_spend', '>=', 100);
$this->deleteCondition('discount_percent', 'Premium Customers', 'country');
```

### Managing Variants

```php
$this->updateVariantWeight('checkout_button_color', 'Button Color Test', 'experiment_a', 50);
$this->deleteVariant('checkout_button_color', 'Button Color Test', 'experiment_b');
```

## Upserting Settings

If you want to ensure a setting exists without failing if it was already created, use `upsert()`.

```php
$this->upsert('emergency_banner', function ($setting) {
    $setting->string()
        ->default('')
        ->description('Site-wide emergency message');
});
```

## Related Reading

- [Rules & Conditions](../condition-logic)
- [Targeting Overview](targeting/)
