# Configuration Reference

Complete reference for all `config/fulcrum.php` options and artisan command flags.

## Table of Contents

1. [Segment Driver](#segment-driver)
2. [Geo Resolver](#geo-resolver)
3. [User Agent Resolver](#user-agent-resolver)
4. [Holiday Resolver](#holiday-resolver)
5. [Pennant Integration](#pennant-integration)
6. [Queue Configuration](#queue-configuration)
7. [Stores](#stores)
8. [Cache](#cache)
9. [Migration Paths](#migration-paths)
10. [Table Names](#table-names)
11. [Settings Registration](#settings-registration)
12. [Types](#types)
13. [Condition Types](#condition-types)
14. [Carbon Configuration](#carbon-configuration)
15. [Masking & Encryption](#masking--encryption)
16. [Immutability](#immutability)
17. [Multi-Tenancy](#multi-tenancy)
18. [Rollout Configuration](#rollout-configuration)
19. [Data Portability](#data-portability)
20. [Telescope](#telescope)
21. [Artisan Command Reference](#artisan-command-reference)

---

## Segment Driver

```php
'segment_driver' => null,
```

- `null` — Disabled (default)
- `SpatiePermissionSegmentDriver::class` — Uses `spatie/laravel-permission`
- Custom class implementing `SegmentDriver` contract

## Geo Resolver

```php
'geo_resolver' => DefaultGeoResolver::class,
```

Resolves geographic location from IP. Implement `GeoResolver` contract for custom providers.

## User Agent Resolver

```php
'user_agent_resolver' => DefaultUserAgentResolver::class,
```

Parses user agent into device, browser, and OS fields. Implement `UserAgentResolver` contract to customize.

## Holiday Resolver

```php
'holiday_resolver' => null,
'holidays' => [
    'default_region' => env('FULCRUM_HOLIDAY_REGION', null),
    'locale' => env('FULCRUM_HOLIDAY_LOCALE', 'en_US'),
    'providers' => [],
],
```

Set to a class implementing `HolidayResolver` to enable `IS_HOLIDAY` / `IS_BUSINESS_DAY` operators.
Integrates with the Yasumi library if configured.

## Pennant Integration

```php
'pennant' => [
    'enabled' => env('FULCRUM_PENNANT_ENABLED', false),
],
```

Enables the drop-in Pennant driver so `Feature::active()` calls resolve through Fulcrum.

## Queue Configuration

```php
'queue' => [
    'connection' => env('FULCRUM_QUEUE_CONNECTION'),
    'queues' => [
        'imports'  => env('FULCRUM_QUEUE_IMPORTS', 'fulcrum-imports'),
        'exports'  => env('FULCRUM_QUEUE_EXPORTS', 'fulcrum-exports'),
        'cache'    => env('FULCRUM_QUEUE_CACHE', 'fulcrum-cache'),
        'audit'    => env('FULCRUM_QUEUE_AUDIT', 'fulcrum-audit'),
    ],
    'defaults' => [
        'tries'   => env('FULCRUM_JOB_TRIES', 3),
        'timeout' => env('FULCRUM_JOB_TIMEOUT', 60),
        'backoff' => env('FULCRUM_JOB_BACKOFF', 60),
    ],
],
```

## Stores

```php
'stores' => [
    'database' => [
        'driver' => 'database',
        'connection' => env('FULCRUM_DATABASE_CONNECTION', 'default'),
        'cache' => ['enabled' => null, 'prefix' => null, 'ttl' => null],
    ],
    'redis' => [
        'driver' => 'redis',
        'connection' => null,
        'cache' => ['enabled' => null, 'prefix' => null, 'ttl' => null],
    ],
],
```

Store-level cache settings override global cache settings when non-null.

## Cache

```php
'cache' => [
    'enabled' => env('FULCRUM_CACHE_ENABLED', false),
    'store'   => env('FULCRUM_CACHE_STORE', null),
    'ttl'     => env('FULCRUM_CACHE_TTL', 3600),
    'prefix'  => env('FULCRUM_CACHE_PREFIX', 'fulcrum'),
],
```

**Note**: Fulcrum does not auto-invalidate scoped cache keys. Clear cache manually or rotate the prefix after changes.

## Migration Paths

```php
'migrations' => [
    'paths' => [database_path('migrations')],
],
```

## Table Names

```php
'table_names' => [
    'settings'                       => 'settings',
    'setting_rules'                  => 'setting_rules',
    'setting_rule_conditions'        => 'setting_rule_conditions',
    'setting_values'                 => 'setting_values',
    'setting_rule_rollout_variants'  => 'setting_rule_rollout_variants',
],
```

## Settings Registration

```php
'settings' => [
    'classes' => [],
    'discovery' => [
        'enabled' => true,
        'paths'   => [app_path('Settings')],
    ],
],
```

Auto-discovers `FulcrumSettings` subclasses in the configured paths.

## Types

```php
'types' => [
    'boolean' => BooleanTypeHandler::class,
    'integer' => IntegerTypeHandler::class,
    'float'   => FloatTypeHandler::class,
    'string'  => StringTypeHandler::class,
    'json'    => JsonTypeHandler::class,
    'array'   => ArrayTypeHandler::class,
    'carbon'  => CarbonTypeHandler::class,
    'bool'    => BooleanTypeHandler::class,   // alias
    'int'     => IntegerTypeHandler::class,   // alias
],
```

Add custom types here. Each must extend `SettingTypeHandler` with `get()`, `set()`, `validate()`.

## Condition Types

```php
'condition_types' => [
    'user'       => UserConditionTypeHandler::class,
    'geocoding'  => GeocodingConditionTypeHandler::class,
    'user_agent' => UserAgentConditionTypeHandler::class,
    'date_time'  => DateTimeConditionTypeHandler::class,
],
'condition_types_default' => 'user',
```

Add custom condition types here. Each must implement `ConditionTypeHandler` returning an `AttributeValue`.

## Carbon Configuration

```php
'carbon' => [
    'default_timezone'  => env('FULCRUM_CARBON_TIMEZONE', config('app.timezone')),
    'store_utc'         => env('FULCRUM_CARBON_STORE_UTC', true),
    'output_timezone'   => env('FULCRUM_CARBON_OUTPUT_TIMEZONE', null),
],
```

## Masking & Encryption

```php
'masking' => [
    'ability'            => env('FULCRUM_MASKING_ABILITY', 'viewSettingValue'),
    'placeholder'        => env('FULCRUM_MASKING_PLACEHOLDER', '********'),
    'mask_in_resolver'   => env('FULCRUM_MASK_IN_RESOLVER', false),
    'require_two_factor' => env('FULCRUM_REQUIRE_TWO_FACTOR', false),
],
```

## Immutability

```php
'immutability' => [
    'env_flag'                => env('FULCRUM_FORCE', false),
    'cli_flag'                => env('FULCRUM_FORCE_FLAG', 'force'),
    'delete_ability'          => env('FULCRUM_DELETE_IMMUTABLE_ABILITY', 'deleteImmutableSetting'),
    'allow_delete_via_gate'   => env('FULCRUM_ALLOW_DELETE_IMMUTABLE_VIA_GATE', true),
],
```

## Multi-Tenancy

```php
'multi_tenancy' => [
    'enabled'         => env('FULCRUM_MULTI_TENANCY', false),
    'tenant_resolver' => null,  // callable|class-string|null
],
```

The resolver should return a tenant ID string or null.

## Rollout Configuration

```php
'rollout' => [
    'identifier_resolver'   => null,
    'bucket_calculator'     => Crc32BucketCalculator::class,
    'distribution_strategy' => WeightDistributionStrategy::class,
    'bucket_precision'      => 100_000,
    'fire_assignment_events' => env('FULCRUM_FIRE_ASSIGNMENT_EVENTS', true),
],
```

## Data Portability

```php
'portability' => [
    'routes' => [
        'enabled'    => env('FULCRUM_PORTABILITY_ROUTES', false),
        'prefix'     => env('FULCRUM_PORTABILITY_PREFIX', 'fulcrum/portability'),
        'middleware' => ['api', 'auth'],
    ],
    'export_ability' => env('FULCRUM_EXPORT_ABILITY', 'exportFulcrumSettings'),
    'import_ability' => env('FULCRUM_IMPORT_ABILITY', 'importFulcrumSettings'),
],
```

## Telescope

```php
'telescope' => [
    'enabled' => env('FULCRUM_TELESCOPE_ENABLED', true),
],
```

---

## Artisan Command Reference

### fulcrum:set

```bash
php artisan fulcrum:set {key} {value?}
```

| Flag | Description |
|------|-------------|
| `--type=` | string, integer, float, boolean, json, carbon |
| `--description=` | Setting description |
| `--masked` | Store encrypted |
| `--immutable` | Prevent modification |
| `--tenant=` | Tenant-specific value |
| `--force` | Override immutability |

Omit `{value}` to enter interactive wizard for rules, conditions, and rollouts.

### fulcrum:get

```bash
php artisan fulcrum:get {key}
```

| Flag | Description |
|------|-------------|
| `--tenant=` | Resolve for specific tenant |
| `--reveal` | Reveal masked values |
| `--scope=` | Scope/identifier for rollout evaluation |

### fulcrum:list

```bash
php artisan fulcrum:list
```

| Flag | Description |
|------|-------------|
| `--tenant=` | Filter by tenant |
| `--no-tenants` | Only global settings |

### fulcrum:export

```bash
php artisan fulcrum:export --format=json --filename=settings.json
```

| Flag | Description |
|------|-------------|
| `--format=` | csv, json, xml, yaml, sql |
| `--directory=` | Output directory |
| `--filename=` | Output filename |
| `--decrypt` | Decrypt masked values |
| `--gzip` | Compress output |
| `--dry-run` | Preview without writing |
| `--connection=` | Database connection |
| `--anonymize` | Anonymize sensitive data |
| `--queue` | Dispatch as background job |
| `--queue-connection=` | Queue connection |
| `--queue-name=` | Queue name |

### fulcrum:import

```bash
php artisan fulcrum:import {path}
```

| Flag | Description |
|------|-------------|
| `--format=` | csv, json, xml, yaml, sql (auto-detected from extension) |
| `--mode=` | insert or upsert |
| `--truncate` | Truncate tables before import |
| `--conflict-handling=` | fail, skip, or log |
| `--dry-run` | Preview without writing |
| `--connection=` | Database connection |
| `--chunk-size=` | Import chunk size (default 1000) |
| `--queue` | Dispatch as background job |
| `--queue-connection=` | Queue connection |
| `--queue-name=` | Queue name |

### make:setting-migration

```bash
php artisan make:setting-migration {name} [--path=]
```

### fulcrum:migrate-spatie

```bash
php artisan fulcrum:migrate-spatie [--table=] [--connection=] [--force]
```
