# Migration from Spatie Laravel Settings

Transitioning from `spatie/laravel-settings` to Laravel Fulcrum is straightforward. This guide will walk you through the process.

## Why Migrate?

While Spatie's package is excellent for static settings, Fulcrum offers:
- **Dynamic Rules**: Change values based on user context.
- **Feature Flags**: Native support for flags and rollouts.
- **Multi-Tenancy**: Scoped settings for tenants.

## Step 1: Install Fulcrum

Follow the [Installation](../installation) guide to install Fulcrum in your project alongside your existing Spatie installation.

## Step 2: Update Settings Classes

Change the base class of your settings from `Spatie\LaravelSettings\Settings` to `GaiaTools\FulcrumSettings\Support\Settings\FulcrumSettings` and map each property to a database key with `#[SettingProperty]`.

**Before:**
```php
class GeneralSettings extends \Spatie\LaravelSettings\Settings
{
    public string $site_name;
}
```

**After:**
```php
use GaiaTools\FulcrumSettings\Attributes\SettingProperty;
use GaiaTools\FulcrumSettings\Support\Settings\FulcrumSettings;

class GeneralSettings extends FulcrumSettings
{
    #[SettingProperty(key: 'general.site_name')]
    protected string $siteName;
}
```

## Step 3: Use the Migration Command

Fulcrum includes a command to help migrate your existing data from Spatie's database tables.

```bash
# Migrate from the default 'settings' table
php artisan fulcrum:migrate-spatie

# Specify a custom table or connection
php artisan fulcrum:migrate-spatie --table=spatie_settings --connection=mysql_old

# Force overwrite if settings already exist in Fulcrum
php artisan fulcrum:migrate-spatie --force
```

This command will:
1. Scan your Spatie settings table.
2. Infer the correct types (boolean, integer, string, etc.).
3. Create corresponding entries in Fulcrum's `settings` and `setting_values` tables.
4. Preserves "locked" status as "immutable" in Fulcrum.

### Data Mapping Notes

- **Key format**: Fulcrum keys are created as `group.name` from Spatie's `group` and `name` columns.
- **Type inference**: Payload values are inferred to `boolean`, `integer`, `float`, `string`, `json`, or `carbon` (date-like strings that contain `-` and `:`).
- **Immutable mapping**: Spatie's `locked` flag becomes Fulcrum's `immutable` flag.
- **Table detection**: If `settings` is missing or lacks a `group` column and `spatie_settings` exists, the command defaults to `spatie_settings`.
- **Tenants**: The migration command does not infer tenant IDs; migrated values are global unless you add tenant-specific overrides later.

## Step 4: Update Usage

If you were using the `app()` helper to resolve settings, it will still work as Fulcrum also binds these classes to the container.

If you were using Spatie's facade or helpers, you should update them to use Fulcrum's equivalent.

**Before:**
```php
$name = app(GeneralSettings::class)->site_name;
```

**After:**
```php
// Still works!
$name = app(GeneralSettings::class)->site_name;
```

## Step 5: Clean Up

Once you've verified that your settings are working correctly with Fulcrum, you can remove the Spatie package and its migrations.

```bash
composer remove spatie/laravel-settings
```

## Search/Replace Patterns

Use these as starting points, then adjust based on your codebase.

### Base Class

- `Spatie\\LaravelSettings\\Settings` -> `GaiaTools\\FulcrumSettings\\Support\\Settings\\FulcrumSettings`

### Property Mapping

- Add `#[SettingProperty(key: 'group.setting_name')]` attributes to map each property to its Fulcrum key.
- Convert public properties to `protected` so Fulcrum can track dirty changes.

### Access Patterns

- If you read settings through settings classes, keep `app(YourSettings::class)` and access properties as before.
- If you read values directly, replace those reads with `Fulcrum::get('group.setting_name')`.

## Rollback Plan

If you need to roll back after testing:

1. Leave the Spatie tables intact (do not drop them until Fulcrum is verified).
2. Remove Fulcrum usage from runtime code (switch reads back to Spatie settings classes or helpers).
3. Delete the migrated Fulcrum settings:
   - Remove rows from `settings` and `setting_values` (and related rule tables if created).
4. Uninstall Fulcrum once confirmed: `composer remove gaiatools/fulcrum-settings`.

## Coexistence Strategy

You can run both packages simultaneously during the transition. Simply migrate your settings classes one by one. Fulcrum uses its own tables (`settings`, `setting_values`, etc.), and its migration renames Spatie's `settings` table to `spatie_settings` if it detects Spatie's schema, avoiding conflicts.

## Next Steps

- [Class-Based Settings](../class-based-settings) - Learn more about Fulcrum's settings classes.
- [Targeting Rules](../targeting-rules) - Start adding dynamic rules to your migrated settings.
