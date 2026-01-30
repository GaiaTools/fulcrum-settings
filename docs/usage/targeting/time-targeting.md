---
title: Time-Based Targeting
description: Target settings by date, time, day of week, or schedule
---

# Time-Based Targeting

Fulcrum supports date/time operators for scheduling features and promotions.

## Common Operators

- `date_gt`, `date_between`
- `time_between`
- `day_of_week`
- `schedule_cron`
- `is_business_day`, `is_holiday`

See [Comparison Operators](../../reference/comparison-operators) for the full list.

## Example: Date Window

```php
$this->createSetting('promo.holiday_sale')
    ->boolean()
    ->default(false)
    ->rule(fn ($rule) => $rule
        ->name('Holiday window')
        ->whereDateBetween('now', '2025-12-01', '2025-12-26')
        ->then(true)
    )
    ->save();
```

## Example: Cron Schedule

```php
$this->createSetting('feature.office_hours')
    ->boolean()
    ->default(false)
    ->rule(fn ($rule) => $rule
        ->name('Weekday mornings')
        ->whereCron('0 9 * * MON-FRI')
        ->then(true)
    )
    ->save();
```

## Related Reading

- [Yasumi Holidays Integration](../../integrations/yasumi-holidays)
- [Custom Type Handlers](../../custom-types)
