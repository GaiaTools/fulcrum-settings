---
title: Configuration Reference
description: Complete reference for all Fulcrum configuration options
---

# Configuration Reference

This is a complete reference for `config/fulcrum.php`.

## Segment Driver

```php
'segment_driver' => null,
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `segment_driver` | `class-string|null` | `null` | Driver for resolving user segments. Set to `null` to disable segment targeting. |

**Available drivers:**

- `null` -- Disabled (default)
- `GaiaTools\FulcrumSettings\Drivers\SpatiePermissionSegmentDriver::class` -- Uses spatie/laravel-permission

## Geo Resolver

```php
'geo_resolver' => GaiaTools\FulcrumSettings\Drivers\DefaultGeoResolver::class,
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `geo_resolver` | `class-string` | `DefaultGeoResolver::class` | Resolves geographic location from IP address. |

## User Agent Resolver

```php
'user_agent_resolver' => GaiaTools\FulcrumSettings\Drivers\DefaultUserAgentResolver::class,
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `user_agent_resolver` | `class-string` | `DefaultUserAgentResolver::class` | Parses user agent data into device, browser, and OS fields. |

## Holiday Resolver

```php
'holiday_resolver' => null,
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `holiday_resolver` | `class-string|null` | `null` | Resolver for holiday checks. Set to a resolver class to enable holiday targeting. |

## Holiday Configuration

```php
'holidays' => [
    'default_region' => env('FULCRUM_HOLIDAY_REGION', null),
    'locale' => env('FULCRUM_HOLIDAY_LOCALE', 'en_US'),
    'providers' => [
        // 'US' => 'UnitedStates',
    ],
],
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `holidays.default_region` | `string|null` | `null` | Default region when a rule omits a region. |
| `holidays.locale` | `string` | `en_US` | Locale passed to the holiday resolver. |
| `holidays.providers` | `array<string, string>` | `[]` | Map of region codes to provider names. |

## Pennant Integration

```php
'pennant' => [
    'enabled' => env('FULCRUM_PENNANT_ENABLED', false),
],
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `pennant.enabled` | `bool` | `false` | Enables the Pennant driver integration. |

## Queue Configuration

```php
'queue' => [
    'connection' => env('FULCRUM_QUEUE_CONNECTION'),
    'queues' => [
        'imports' => env('FULCRUM_QUEUE_IMPORTS', 'fulcrum-imports'),
        'exports' => env('FULCRUM_QUEUE_EXPORTS', 'fulcrum-exports'),
        'cache' => env('FULCRUM_QUEUE_CACHE', 'fulcrum-cache'),
        'audit' => env('FULCRUM_QUEUE_AUDIT', 'fulcrum-audit'),
    ],
    'defaults' => [
        'tries' => env('FULCRUM_JOB_TRIES', 3),
        'timeout' => env('FULCRUM_JOB_TIMEOUT', 60),
        'backoff' => env('FULCRUM_JOB_BACKOFF', 60),
    ],
],
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `queue.connection` | `string|null` | `null` | Queue connection for Fulcrum jobs. |
| `queue.queues.imports` | `string` | `fulcrum-imports` | Queue name for imports. |
| `queue.queues.exports` | `string` | `fulcrum-exports` | Queue name for exports. |
| `queue.queues.cache` | `string` | `fulcrum-cache` | Queue name for cache operations. |
| `queue.queues.audit` | `string` | `fulcrum-audit` | Queue name for audit events. |
| `queue.defaults.tries` | `int` | `3` | Default number of job retries. |
| `queue.defaults.timeout` | `int` | `60` | Job timeout in seconds. |
| `queue.defaults.backoff` | `int` | `60` | Job backoff in seconds. |

## Stores

```php
'stores' => [
    'database' => [
        'driver' => 'database',
        'connection' => env('FULCRUM_DATABASE_CONNECTION', 'default'),
        'cache' => [
            'enabled' => null,
            'prefix' => null,
            'ttl' => null,
        ],
    ],
    'redis' => [
        'driver' => 'redis',
        'connection' => null,
        'cache' => [
            'enabled' => null,
            'prefix' => null,
            'ttl' => null,
        ],
    ],
],
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `stores.{store}.driver` | `string` | `database` | Store driver (`database` or `redis`). |
| `stores.{store}.connection` | `string|null` | `default` | Connection name for the store. |
| `stores.{store}.cache.enabled` | `bool|null` | `null` | Overrides global cache enabled setting. |
| `stores.{store}.cache.prefix` | `string|null` | `null` | Overrides global cache prefix. |
| `stores.{store}.cache.ttl` | `int|null` | `null` | Overrides global cache TTL. |

## Cache

```php
'cache' => [
    'enabled' => env('FULCRUM_CACHE_ENABLED', false),
    'store' => env('FULCRUM_CACHE_STORE', null),
    'ttl' => env('FULCRUM_CACHE_TTL', 3600),
    'prefix' => env('FULCRUM_CACHE_PREFIX', 'fulcrum'),
],
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `cache.enabled` | `bool` | `false` | Enable caching for setting resolution. |
| `cache.store` | `string|null` | `null` | Cache store to use. |
| `cache.ttl` | `int` | `3600` | Cache TTL in seconds. |
| `cache.prefix` | `string` | `fulcrum` | Cache key prefix. |

## Migration Paths

```php
'migrations' => [
    'paths' => [
        database_path('migrations'),
    ],
],
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `migrations.paths` | `array<int, string>` | `['database/migrations']` | Paths where Fulcrum migrations live. |

## Table Names

```php
'table_names' => [
    'settings' => 'settings',
    'setting_rules' => 'setting_rules',
    'setting_rule_conditions' => 'setting_rule_conditions',
    'setting_values' => 'setting_values',
    'setting_rule_rollout_variants' => 'setting_rule_rollout_variants',
],
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `table_names.settings` | `string` | `settings` | Settings table name. |
| `table_names.setting_rules` | `string` | `setting_rules` | Rules table name. |
| `table_names.setting_rule_conditions` | `string` | `setting_rule_conditions` | Conditions table name. |
| `table_names.setting_values` | `string` | `setting_values` | Values table name. |
| `table_names.setting_rule_rollout_variants` | `string` | `setting_rule_rollout_variants` | Rollout variants table name. |

## Settings Registration

```php
'settings' => [
    'classes' => [
        // \App\Settings\GeneralSettings::class,
    ],
    'discovery' => [
        'enabled' => true,
        'paths' => [
            app_path('Settings'),
        ],
    ],
],
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `settings.classes` | `array<int, class-string>` | `[]` | Explicit settings classes to register. |
| `settings.discovery.enabled` | `bool` | `true` | Auto-discover settings classes. |
| `settings.discovery.paths` | `array<int, string>` | `['app/Settings']` | Paths to scan for settings classes. |

## Publish Paths

```php
'publish' => [
    'enums_path' => app_path('Enums'),
],
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `publish.enums_path` | `string` | `app/Enums` | Output path for published enums. |

## Types

```php
'types' => [
    'boolean' => BooleanTypeHandler::class,
    'integer' => IntegerTypeHandler::class,
    'float' => FloatTypeHandler::class,
    'string' => StringTypeHandler::class,
    'json' => JsonTypeHandler::class,
    'array' => ArrayTypeHandler::class,
    'carbon' => CarbonTypeHandler::class,
    'bool' => BooleanTypeHandler::class,
    'int' => IntegerTypeHandler::class,
],
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `types` | `array<string, class-string>` | see above | Map of type names to handler classes. |

## Condition Types

```php
'condition_types' => [
    'user' => UserConditionTypeHandler::class,
    'geocoding' => GeocodingConditionTypeHandler::class,
    'user_agent' => UserAgentConditionTypeHandler::class,
    'date_time' => DateTimeConditionTypeHandler::class,
],
'condition_types_default' => 'user',
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `condition_types` | `array<string, class-string>` | see above | Map of condition type names to resolver classes. |
| `condition_types_default` | `string` | `user` | Default condition type when none is specified. |

## Carbon Configuration

```php
'carbon' => [
    'default_timezone' => env('FULCRUM_CARBON_TIMEZONE', config('app.timezone')),
    'store_utc' => env('FULCRUM_CARBON_STORE_UTC', true),
    'output_timezone' => env('FULCRUM_CARBON_OUTPUT_TIMEZONE', null),
],
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `carbon.default_timezone` | `string` | `config('app.timezone')` | Default timezone for Carbon values. |
| `carbon.store_utc` | `bool` | `true` | Store times in UTC. |
| `carbon.output_timezone` | `string|null` | `null` | Convert times on retrieval. |

## Masking & Encryption

```php
'masking' => [
    'ability' => env('FULCRUM_MASKING_ABILITY', 'viewSettingValue'),
    'placeholder' => env('FULCRUM_MASKING_PLACEHOLDER', '********'),
    'mask_in_resolver' => env('FULCRUM_MASK_IN_RESOLVER', false),
    'require_two_factor' => env('FULCRUM_REQUIRE_TWO_FACTOR', false),
],
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `masking.ability` | `string` | `viewSettingValue` | Gate ability required to reveal masked values. |
| `masking.placeholder` | `string` | `********` | Placeholder returned when masked. |
| `masking.mask_in_resolver` | `bool` | `false` | Return masked placeholders from resolvers by default. |
| `masking.require_two_factor` | `bool` | `false` | Require two-factor auth before revealing values. |

## Immutability

```php
'immutability' => [
    'env_flag' => env('FULCRUM_FORCE', false),
    'cli_flag' => env('FULCRUM_FORCE_FLAG', 'force'),
    'delete_ability' => env('FULCRUM_DELETE_IMMUTABLE_ABILITY', 'deleteImmutableSetting'),
    'allow_delete_via_gate' => env('FULCRUM_ALLOW_DELETE_IMMUTABLE_VIA_GATE', true),
],
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `immutability.env_flag` | `bool` | `false` | Allow overrides via ENV. |
| `immutability.cli_flag` | `string` | `force` | CLI flag name for forcing changes. |
| `immutability.delete_ability` | `string` | `deleteImmutableSetting` | Gate ability for deleting immutable settings. |
| `immutability.allow_delete_via_gate` | `bool` | `true` | Allow deletion when gate passes. |

## Multi-Tenancy

```php
'multi_tenancy' => [
    'enabled' => env('FULCRUM_MULTI_TENANCY', false),
    'tenant_resolver' => null,
],
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `multi_tenancy.enabled` | `bool` | `false` | Enable tenant-scoped settings. |
| `multi_tenancy.tenant_resolver` | `class-string|callable|null` | `null` | Resolve the current tenant ID. |

## Telescope

```php
'telescope' => [
    'enabled' => env('FULCRUM_TELESCOPE_ENABLED', true),
],
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `telescope.enabled` | `bool` | `true` | Enable Telescope watcher registration. |

## Rollout Configuration

```php
'rollout' => [
    'identifier_resolver' => null,
    'bucket_calculator' => Crc32BucketCalculator::class,
    'distribution_strategy' => WeightDistributionStrategy::class,
    'bucket_precision' => 100_000,
    'fire_assignment_events' => env('FULCRUM_FIRE_ASSIGNMENT_EVENTS', true),
],
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `rollout.identifier_resolver` | `callable|class-string|null` | `null` | Resolve rollout identifiers. |
| `rollout.bucket_calculator` | `class-string` | `Crc32BucketCalculator::class` | Hash algorithm for bucket assignments. |
| `rollout.distribution_strategy` | `class-string` | `WeightDistributionStrategy::class` | Strategy for variant distribution. |
| `rollout.bucket_precision` | `int` | `100000` | Bucket granularity. |
| `rollout.fire_assignment_events` | `bool` | `true` | Fire `VariantAssigned` events. |

## Data Portability

```php
'portability' => [
    'routes' => [
        'enabled' => env('FULCRUM_PORTABILITY_ROUTES', false),
        'prefix' => env('FULCRUM_PORTABILITY_PREFIX', 'fulcrum/portability'),
        'middleware' => ['api', 'auth'],
    ],
    'export_ability' => env('FULCRUM_EXPORT_ABILITY', 'exportFulcrumSettings'),
    'import_ability' => env('FULCRUM_IMPORT_ABILITY', 'importFulcrumSettings'),
],
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `portability.routes.enabled` | `bool` | `false` | Enable HTTP import/export routes. |
| `portability.routes.prefix` | `string` | `fulcrum/portability` | URL prefix for portability routes. |
| `portability.routes.middleware` | `array<int, string>` | `['api', 'auth']` | Middleware for portability routes. |
| `portability.export_ability` | `string` | `exportFulcrumSettings` | Gate ability for exports. |
| `portability.import_ability` | `string` | `importFulcrumSettings` | Gate ability for imports. |
