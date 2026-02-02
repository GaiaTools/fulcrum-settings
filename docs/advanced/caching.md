---
title: Caching
description: Cache configuration, invalidation, and warming
---

# Caching

Caching improves performance by avoiding repeated database lookups and rule evaluation.

## Enable Caching

```php
'cache' => [
    'enabled' => env('FULCRUM_CACHE_ENABLED', false),
    'store' => env('FULCRUM_CACHE_STORE', null),
    'ttl' => env('FULCRUM_CACHE_TTL', 3600),
    'prefix' => env('FULCRUM_CACHE_PREFIX', 'fulcrum'),
],
```

## Store-Level Cache

Each store can enable cache overrides:

```php
'stores' => [
    'database' => [
        'cache' => [
            'enabled' => null,
            'prefix' => null,
            'ttl' => null,
        ],
    ],
],
```

## Invalidation

Fulcrum does not automatically invalidate scoped cache keys. When settings change, clear the cache store manually (or rotate the cache prefix) to avoid stale results. If you update values outside of Fulcrum, you must clear cache manually.

## Warming

For hot paths, resolve commonly used settings during application boot to prime cache entries.
