---
title: Segment Targeting
description: Target settings using user segments, roles, or groups
---

# Segment Targeting

Segments are logical groupings like roles, plans, or cohorts. Fulcrum delegates segment resolution to a segment driver.

## Enable a Segment Driver

```php
// config/fulcrum.php
'segment_driver' => GaiaTools\FulcrumSettings\Drivers\SpatiePermissionSegmentDriver::class,
```

## Rule Example

```php
$this->createSetting('feature.beta_dashboard')
    ->boolean()
    ->default(false)
    ->rule(fn ($rule) => $rule
        ->name('Beta segment')
        ->whereInSegment('segment', 'beta')
        ->then(true)
    )
    ->save();
```

## Custom Drivers

Provide your own driver by implementing `GaiaTools\FulcrumSettings\Contracts\SegmentDriver`.

```php
use GaiaTools\FulcrumSettings\Contracts\SegmentDriver;
use Illuminate\Contracts\Auth\Authenticatable;

class CustomSegmentDriver implements SegmentDriver
{
    public function getUserSegments(Authenticatable $user): array
    {
        return $user->segments ?? [];
    }

    public function isInSegment(Authenticatable $user, string $segment): bool
    {
        return in_array($segment, $this->getUserSegments($user), true);
    }
}
```

## Related Reading

- [Spatie Permissions Integration](../../integrations/spatie-permissions)
- [Configuration Reference](../../reference/configuration)
