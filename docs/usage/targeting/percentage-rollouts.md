---
title: Percentage Rollouts
description: Gradually release features with consistent user bucketing
---

# Percentage Rollouts

Use rollouts to enable a feature for a percentage of users while keeping assignments stable.

## Gradual Rollout

```php
$this->createSetting('feature.new_ui')
    ->boolean()
    ->default(false)
    ->rule(fn ($rule) => $rule
        ->name('10% rollout')
        ->rollout(fn ($rollout) => $rollout->variant('enabled', 10, true))
    )
    ->save();
```

## A/B Testing

```php
$this->createSetting('checkout.button_color')
    ->string()
    ->default('blue')
    ->rule(fn ($rule) => $rule
        ->name('Button color test')
        ->rollout(fn ($rollout) => $rollout
            ->variant('control', 50, 'blue')
            ->variant('experiment', 50, 'green')
        )
    )
    ->save();
```

## Identifier Resolution

Rollouts require an identifier to bucket users. Fulcrum resolves identifiers in this order:

1. Custom resolver configured in `config/fulcrum.php`
2. Authenticated user ID
3. Explicit scope passed to `Fulcrum::get()`

## Related Reading

- [Rollouts Concept](../../concepts/rollouts)
- [Configuration Reference](../../reference/configuration)
