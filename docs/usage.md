# Usage Guide

Learn how to interact with Laravel Fulcrum in your application code.

## The Fulcrum Facade

The primary way to interact with Fulcrum is through the `GaiaTools\FulcrumSettings\Facades\Fulcrum` facade.

### Checking Feature Flags

Use `isActive` to check if a boolean feature flag is enabled for the current context.

```php
use GaiaTools\FulcrumSettings\Facades\Fulcrum;

if (Fulcrum::isActive('new_dashboard')) {
    // Show the new dashboard
}
```

### Getting Setting Values

Use `get` to retrieve a setting value. You can provide a default value if the setting doesn't exist.

```php
$discount = Fulcrum::get('registration_discount', default: 10);
```

If the setting has rollout rules, Fulcrum will automatically try to use the authenticated user's ID for bucketing. You can also pass a manual identifier (or "scope") as the third argument:

```php
// Use a specific string as the rollout identifier
$value = Fulcrum::get('my_feature', default: false, scope: 'user_123');

// Use an object that has an 'id' property
$value = Fulcrum::get('my_feature', default: false, scope: $company);
```

### Setting Values Programmatically

While most settings should be defined via migrations, you can update them at runtime:

```php
Fulcrum::set('maintenance_mode', true);
```

*Note: This updates the global value in the database.*

### Interactive Wizard (CLI)

For complex settings involving multiple rules, conditions, and rollout variants, you can use the interactive wizard. Simply run the `fulcrum:set` command without a value:

```bash
php artisan fulcrum:set my_setting
```

Or just:

```bash
php artisan fulcrum:set
```

The wizard will guide you through:
1. Basic setting configuration (type, description, etc.).
2. Default value assignment.
3. Creating targeting rules and adding conditions to them.
4. Setting up percentage rollouts and A/B test variants.

## Contextual Evaluation

Fulcrum shines when you need to resolve settings based on the current context (user, tenant, etc.).

### Automatic Context

By default, Fulcrum automatically uses the currently authenticated user for rule evaluation.
If no user is available, or if an attribute is missing from the resolved context, the condition does not match (explicit `null` values still count).

### Explicit Context

You can explicitly provide context when resolving a setting:

```php
$value = Fulcrum::forUser($user)->get('theme');

// Or with custom attributes
use GaiaTools\FulcrumSettings\Support\FulcrumContext;

FulcrumContext::set('browser', 'chrome');
$value = Fulcrum::isActive('modern_ui');
```

## Using Settings Classes

Settings classes are typed accessors that map properties to database-defined settings.

```php
use App\Settings\GeneralSettings;

$settings = app(GeneralSettings::class);

if ($settings->maintenanceMode) {
    // ...
}
```

See [Class-Based Settings](class-based-settings) for more details.

## Masked Settings & Reveal Logic

Fulcrum supports **Masked Settings** for sensitive information (e.g., API keys). When a setting is masked:
- Its value is stored **encrypted** in the database.
- It returns a `MaskedValue` object (placeholder `********`) by default when retrieved.
- It requires an explicit `reveal()` call and proper authorization to see the unmasked value.

### Retrieving Masked Values

By default, calling `Fulcrum::get()` on a masked setting returns a masked placeholder:

```php
// Returns GaiaTools\FulcrumSettings\Support\MaskedValue ('********')
$apiKey = Fulcrum::get('stripe_api_key');
```

To retrieve the actual value, you must use the `reveal()` method:

```php
// If authorized, returns the decrypted string.
// If NOT authorized, still returns the MaskedValue object.
$apiKey = Fulcrum::reveal()->get('stripe_api_key');
```

### Authorization

Unmasking is protected by a Laravel Gate. By default, Fulcrum checks for the `viewSettingValue` ability. You can define this in your `AuthServiceProvider`:

```php
use Illuminate\Support\Facades\Gate;
use GaiaTools\FulcrumSettings\Models\Setting;

Gate::define('viewSettingValue', function ($user, Setting $setting) {
    return $user->hasRole('admin');
});
```

You can change the ability name in `config/fulcrum.php`:

```php
'masking' => [
    'ability' => 'view_secrets', // Custom gate ability
],
```

### CLI, Tinker, and Cron Jobs

In administrative contexts like the CLI, Tinker, or Cron jobs, calling `reveal()` will automatically unmask the value without requiring a Gate check (unless a user is explicitly authenticated in that context).

```php
// In Tinker or a Cron job
$apiKey = Fulcrum::reveal()->get('stripe_api_key'); // returns the real value
```

## Artisan Commands

Fulcrum provides several artisan commands for managing settings from the console.

### Setting Values

Use `fulcrum:set` to create or update a setting.

```bash
# Basic usage
php artisan fulcrum:set key value

# Specify type and description
php artisan fulcrum:set discount 15 --type=integer --description="Global discount"

# Create a masked (encrypted) setting
php artisan fulcrum:set api_key secret123 --masked

# Create an immutable setting
php artisan fulcrum:set maintenance_mode true --type=boolean --immutable

# Set a tenant-specific setting
php artisan fulcrum:set theme dark --tenant=tenant-123

# Force update an immutable setting
php artisan fulcrum:set maintenance_mode false --force
```

### Retrieving Values

Use `fulcrum:get` to retrieve a setting value.

```bash
# Basic usage
php artisan fulcrum:get key

# Retrieve for a specific tenant
php artisan fulcrum:get theme --tenant=tenant-123

# Reveal a masked setting value
php artisan fulcrum:get api_key --reveal

# Resolve a value for a specific scope (rollout evaluation)
php artisan fulcrum:get feature_flag --scope=user_456
```

### Listing Settings

Use `fulcrum:list` to see all defined settings.

```bash
# List all settings
php artisan fulcrum:list

# Filter by tenant
php artisan fulcrum:list --tenant=tenant-123

# List only global settings (not scoped to any tenant)
php artisan fulcrum:list --no-tenants
```

### Migrating from Spatie

If you are migrating from `spatie/laravel-settings`, you can use the `fulcrum:migrate-spatie` command to import your existing settings.

```bash
# Migrate from default Spatie table
php artisan fulcrum:migrate-spatie

# Specify a custom table or connection
php artisan fulcrum:migrate-spatie --table=custom_settings --connection=mysql_old
```

See [Migrating from Spatie](migrate/spatie) for more details.

### Other Commands

- `make:setting-migration`: Create a new setting migration file.
- `fulcrum:export`: Export settings to a file.
- `fulcrum:import`: Import settings from a file.

For more details on import/export, see [Data Portability](data-portability).

## Next Steps

- [Class-Based Settings](class-based-settings) - Use settings classes as typed accessors.
- [Targeting Rules](targeting-rules) - Define dynamic rules for your settings.
- [Multi-Tenancy](multi-tenancy) - Scoping settings to tenants.
