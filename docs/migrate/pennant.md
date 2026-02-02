# Migration from Laravel Pennant

If you are using `laravel/pennant` for feature flags, you can easily migrate to Laravel Fulcrum to take advantage of its advanced targeting and unified settings system.

## Why Migrate?

Laravel Pennant is great for simple flags, but Fulcrum provides:
- **Rich Targeting Rules**: More complex logic than simple boolean closures.
- **Unified API**: Manage both configuration and feature flags with the same tool.
- **Type Safety**: Use settings classes as typed accessors for better IDE support.
- **Database Management**: A robust database schema for managing complex rule sets.

## Step 1: Install the Pennant Driver

Fulcrum includes a Pennant driver that allows it to act as a drop-in replacement for Pennant's default stores.

In your `config/pennant.php`:

```php
'stores' => [
    'fulcrum' => [
        'driver' => 'fulcrum',
    ],
],
```

## Step 2: Update Feature Definitions

Instead of defining features in a Service Provider using `Feature::define()`, you define them as Fulcrum settings.

**Before (Pennant):**
```php
Feature::define('new-api', fn (User $user) => $user->is_beta);
```

**After (Fulcrum):**
Create a migration for the feature:

```php
use GaiaTools\FulcrumSettings\Database\Migrations\SettingMigration;

return new class extends SettingMigration
{
    public function up(): void
    {
        $this->createSetting('new-api')
            ->boolean()
            ->default(false)
            ->rule(fn ($rule) => $rule
                ->name('Beta users')
                ->whereTrue('is_beta')
                ->then(true)
            )
            ->save();
    }
};
```

## Step 3: Update Usage

Fulcrum's facade is compatible with most of Pennant's core functionality.

**Before:**
```php
use Laravel\Pennant\Feature;

if (Feature::active('new-api')) { ... }
```

**After:**
```php
use GaiaTools\FulcrumSettings\Facades\Fulcrum;

if (Fulcrum::isActive('new-api')) { ... }
```

## Context Resolution Differences

Pennant passes the scope into the driver, and Fulcrum converts that scope into rule evaluation context:

- **Authenticatable scope**: If the scope is a user model, Fulcrum treats it as the user for segment rules and adds `id` and `email` (when available) to the context.
- **Array scope**: If the scope is an array, Fulcrum uses it directly as the rule evaluation context.
- **Other objects**: If the scope is an object (e.g., Eloquent model), Fulcrum merges its attributes into the context.
- **Scalar scope**: If the scope is a string/int, Fulcrum wraps it as `['scope' => $scope]`.

This means rules should match on attributes that are actually present in the built context.

## Step 4: Leverage Fulcrum Features

Once migrated, you can start using Fulcrum-specific features like:
- **Percentage Rollouts**: `->rollout(10)`
- **Variant Assignment**: `->variants(['a' => 50, 'b' => 50])`
- **Time-Based Flags**: Enable a feature only during a specific period.

## Next Steps

- [Targeting Rules](../targeting-rules) - Explore the powerful rules engine.
- [Usage Guide](../usage) - Learn more about the Fulcrum API.
