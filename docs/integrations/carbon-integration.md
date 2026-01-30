---
title: Carbon and DateTime
description: Use Carbon and DateTime values in settings and targeting
---

# Carbon/DateTime Integration

Fulcrum supports `Carbon` and PHP `DateTime` instances via the `carbon` type handler.

## The `carbon` Type

```php
$this->createSetting('sale_ends_at')
    ->type('carbon')
    ->default(now()->addDays(30))
    ->save();
```

## Time-Based Targeting

Enable a feature during a specific window:

```php
$this->createSetting('promo.black_friday')
    ->boolean()
    ->default(false)
    ->rule(fn ($rule) => $rule
        ->name('Black Friday window')
        ->whereDateBetween('now', '2025-11-28', '2025-12-01')
        ->then(true)
    )
    ->save();
```

## Override Time in Tests

```php
use Illuminate\Support\Carbon;

Carbon::setTestNow('2025-11-28 12:00:00');
$value = Fulcrum::get('discount_percent');
Carbon::setTestNow(); // reset
```

## Related Reading

- [Time-Based Targeting](../usage/targeting/time-targeting)
