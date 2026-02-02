---
title: Resolving Values
description: Resolve settings via the Fulcrum facade, DI, and scopes
---

# Resolving Values

Fulcrum resolves settings based on the current scope (usually the authenticated user) and any additional context you provide.

## The Fulcrum Facade

```php
use GaiaTools\FulcrumSettings\Facades\Fulcrum;

// Resolve with the current context
$discount = Fulcrum::get('registration_discount', default: 10);

// Boolean helper
if (Fulcrum::isActive('feature.new_dashboard')) {
    // ...
}
```

## Explicit Scopes

You can pass a scope to resolve values for a specific user or identifier.

```php
// Use a specific string as the rollout identifier
$value = Fulcrum::get('my_feature', default: false, scope: 'user_123');

// Use an object that has an 'id' property
$value = Fulcrum::get('my_feature', default: false, scope: $company);
```

## Context Overrides

Provide additional attributes for rule evaluation:

```php
use GaiaTools\FulcrumSettings\Support\FulcrumContext;

FulcrumContext::set('browser', 'chrome');
$value = Fulcrum::isActive('modern_ui');
```

## Settings Classes

Settings classes are resolved from the container and expose settings as typed properties mapped to database keys:

```php
use GaiaTools\FulcrumSettings\Attributes\SettingProperty;
use GaiaTools\FulcrumSettings\Support\Settings\FulcrumSettings;

class GeneralSettings extends FulcrumSettings
{
    #[SettingProperty(key: 'general.maintenance_mode')]
    protected bool $maintenanceMode;
}

$settings = app(GeneralSettings::class);
$settings->maintenanceMode;
```

## Masked Values

Masked settings are stored encrypted and return a placeholder unless explicitly revealed.

```php
// Returns a MaskedValue placeholder
$apiKey = Fulcrum::get('stripe_api_key');

// Returns the decrypted value when authorized
$apiKey = Fulcrum::reveal()->get('stripe_api_key');
```

::: warning
In web requests, revealing masked values requires the configured Gate ability.
:::

## Related Reading

- [Settings Classes](settings-classes)
- [Security](../advanced/security)
