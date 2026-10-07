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

## Context Isolation

Cache entries include the setting key, tenant identity, user identity (class and identifier), scope (including value types),
custom `FulcrumContext` attributes, request IP address, and user agent. These inputs
are hashed so their values do not appear in cache keys. The key format is versioned;
entries created with the older format are ignored and expire normally.

Authenticated users, explicit `forUser()` contexts, and user objects passed as scope
reuse the resolved value for the configured TTL. Explicit users take precedence
over user scope, which takes precedence over the authenticated user. User attributes,
permissions, and relationships are not reevaluated on cache hits; changes to those
values take effect after expiry or manual invalidation. Scope values and custom
`FulcrumContext` attributes remain part of the key, so changing them creates a new
cache entry.

Revealed values bypass caching so every read applies the current authorization
check and secrets are never written to the resolution cache. Users without scalar
identifiers, and scopes or attributes containing other objects or resources, also
bypass caching because those inputs lack a supported cache identity.

Custom condition handlers or resolvers that depend on additional external state
can return stale results during the TTL. Supply that state through scope or
`FulcrumContext` attributes when changes must select a new entry.

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
