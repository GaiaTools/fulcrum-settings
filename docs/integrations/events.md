# Events & Observability

Laravel Fulcrum fires several events throughout its lifecycle, allowing you to hook into setting resolution and management.

## Available Events

All events are located in the `GaiaTools\FulcrumSettings\Events` namespace.

### `SettingResolved`
Fired whenever a setting is successfully resolved.
- **Properties**: `key`, `value`, `context`, `isDefault`.

### `SettingsLoaded`
Fired when a batch of settings is loaded (e.g., when a Settings class is instantiated).
- **Properties**: `settings` (array of key/value pairs).

### `SettingsSaved`
Fired when settings are saved to the database.
- **Properties**: `settings` (array of key/value pairs).

### `LoadingSettings`
Fired before settings are loaded from the store.

### `SavingSettings`
Fired before settings are saved to the store.

### `VariantAssigned`
Fired when a user is assigned to a specific variant in an A/B test.
- **Properties**: `key`, `variant`, `user`.

## Listening for Events

You can register listeners in your `EventServiceProvider`:

```php
use GaiaTools\FulcrumSettings\Events\SettingResolved;
use App\Listeners\LogSettingResolution;

protected $listen = [
    SettingResolved::class => [
        LogSettingResolution::class,
    ],
];
```

## Use Cases

### 1. Audit Logging
Record every time a setting is changed.
```php
public function handle(SettingsSaved $event)
{
    foreach ($event->settings as $key => $value) {
        AuditLog::create([
            'user_id' => auth()->id(),
            'setting_key' => $key,
            'new_value' => json_encode($value),
        ]);
    }
}
```

### 2. Analytics Integration
Send variant assignments to your analytics platform (e.g., Mixpanel, Segment).
```php
public function handle(VariantAssigned $event)
{
    Mixpanel::track('Experiment Assigned', [
        'experiment_key' => $event->key,
        'variant' => $event->variant,
    ]);
}
```

### 3. Cache Invalidation
Trigger cache clearing in other parts of your application when settings change.

## Laravel Telescope Integration

Fulcrum includes a Telescope watcher that automatically records setting resolutions, making it easy to debug rule evaluation during development.

To enable it, ensure you have Telescope installed and configured. Fulcrum will automatically register its watcher if Telescope is detected.
