# Class-Based Settings

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

If the PHP type is not registered as a handler, set `cast` in the attribute to pick the correct type.

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

## Next Steps

- [Targeting Rules](targeting-rules) - Learn how to add dynamic rules to your settings.
- [Custom Types](custom-types) - Use complex value objects in your settings classes.
