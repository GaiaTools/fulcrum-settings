# Quick Start (5 Minutes)

Get started with your first feature flag in Laravel Fulcrum in less than 5 minutes.

## 1. Define Your First Feature Flag

First, [complete the installation](installation) if you haven't already.

## 2. Create the Setting Migration

Create a new migration to define a feature flag:

```bash
php artisan make:setting-migration create_new_dashboard_flag
```

In the generated migration file:

```php
use GaiaTools\FulcrumSettings\Database\Migrations\SettingMigration;
use GaiaTools\FulcrumSettings\Support\Builders\SettingBuilder;

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
};
```

Run the migration:

```bash
php artisan migrate
```

## 3. Use the Feature Flag in Your Code

You can now use the `Fulcrum` facade to check if the feature is active:

```php
use GaiaTools\FulcrumSettings\Facades\Fulcrum;

if (Fulcrum::isActive('new_dashboard')) {
    // Show the new dashboard
} else {
    // Show the old dashboard
}
```

## 4. Toggle the Flag via Artisan

You can easily toggle the flag from the command line:

```bash
# Set the value to true
php artisan fulcrum:set feature.new_dashboard true

# Set it back to false
php artisan fulcrum:set feature.new_dashboard false
```

## 5. Add a Targeting Rule

Let's enable the new dashboard only for users in the "Beta" segment.

```php
use GaiaTools\FulcrumSettings\Database\Migrations\SettingMigration;

return new class extends SettingMigration
{
    public function up(): void
    {
        $this->addRule('feature.new_dashboard', function ($rule) {
            $rule->name('Beta testers')
                ->whereInSegment('role', 'beta')
                ->then(true);
        });
    }
};
```

Now, `Fulcrum::isActive('feature.new_dashboard')` will return `true` if the current user belongs to the "beta" role, and `false` otherwise.

## Next Steps

- [Usage Guide](usage) - Learn more about the basic API.
- [Targeting Rules](targeting-rules) - Explore complex targeting logic.
- [Class-Based Settings](class-based-settings) - Use settings classes as typed accessors.
