---
title: Settings Classes
description: Typed accessors for database-defined settings
---

# Settings Classes

Settings classes are typed accessors for settings that already exist in the database. Each property maps to a setting key via `#[SettingProperty]`, which gives you IDE autocomplete, casting, validation, and dirty tracking without redefining the setting.

## Define Settings in the Database

Create the setting definitions in migrations (or via the CLI) using the keys your class will map to.

```php
use GaiaTools\FulcrumSettings\Database\Migrations\SettingMigration;

return new class extends SettingMigration
{
    public function up(): void
    {
        $this->createSetting('general.site_name')
            ->string()
            ->default('My Awesome App')
            ->save();

        $this->createSetting('general.maintenance_mode')
            ->boolean()
            ->default(false)
            ->save();

        $this->createSetting('general.pagination_limit')
            ->integer()
            ->default(15)
            ->save();
    }
};
```

## Create a Settings Class

Map each property to a setting key with `#[SettingProperty]`. Use `protected` properties so Fulcrum can track dirty changes via magic accessors.

```php
namespace App\Settings;

use GaiaTools\FulcrumSettings\Attributes\SettingProperty;
use GaiaTools\FulcrumSettings\Support\Settings\FulcrumSettings;

class GeneralSettings extends FulcrumSettings
{
    #[SettingProperty(key: 'general.site_name')]
    protected string $siteName;

    #[SettingProperty(key: 'general.maintenance_mode')]
    protected bool $maintenanceMode;

    #[SettingProperty(key: 'general.pagination_limit', default: 15)]
    protected int $paginationLimit;
}
```

The `default` attribute value is a local fallback if the setting is missing or resolves to `null`. Your real defaults should still be defined in the database. If the PHP type is not registered as a handler, set `cast` in the attribute to pick the correct type.

## Using Settings Classes

Resolve your settings class from the Laravel service container.

```php
use App\Settings\GeneralSettings;

public function index(GeneralSettings $settings)
{
    return view('welcome', [
        'siteName' => $settings->siteName,
    ]);
}
```

## Saving Changes

Settings classes are dirty-aware. Update properties and call `save()` to persist the default value back to the database.

```php
$settings->maintenanceMode = true;
$settings->save();
```

## Lazy, Read-Only, Tenant-Scoped

Use attribute flags for lazy loading, read-only properties, and tenant-specific resolution.

```php
class BillingSettings extends FulcrumSettings
{
    #[SettingProperty(key: 'billing.api_key', readOnly: true, lazy: true)]
    protected ?string $apiKey = null;

    #[SettingProperty(key: 'billing.tax_rate', tenantScoped: true)]
    protected float $taxRate;
}

$settings = app(BillingSettings::class)->forTenant('tenant-123');
```

## Validation

Use the `rules` attribute to validate properties before saving.

```php
class GeneralSettings extends FulcrumSettings
{
    #[SettingProperty(key: 'general.site_name', rules: ['min:3', 'max:255'])]
    protected string $siteName;

    #[SettingProperty(key: 'general.pagination_limit', rules: ['integer', 'min:1', 'max:100'])]
    protected int $paginationLimit;
}
```

## Registration and Discovery

Fulcrum auto-discovers settings classes in `app/Settings` by default. To add classes manually or scan different paths, update `config/fulcrum.php`:

```php
'settings' => [
    'classes' => [
        App\Settings\GeneralSettings::class,
    ],
    'discovery' => [
        'enabled' => true,
        'paths' => [
            app_path('Settings'),
        ],
    ],
],
```

## Related Reading

- [Database Migrations](migrations)
- [Resolving Values](resolving)
- [Custom Type Handlers](../custom-types)
