<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Drivers\WeightDistributionStrategy;
use GaiaTools\FulcrumSettings\Drivers\YasumiHolidayResolver;
use GaiaTools\FulcrumSettings\Services\Crc32BucketCalculator;
use GaiaTools\FulcrumSettings\Types\ArrayTypeHandler;
use GaiaTools\FulcrumSettings\Types\BooleanTypeHandler;
use GaiaTools\FulcrumSettings\Types\CarbonTypeHandler;
use GaiaTools\FulcrumSettings\Types\FloatTypeHandler;
use GaiaTools\FulcrumSettings\Types\IntegerTypeHandler;
use GaiaTools\FulcrumSettings\Types\JsonTypeHandler;
use GaiaTools\FulcrumSettings\Types\StringTypeHandler;
use GaiaTools\FulcrumSettings\Conditions\UserConditionTypeHandler;
use GaiaTools\FulcrumSettings\Conditions\GeocodingConditionTypeHandler;
use GaiaTools\FulcrumSettings\Conditions\UserAgentConditionTypeHandler;
use GaiaTools\FulcrumSettings\Conditions\DateTimeConditionTypeHandler;

return [
    /*
    |--------------------------------------------------------------------------
    | Default Segment Driver
    |--------------------------------------------------------------------------
    |
    | The segment driver is responsible for determining which segments (roles,
    | groups, etc.) a user belongs to. You can specify a custom driver class
    | or use 'null' for no segment support.
    |
    | Available drivers:
    | - null (default)
    | - SpatiePermissionsDriver::class (requires spatie/laravel-permission)
    |
    */

    'segment_driver' => null,

    /*
    |--------------------------------------------------------------------------
    | Geographic Targeting Driver
    |--------------------------------------------------------------------------
    |
    | The geo resolver is responsible for determining the geographic location
    | of a user based on their IP address. This is used for geo-targeting rules.
    |
    | Available drivers:
    | - DefaultGeoResolver::class (default, returns IP only)
    |
    */
    'geo_resolver' => \GaiaTools\FulcrumSettings\Drivers\DefaultGeoResolver::class,

    /*
    |--------------------------------------------------------------------------
    | User Agent Targeting Driver
    |--------------------------------------------------------------------------
    |
    | The user agent resolver is responsible for determining device, browser,
    | and OS information from the user agent string.
    |
    | Available drivers:
    | - DefaultUserAgentResolver::class (default, basic regex-based detection)
    |
    */
    'user_agent_resolver' => \GaiaTools\FulcrumSettings\Drivers\DefaultUserAgentResolver::class,

    /*
    |--------------------------------------------------------------------------
    | Holiday Resolver
    |--------------------------------------------------------------------------
    |
    | The holiday resolver determines whether a given date is a holiday.
    | You can swap implementations by providing a different resolver class.
    |
    | Default: null (disabled). Provide a resolver class to enable.
    | Available options:
    |   \GaiaTools\FulcrumSettings\Drivers\YasumiHolidayResolver::class
    */
    'holiday_resolver' => YasumiHolidayResolver::class,

    /*
    |--------------------------------------------------------------------------
    | Holiday Configuration
    |--------------------------------------------------------------------------
    |
    | default_region: Fallback region when a rule does not specify one.
    | locale: Locale passed to the resolver (for Yasumi providers).
    | providers: Optional map from region codes to provider names.
    |
    */
    'holidays' => [
        'default_region' => env('FULCRUM_HOLIDAY_REGION', null),
        'locale' => env('FULCRUM_HOLIDAY_LOCALE', 'en_US'),
        'providers' => [
            // 'US' => 'UnitedStates',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Laravel Pennant Integration
    |--------------------------------------------------------------------------
    |
    | Enable integration with Laravel Pennant to use Feature::active() and
    | Feature::value() with Fulcrum's rule-based evaluation engine.
    |
    | To use: Set PENNANT_STORE=fulcrum in your .env file.
    |
    */

    'pennant' => [
        'enabled' => env('FULCRUM_PENNANT_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Define centralized queue configuration for all Fulcrum background jobs.
    | This allows you to specify which connection and named queues should
    | be used for different types of operations.
    |
    */

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

    /*
    |--------------------------------------------------------------------------
    | Fulcrum Stores
    |--------------------------------------------------------------------------
    | Stores are used to persist and retrieve data for Fulcrum's rule-based
    | evaluation engine. Multiple stores can be configured to support different
    | persistence mechanisms.
    |
    | Each store has a 'driver' which determines the type of store to use, and
    | additional configuration options specific to that driver.
    |
    | Available drivers: 'database', 'redis'
    |
    */

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

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | Configure caching behavior for setting resolution. Caching can
    | significantly improve performance for frequently accessed settings.
    |
    */

    'cache' => [
        'enabled' => env('FULCRUM_CACHE_ENABLED', false),
        'store' => env('FULCRUM_CACHE_STORE', null),
        'ttl' => env('FULCRUM_CACHE_TTL', 3600),
        'prefix' => env('FULCRUM_CACHE_PREFIX', 'fulcrum'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Paths
    |--------------------------------------------------------------------------
    |
    | Directories where Fulcrum migrations can be published and discovered.
    | Supports multiple paths and wildcards for modular applications.
    |
    */
    'migrations' => [
        'paths' => [
            database_path('migrations'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | database Tables
    |--------------------------------------------------------------------------
    |
    | Customize the table names used by Fulcrum if needed.
    |
    */

    'table_names' => [
        'settings' => 'settings',
        'setting_rules' => 'setting_rules',
        'setting_rule_conditions' => 'setting_rule_conditions',
        'setting_values' => 'setting_values',
        'setting_rule_rollout_variants' => 'setting_rule_rollout_variants',
    ],

    /*
    |--------------------------------------------------------------------------
    | Settings Registration
    |--------------------------------------------------------------------------
    |
    | Define your FulcrumSettings classes here for manual registration,
    | or specify paths for auto-discovery.
    |
    */

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

    /*
    |--------------------------------------------------------------------------
    | Publishing Paths
    |--------------------------------------------------------------------------
    */
    'publish' => [
        'enums_path' => app_path('Enums'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Setting Types
    |--------------------------------------------------------------------------
    |
    | Register setting types with their handlers.
    | The key is the type identifier, the value is the handler class.
    |
    */
    'types' => [
        'boolean' => BooleanTypeHandler::class,
        'integer' => IntegerTypeHandler::class,
        'float' => FloatTypeHandler::class,
        'string' => StringTypeHandler::class,
        'json' => JsonTypeHandler::class,
        'array' => ArrayTypeHandler::class,
        'carbon' => CarbonTypeHandler::class,

        // PHP type aliases (for reflection)
        'bool' => BooleanTypeHandler::class,
        'int' => IntegerTypeHandler::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Condition Types
    |--------------------------------------------------------------------------
    |
    | Register condition types with their resolvers.
    | The key is the type identifier, the value is the resolver class.
    |
    */

    'condition_types' => [
        'user' => UserConditionTypeHandler::class,
        'geocoding' => GeocodingConditionTypeHandler::class,
        'user_agent' => UserAgentConditionTypeHandler::class,
        'date_time' => DateTimeConditionTypeHandler::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Condition Types
    |--------------------------------------------------------------------------
    |
    | The default condition type to use when none is specified.
    |
    */

    'condition_types_default' => \GaiaTools\FulcrumSettings\Enums\ConditionType::USER->value,

    /*
    |--------------------------------------------------------------------------
    | Carbon & DateTime Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how Carbon instances and date/time values are handled.
    |
    | 'default_timezone': Default timezone for Carbon instances.
    | 'store_utc': If true, all times are converted to UTC before storage.
    | 'output_timezone': If set, retrieved Carbon instances are converted to this timezone.
    |
    */

    'carbon' => [
        'default_timezone' => env('FULCRUM_CARBON_TIMEZONE', config('app.timezone')),
        'store_utc' => env('FULCRUM_CARBON_STORE_UTC', true),
        'output_timezone' => env('FULCRUM_CARBON_OUTPUT_TIMEZONE', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Masking & Encryption
    |--------------------------------------------------------------------------
    |
    | When a setting is marked as masked, its values (default and rule values)
    | are stored encrypted. Unmasking requires passing the configured Gate
    | ability. You can also choose whether resolvers should return masked
    | placeholders or the real value by default.
    |
    */

    'masking' => [
        'ability' => env('FULCRUM_MASKING_ABILITY', 'viewSettingValue'),
        'placeholder' => env('FULCRUM_MASKING_PLACEHOLDER', '********'),
        'mask_in_resolver' => env('FULCRUM_MASK_IN_RESOLVER', false),
        'require_two_factor' => env('FULCRUM_REQUIRE_TWO_FACTOR', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Immutability Controls
    |--------------------------------------------------------------------------
    |
    | If a setting is immutable, modifications to the setting, its rules,
    | conditions, and values are blocked by default. You can allow overrides
    | via an ENV flag or an Artisan --force flag. Deleting an immutable
    | setting can be allowed based on a Gate ability.
    |
    */

    'immutability' => [
        'env_flag' => env('FULCRUM_FORCE', false),
        'cli_flag' => env('FULCRUM_FORCE_FLAG', 'force'),
        'delete_ability' => env('FULCRUM_DELETE_IMMUTABLE_ABILITY', 'deleteImmutableSetting'),
        'allow_delete_via_gate' => env('FULCRUM_ALLOW_DELETE_IMMUTABLE_VIA_GATE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Tenancy Configuration
    |--------------------------------------------------------------------------
    |
    | Fulcrum supports multi-tenancy by allowing settings to be scoped to a
    | specific tenant. This configuration controls the behavior of multi-
    | tenancy in Fulcrum. If enabled, settings will be associated with a
    | tenant and only accessible within that tenant's context.
    |
    | The 'tenant_resolver' can be:
    | - A class implementing TenantResolver
    | - A closure that returns the tenant ID
    | - null (context must be set manually via FulcrumContext::setTenantId())
    |
    */
    'multi_tenancy' => [
        'enabled' => env('FULCRUM_MULTI_TENANCY', false),
        'tenant_resolver' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Telescope Integration
    |--------------------------------------------------------------------------
    |
    | When Laravel Telescope is installed, Fulcrum can record detailed
    | information about setting resolutions. This helps debug why a
    | particular value was returned for a setting.
    |
    | To enable, add the watcher to your config/telescope.php:
    |
    | 'watchers' => [
    |     \GaiaTools\LaravelFulcrum\Support\Telescope\SettingResolutionWatcher::class => [
    |         'enabled' => env('TELESCOPE_FULCRUM_WATCHER', true),
    |         'include_scope' => false,
    |     ],
    | ],
    |
    */
    'telescope' => [
        'enabled' => env('FULCRUM_TELESCOPE_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Percentage Rollout Configuration
    |--------------------------------------------------------------------------
    |
    | Configure behavior for percentage-based rollouts and A/B testing.
    |
    | The 'identifier_resolver' determines which identifier is used to
    | consistently bucket users. It can be:
    | - null: Uses authenticated user ID, falls back to scope if scalar
    | - A closure: fn($scope, $user) => string|null
    | - A class implementing a __invoke method
    |
    | The 'bucket_calculator' determines the hashing algorithm used.
    | Default is CRC32 which provides good distribution and performance.
    |
    | The 'bucket_precision' controls granularity:
    | - 100000 = 0.001% precision (default, matches LaunchDarkly)
    | - 10000 = 0.01% precision
    | - 1000 = 0.1% precision
    |
    | Available distribution strategies:
    | - WeightDistributionStrategy::class: Simple cumulative weight distribution (default).
    | - StratifiedDistributionStrategy::class: Guarantees exact percentages by pre-assigning buckets.
    |
    */

    'rollout' => [
        'identifier_resolver' => null,
        'bucket_calculator' => Crc32BucketCalculator::class,
        'distribution_strategy' => WeightDistributionStrategy::class,
        'bucket_precision' => 100_000,
        'fire_assignment_events' => env('FULCRUM_FIRE_ASSIGNMENT_EVENTS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Portability (Import/Export)
    |--------------------------------------------------------------------------
    |
    | Configure behavior for importing and exporting settings.
    |
    */

    'portability' => [
        'routes' => [
            'enabled' => env('FULCRUM_PORTABILITY_ROUTES', false),
            'prefix' => env('FULCRUM_PORTABILITY_PREFIX', 'fulcrum/portability'),
            'middleware' => ['api', 'auth'],
        ],
        'export_ability' => env('FULCRUM_EXPORT_ABILITY', 'exportFulcrumSettings'),
        'import_ability' => env('FULCRUM_IMPORT_ABILITY', 'importFulcrumSettings'),
    ],
];
