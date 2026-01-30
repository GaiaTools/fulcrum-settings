---
title: Events
description: Fulcrum event lifecycle and payloads
---

# Events

Fulcrum fires events throughout its lifecycle so you can observe and extend behavior.

## Available Events

All events are in the `GaiaTools\FulcrumSettings\Events` namespace.

### `SettingResolved`
Fired when a setting is resolved.
- **Properties**: `key`, `value`, `matchedRule`, `rulesEvaluated`, `source`, `tenantId`, `userId`, `scope`, `durationMs`

### `SettingsLoaded`
Fired when settings are loaded.
- **Properties**: `settings`

### `SettingsSaved`
Fired when settings are saved to the database.
- **Properties**: `settings`

### `LoadingSettings`
Fired before settings are loaded from the store.

### `SavingSettings`
Fired before settings are saved to the store.

### `VariantAssigned`
Fired when a user is assigned to a rollout variant.
- **Properties**: `settingKey`, `ruleName`, `variantName`, `value`, `identifier`, `bucket`, `tenantId`

## Listening for Events

Register listeners in your `EventServiceProvider`:

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

### Audit Logging

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

### Analytics Integration

```php
public function handle(VariantAssigned $event)
{
    Mixpanel::track('Experiment Assigned', [
        'experiment_key' => $event->key,
        'variant' => $event->variant,
    ]);
}
```
