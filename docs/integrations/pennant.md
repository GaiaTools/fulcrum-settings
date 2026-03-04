---
title: Laravel Pennant
description: Use Fulcrum as a Pennant driver for feature flags
---

# Laravel Pennant

Fulcrum includes a Pennant driver, letting you use `Feature::active()` with Fulcrum's rule engine.

## Install Pennant

```bash
composer require laravel/pennant
```

## Configure the Store

```php
// config/pennant.php
'stores' => [
    'fulcrum' => [
        'driver' => 'fulcrum',
    ],
],
```

Set the store in `.env`:

```bash
PENNANT_STORE=fulcrum
```

## Register the Driver

Fulcrum ships with a Pennant driver class, but you must register it with Pennant.
Add this to your `AppServiceProvider::boot()` (or a dedicated service provider):

```php
use GaiaTools\FulcrumSettings\Drivers\PennantDriver;
use Laravel\Pennant\Feature;

public function boot(): void
{
    Feature::extend('fulcrum', function ($app) {
        return $app->make(PennantDriver::class);
    });
}
```

## Enable Fulcrum Integration

Fulcrum integration is disabled by default. You must enable it **and** register the driver (see Step 3) for the integration to work.

```php
// config/fulcrum.php
'pennant' => [
    'enabled' => true,
],
```

::: warning Important
Both of these steps are required:
1. Enable the integration in `config/fulcrum.php` (shown above)
2. Register the driver with Pennant in your service provider (Step 3 below)

The integration will not work without both steps.
:::

## Define Features in Fulcrum

Instead of defining features in code, create Fulcrum settings and rules:

```php
$this->createSetting('new-api')
    ->boolean()
    ->default(false)
    ->save();
```

## Usage

```php
use Laravel\Pennant\Feature;

if (Feature::active('new-api')) {
    // ...
}
```

## Limitations

Fulcrum manages features in the database, so Pennant write operations are not supported.
The following methods will throw exceptions:

- `Feature::set(...)`
- `Feature::setForAllScopes(...)`
- `Feature::delete(...)`
- `Feature::purge(...)`

## Related Reading

- [Targeting Rules](../targeting-rules)
