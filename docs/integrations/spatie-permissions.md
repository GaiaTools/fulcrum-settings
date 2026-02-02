---
title: Spatie Permissions
description: Role and permission based targeting with spatie/laravel-permission
---

# Spatie Permissions

Use Spatie's permission package to power segment-based targeting in Fulcrum.

## Install

```bash
composer require spatie/laravel-permission
```

## Configure the Segment Driver

```php
// config/fulcrum.php
'segment_driver' => GaiaTools\FulcrumSettings\Drivers\SpatiePermissionSegmentDriver::class,
```

## Rule Example

```php
$this->createSetting('feature.admin_tools')
    ->boolean()
    ->default(false)
    ->rule(fn ($rule) => $rule
        ->name('Admins')
        ->whereInSegment('segment', 'admin')
        ->then(true)
    )
    ->save();
```

This will resolve `segment` against the user's roles and permissions as provided by the driver.
