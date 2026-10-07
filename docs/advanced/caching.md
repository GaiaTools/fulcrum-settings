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
and custom `FulcrumContext` attributes. IP address is included only for settings with
geocoding conditions; user agent is included only for settings with user-agent
conditions. Ordinary guest settings therefore share results across visitors, and
ordinary authenticated settings retain cache hits when users change networks.
Request-dependent settings have more entries: geo targeting varies by IP and device
targeting varies by user agent. Their memory usage grows with distinct relevant
request inputs during the TTL.

A small dependency record per setting and tenant is cached for the configured TTL.
Discovering it requires a database lookup on the first read; subsequent reads reuse
it without database queries. Changed dependency inputs produce a new revision, so results from an older
dependency map cannot be reused. Refreshing unchanged metadata preserves existing
result entries and their TTL. These inputs are hashed so
values do not appear in cache keys. `CACHE_KEY_VERSION` names the schema version;
older entries are ignored and expire normally.

Authenticated users, explicit `forUser()` contexts, and user objects passed as scope
reuse the resolved value for the configured TTL. Explicit users take precedence
over user scope, which takes precedence over the authenticated user. User attributes,
permissions, and relationships are not reevaluated on cache hits; changes to those
values take effect after expiry or manual invalidation. Scope values and custom
`FulcrumContext` attributes remain part of the key, so changing them creates a new
cache entry.

Revealed values bypass caching so every read applies the current authorization
check and secrets are never written to the resolution cache. Users without scalar
identifiers, and scopes or attributes containing unsupported objects or resources,
also bypass caching. Backed enums are normalized by enum class and backing value;
dates by class, timezone, and timestamp including microseconds. Arbitrary objects
and Stringables remain unsupported because their string representation may omit
attributes used by targeting rules. Named top-level context attributes are sorted
so insertion order does not create duplicate cache entries; scope array ordering is
preserved.

A debug log (`Fulcrum resolution cache bypassed.`) identifies bypassed setting keys
without logging scope, user, or secret values. Custom setting resolvers can implement
`Contracts\CacheContextProvider` to supply current tenant identity and request
dependencies; resolvers without this optional contract bypass caching safely.

Custom condition types conservatively vary by both IP and user agent. Declare their
request dependencies explicitly to preserve reuse:

```php
'cache' => [
    // Other cache options...
    'request_dependencies' => [
        'custom_geo' => ['ip'],
        'custom_device' => ['user_agent'],
        'custom_scope_only' => [],
    ],
],
```

Overrides also apply to built-in types when their handlers are replaced. Custom
handlers that depend on other request or external state should supply those inputs
through scope or `FulcrumContext` attributes. Otherwise their changes can remain
stale during the TTL.

Built-in `date_time` conditions and rule `starts_at`/`ends_at` bounds are evaluated
on cache misses only. Opening or closing a time window does not expire cached
results early: the result can remain stale for the full TTL. Choose a TTL suited
to the required scheduling precision.

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

Fulcrum does not automatically invalidate scoped cache keys. When settings change, clear the cache store manually (or rotate the cache prefix), including dependency metadata to avoid stale results. If you update values outside of Fulcrum, you must clear cache manually.

## Warming

For hot paths, resolve commonly used settings during application boot to prime cache entries.
