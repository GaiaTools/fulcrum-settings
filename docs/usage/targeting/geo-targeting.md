---
title: Geographic Targeting
description: Target settings by country, region, or IP-derived location
---

# Geographic Targeting

Geo targeting relies on a `GeoResolver` to provide location data from an IP address.

## Configure a Resolver

```php
// config/fulcrum.php
'geo_resolver' => App\Drivers\MaxMindGeoResolver::class,
```

## Rule Example

```php
$this->createSetting('feature.geo_banner')
    ->boolean()
    ->default(false)
    ->rule(fn ($rule) => $rule
        ->name('US visitors')
        ->whenType('geocoding', 'country', 'equals', 'US')
        ->then(true)
    )
    ->save();
```

## Resolver Contract

Custom resolvers must implement `GaiaTools\FulcrumSettings\Contracts\GeoResolver` and return location data with keys like `country`, `region`, and `city`.
