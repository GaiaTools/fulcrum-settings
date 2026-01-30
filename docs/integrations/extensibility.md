---
title: Extensibility
description: Replace Fulcrum drivers with custom implementations
---

# Extensibility (Drivers)

Fulcrum is designed to be extensible. You can replace core components with custom drivers.

## Available Extension Points

### `SegmentDriver`
Determines which segments a user belongs to.

**Interface**: `GaiaTools\FulcrumSettings\Contracts\SegmentDriver`

```php
interface SegmentDriver
{
    public function isInSegment(Authenticatable $user, string $segment): bool;
    public function getUserSegments(Authenticatable $user): array;
}
```

### `GeoResolver`
Resolves geographic location based on IP.

**Interface**: `GaiaTools\FulcrumSettings\Contracts\GeoResolver`

```php
interface GeoResolver
{
    public function resolve(Request|string|array|null $input = null): array;
}
```

### `TenantResolver`
Resolves the current tenant for multi-tenant apps.

**Interface**: `GaiaTools\FulcrumSettings\Contracts\TenantResolver`

### `UserAgentResolver`
Parses user agent strings for device, browser, and OS info.

**Interface**: `GaiaTools\FulcrumSettings\Contracts\UserAgentResolver`

## Registering Custom Drivers

```php
// config/fulcrum.php
'segment_driver' => App\Drivers\MyCustomSegmentDriver::class,
'geo_resolver' => App\Drivers\MaxMindGeoResolver::class,
'user_agent_resolver' => App\Drivers\CustomUserAgentResolver::class,
'holiday_resolver' => App\Drivers\CustomHolidayResolver::class,
```

Or bind in a service provider:

```php
use GaiaTools\FulcrumSettings\Contracts\SegmentDriver;
use App\Drivers\MyCustomSegmentDriver;

public function register()
{
    $this->app->bind(SegmentDriver::class, MyCustomSegmentDriver::class);
}
```
