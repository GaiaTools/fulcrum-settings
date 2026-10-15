---
title: Percentage Rollouts
description: Gradual feature rollouts with consistent user bucketing
---

# Percentage Rollouts

Percentage rollouts let you enable a setting for a fixed percentage of users while keeping assignments stable across requests.

## How Bucketing Works

Fulcrum hashes a rollout identifier (usually the authenticated user ID) into a bucket. That bucket determines which variant a user receives.

The identifier is resolved in this order:

1. Custom resolver configured in `config/fulcrum.php`
2. User selected with `forUser()`
3. Explicit scope: `getAuthIdentifier()` for an `Authenticatable`, a scalar, or an array/object `id`
4. Current authenticated user ID

If no identifier is available, the rollout cannot be evaluated and Fulcrum will fall back to the next rule or default value.

## Rollout Variants

Rollouts can be a single percentage (gradual enablement) or multiple variants (A/B tests).

```php
$rule->rollout(function ($rollout) {
    $rollout->variant('enabled', 10, true);
});
```

```php
$rule->rollout(function ($rollout) {
    $rollout->variant('control', 50, 'blue');
    $rollout->variant('experiment_a', 25, 'green');
    $rollout->variant('experiment_b', 25, 'red');
});
```

## Rollout Precision

You can control bucketing precision in the configuration:

```php
'rollout' => [
    'bucket_precision' => 100_000,
],
```

## Related Reading

- [Targeting: Percentage Rollouts](../usage/targeting/percentage-rollouts)
- [Configuration Reference](../reference/configuration)
