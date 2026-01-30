---
title: Performance Guide
description: Practical guidance for fast setting resolution at scale
---

# Performance Guide

This guide focuses on how Fulcrum resolves settings so you can tune for speed and predictability.

## How Resolution Works

Each call to `Fulcrum::get()` or `Fulcrum::resolve()` loads a single setting with its rules, conditions, rollout variants, and default value. Rules are evaluated in priority order until the first match is found.

**Implications:**
- Resolution time grows with the number of rules per setting.
- Each setting is resolved independently; there is no built-in batch API.

## Enable Caching

Fulcrum can wrap the resolver with a cache layer via `config/fulcrum.php`:

```php
'cache' => [
    'enabled' => true,
    'store' => null,
    'ttl' => 3600,
    'prefix' => 'fulcrum',
],
```

### Cache Key Behavior

The cache key is derived from the setting key plus a scope identifier:
- If you call `Fulcrum::forUser($user)`, the user ID becomes the scope key.
- If you pass a scalar scope, the scope value is used directly.
- If you pass an object or array scope, Fulcrum uses `md5(serialize($scope))`.

**Recommendations:**
- Prefer scalar scopes or `forUser()` to keep cache keys stable.
- Avoid passing large arrays or rich objects as scope unless necessary.
- Use shorter TTLs when settings change frequently.
- Clear the cache store or rotate the prefix when values must update immediately.

## Rule Design

- Keep high-traffic rules early (lower priority number) so evaluation exits quickly.
- Use simple operators where possible; complex operators and large lists are slower.
- Split unrelated concerns into separate settings instead of stacking many rules on one setting.

## Rollouts and Bucketing

Rollout bucketing uses a stable hash (CRC32) with configurable bucket precision (default 100,000).

**Recommendations:**
- Always pass a stable identifier (user ID or a stable string) to keep rollout assignment consistent.
- Avoid random or session-only identifiers for long-lived experiments.

## Settings Classes and Lazy Properties

Settings classes resolve each property individually. Use lazy properties when a setting is rarely needed:

```php
#[SettingProperty(key: 'billing.api_key', lazy: true)]
protected ?string $apiKey = null;
```

This delays resolution until the property is first accessed.

## Multi-Tenancy Considerations

Tenant resolution happens on each request. Make sure your tenant resolver is fast and does not trigger heavy queries.

## Background Operations

Large imports/exports can run asynchronously using the queue flags in `fulcrum:import` and `fulcrum:export`. This keeps requests fast during heavy data operations.
