---
title: Yasumi Holidays
description: Holiday-based targeting using Yasumi
---

# Yasumi Holidays

Fulcrum can target holidays using the Yasumi library.

## Install

```bash
composer require azuyalabs/yasumi
```

## Configure the Resolver

```php
// config/fulcrum.php
'holiday_resolver' => \GaiaTools\FulcrumSettings\Drivers\YasumiHolidayResolver::class,
'holidays' => [
    'default_region' => env('FULCRUM_HOLIDAY_REGION', null),
    'locale' => env('FULCRUM_HOLIDAY_LOCALE', 'en_US'),
    'providers' => [
        // 'US' => 'UnitedStates',
    ],
],
```

## Rule Example

```php
use GaiaTools\FulcrumSettings\Enums\ComparisonOperator;

$this->createSetting('promo.holiday_banner')
    ->boolean()
    ->default(false)
    ->rule(fn ($rule) => $rule
        ->name('US holidays')
        ->when('now', ComparisonOperator::IS_HOLIDAY, 'US')
        ->then(true)
    )
    ->save();
```

::: tip
If you omit the region value, Fulcrum uses `holidays.default_region`.
:::
