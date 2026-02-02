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

## Enable Fulcrum Integration

```php
// config/fulcrum.php
'pennant' => [
    'enabled' => true,
],
```

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

## Related Reading

- [Targeting Rules](../targeting-rules)
