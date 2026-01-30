# Targeting Rules

Targeting rules are the core of Fulcrum's dynamic evaluation. They allow you to change a setting's value based on the current evaluation context.

## Rule Structure

A rule consists of:
1. **Conditions**: A set of criteria that must be met (e.g., `user.segment == 'beta'`).
2. **Value**: The value to return if the conditions match.
3. **Priority**: Rules are evaluated in order; the first matching rule wins.

Each condition includes:
- **Type** -- determines how the field is resolved (for example `user`, `geocoding`, `user_agent`, `date_time`)
- **Field** -- the attribute to resolve within that type (for example `id`, `country`, `os`)
- **Operator** -- comparison type (for example `equals`, `number_gt`, `date_between`)
- **Value** -- the expected value for comparisons that require one

### Condition Types
Built-in condition types include:
- `user` -- resolves attributes from the authenticated user or explicit scope
- `geocoding` -- resolves location data via the configured geo resolver
- `user_agent` -- resolves device/browser/OS data via the user-agent resolver
- `date_time` -- resolves time-based attributes using the configured timezone and clock

### Attribute Presence
- **Missing attribute**: The condition fails.
- **Present but null**: The condition is evaluated with a `null` value. This allows `is_null` to match only when the attribute is explicitly set to `null`.

## Defining Rules via Migrations

Define rules in migrations using the `SettingMigration` helpers.

```php
use GaiaTools\FulcrumSettings\Database\Migrations\SettingMigration;

return new class extends SettingMigration
{
    public function up(): void
    {
        $this->addRule('discount_percent', function ($rule) {
            $rule->name('Premium users in US')
                ->whereInSegment('segment', 'premium')
                ->whenType('geocoding', 'country', 'equals', 'US')
                ->then(20)
                ->priority(10);
        });
    }
};
```

## Supported Operators

Fulcrum supports a variety of operators for matching:

- `equals`, `not_equals`
- `contains_any`, `not_contains_any`
- `starts_with_any`, `ends_with_any`
- `matches_regex`
- `number_gt`, `number_gte`, `number_lt`, `number_lte`, `number_between`
- `date_gt`, `date_gte`, `date_lt`, `date_lte`, `date_between`
- `in_segment`, `not_in_segment`

```php
use GaiaTools\FulcrumSettings\Database\Migrations\SettingMigration;

return new class extends SettingMigration
{
    public function up(): void
    {
        $this->addRule('content.rating', function ($rule) {
            $rule->name('Adult users')
                ->whereNumberGreaterThanOrEqual('age', 18)
                ->then('Adult Content Enabled');
        });
    }
};
```

## Percentage Rollouts

You can define rules that apply to only a percentage of your users.

```php
use GaiaTools\FulcrumSettings\Database\Migrations\SettingMigration;

return new class extends SettingMigration
{
    public function up(): void
    {
        $this->addRule('feature.new_feature', function ($rule) {
            $rule->name('10% rollout')
                ->rollout(fn ($rollout) => $rollout->variant('enabled', 10, true));
        });
    }
};
```

Fulcrum uses a consistent hashing algorithm based on the "rollout identifier" to ensure the same user always gets the same result. By default, the identifier is the authenticated user's ID.

### Rollout Identifiers

For a rollout to work, Fulcrum needs an identifier to place the user in a "bucket". It looks for this identifier in the following order:

1. **Custom Resolver**: A custom identifier resolver defined in configuration.
2. **Authenticated User**: The ID of the currently logged-in user.
3. **Explicit Scope**: An ID or object passed directly to the `get()` or `resolve()` method.

If no identifier can be found (e.g., a guest user in a console command), the rollout cannot be calculated.

In this example, if a user is logged in, they will be bucketed into either `new-feature` or `old-feature`. If no user is logged in, the rollout cannot be calculated, and Fulcrum will continue to the next rule (or fall back to the setting's default value).

## A/B Testing with Variants

For more complex scenarios, you can define multiple variants for a rollout.

```php
use GaiaTools\FulcrumSettings\Database\Migrations\SettingMigration;

return new class extends SettingMigration
{
    public function up(): void
    {
        $this->addRule('checkout.button_color', function ($rule) {
            $rule->name('Button color test')
                ->rollout(fn ($rollout) => $rollout
                    ->variant('control', 50, 'blue')
                    ->variant('experiment_a', 25, 'green')
                    ->variant('experiment_b', 25, 'red')
                );
        });
    }
};
```

## Multi-Condition Rules

By default, all conditions in a rule definition are treated as an "AND" operation.

```php
// User must be in 'beta' AND from 'UK'
use GaiaTools\FulcrumSettings\Database\Migrations\SettingMigration;

return new class extends SettingMigration
{
    public function up(): void
    {
        $this->addRule('feature.beta_access', function ($rule) {
            $rule->name('Beta users in UK')
                ->whereInSegment('segment', 'beta')
                ->whenType('geocoding', 'country', 'equals', 'UK')
                ->then(true);
        });
    }
};
```

## Next Steps

- [Usage Guide](usage) - Learn how to evaluate these rules in your code.
- [Custom Types](custom-types) - Use rules with complex data types.
- [Examples: Advanced Targeting](examples/advanced-targeting) - See real-world targeting examples.

## Technical Details

### Priority and Conflict Resolution
Rules are evaluated in ascending priority order. When two rules match, the lower priority number wins. If priorities are equal, the earliest-created rule wins.

### Operator Details
Operators are grouped by comparison type:
- **String**: `equals`, `contains_any`, `starts_with_any`, `matches_regex`
- **Numeric**: `number_gt`, `number_between`, etc. (cast to floats)
- **Date/Time**: `date_gt`, `schedule_cron`, `is_business_day` (uses Carbon)
- **Version**: `version_gt`, `version_equals` (uses `version_compare()`)
- **Boolean/Null**: `is_true`, `is_null` (no expected value needed)
