---
title: Configuration
description: Essential configuration options for Fulcrum Settings
---

# Configuration

After publishing, your config lives at `config/fulcrum.php`. This page covers the essentials -- see the [full reference](../reference/configuration) for all options.

## Environment Variables

Add these to your `.env` for quick configuration:

```bash
# Caching (recommended for production)
FULCRUM_CACHE_ENABLED=true
FULCRUM_CACHE_TTL=3600

# Optional multi-tenancy
FULCRUM_MULTI_TENANCY=true

# Pennant integration
FULCRUM_PENNANT_ENABLED=false
```

## Database Connection

By default, Fulcrum uses your default database connection:

```php
'stores' => [
    'database' => [
        'driver' => 'database',
        'connection' => env('FULCRUM_DATABASE_CONNECTION', 'default'),
    ],
],
```

## Table Names

Customize table names before running migrations:

```php
'table_names' => [
    'settings' => 'settings',
    'setting_rules' => 'setting_rules',
    'setting_rule_conditions' => 'setting_rule_conditions',
    'setting_values' => 'setting_values',
    'setting_rule_rollout_variants' => 'setting_rule_rollout_variants',
],
```

::: warning
Table names cannot be changed after migration without manual database work.
:::

## Cache Defaults

Fulcrum can cache resolved settings to improve performance:

```php
'cache' => [
    'enabled' => env('FULCRUM_CACHE_ENABLED', false),
    'store' => env('FULCRUM_CACHE_STORE', null),
    'ttl' => env('FULCRUM_CACHE_TTL', 3600),
    'prefix' => env('FULCRUM_CACHE_PREFIX', 'fulcrum'),
],
```

[Full Configuration Reference ->](../reference/configuration)
