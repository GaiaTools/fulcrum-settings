---
title: Security
description: Masking, immutability, and access control for sensitive settings
---

# Security

Fulcrum includes masking and immutability controls for sensitive settings.

## Masked Settings

Masked settings are stored encrypted and return a placeholder by default.

```php
// Returns a MaskedValue placeholder
$apiKey = Fulcrum::get('stripe_api_key');

// Returns the decrypted value when authorized
$apiKey = Fulcrum::reveal()->get('stripe_api_key');
```

### Authorization

Unmasking is protected by a Laravel Gate ability. Configure it in `config/fulcrum.php`:

```php
'masking' => [
    'ability' => 'viewSettingValue',
],
```

Define the ability in your `AuthServiceProvider`:

```php
Gate::define('viewSettingValue', function ($user, Setting $setting) {
    return $user->hasRole('admin');
});
```

::: warning
In CLI or background contexts without an authenticated user, `Fulcrum::reveal()` returns real values automatically.
:::

## Immutability

Immutable settings cannot be modified by default. You can allow overrides via ENV or CLI flags:

```php
'immutability' => [
    'env_flag' => env('FULCRUM_FORCE', false),
    'cli_flag' => env('FULCRUM_FORCE_FLAG', 'force'),
],
```

Use the `--force` flag on CLI commands to override immutability when needed.
