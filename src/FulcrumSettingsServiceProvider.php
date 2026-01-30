<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings;

use GaiaTools\FulcrumSettings\Contracts\BucketCalculator as BucketCalculatorContract;
use GaiaTools\FulcrumSettings\Contracts\DistributionStrategy as DistributionStrategyContract;
use GaiaTools\FulcrumSettings\Contracts\GeoResolver as GeoResolverContract;
use GaiaTools\FulcrumSettings\Contracts\HolidayResolver as HolidayResolverContract;
use GaiaTools\FulcrumSettings\Contracts\RuleEvaluator as RuleEvaluatorContract;
use GaiaTools\FulcrumSettings\Contracts\SegmentDriver as SegmentDriverContract;
use GaiaTools\FulcrumSettings\Contracts\SettingResolver as SettingResolverContract;
use GaiaTools\FulcrumSettings\Contracts\UserAgentResolver as UserAgentResolverContract;
use GaiaTools\FulcrumSettings\Drivers\WeightDistributionStrategy;
use GaiaTools\FulcrumSettings\Facades\Fulcrum;
use GaiaTools\FulcrumSettings\Http\Controllers\DataPortabilityController;
use GaiaTools\FulcrumSettings\Services\CachedSettingResolver;
use GaiaTools\FulcrumSettings\Services\Crc32BucketCalculator;
use GaiaTools\FulcrumSettings\Services\RuleEvaluator;
use GaiaTools\FulcrumSettings\Services\SettingResolver;
use GaiaTools\FulcrumSettings\Support\ConditionTypeRegistry;
use GaiaTools\FulcrumSettings\Support\Settings\FulcrumSettings;
use GaiaTools\FulcrumSettings\Support\TypeRegistry;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class FulcrumSettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/fulcrum.php',
            'fulcrum'
        );

        $this->registerSegmentDriver();
        $this->registerGeoResolver();
        $this->registerUserAgentResolver();
        $this->registerHolidayResolver();
        $this->registerBucketCalculator();
        $this->registerDistributionStrategy();
        $this->registerConditionTypeRegistry();
        $this->registerRuleEvaluator();
        $this->registerSettingResolver();
        $this->registerSettingsClasses();
        $this->registerTypeRegistry();
        $this->registerDataPortabilityResponses();
    }

    protected function registerDataPortabilityResponses(): void
    {
        $this->app->singleton(
            Contracts\DataPortability\ImportViewResponse::class,
            Http\Responses\ImportViewResponse::class
        );
        $this->app->singleton(
            Contracts\DataPortability\ImportResponse::class,
            Http\Responses\ImportResponse::class
        );
        $this->app->singleton(
            Contracts\DataPortability\ExportViewResponse::class,
            Http\Responses\ExportViewResponse::class
        );
        $this->app->singleton(
            Contracts\DataPortability\ExportResponse::class,
            Http\Responses\ExportResponse::class
        );
    }

    public function boot(): void
    {
        $this->bootMigrations();
        $this->bootPublishables();
        $this->bootViews();
        $this->bootCommands();
        $this->bootRoutes();
    }

    protected function bootMigrations(): void
    {
        // Load package migrations only if not published anywhere
        if (! $this->areMigrationsPublished()) {
            foreach ($this->getPackageMigrationFiles() as $migration) {
                $this->loadMigrationsFrom($migration->getPathname());
            }
        }
    }

    protected function bootPublishables(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/fulcrum.php' => config_path('fulcrum.php'),
        ], 'config');

        // Publish migrations to first configured path
        $publishMigrations = [];
        foreach ($this->getPackageMigrationFiles() as $migration) {
            $publishMigrations[$migration->getPathname()] = $this->getPrimaryMigrationPath().'/'.$migration->getFilename();
        }

        $this->publishes($publishMigrations, 'migrations');
    }

    protected function bootViews(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'fulcrum');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/fulcrum'),
            ], 'views');
        }
    }

    protected function bootCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            Console\Commands\ExportSettingsCommand::class,
            Console\Commands\GetSettingCommand::class,
            Console\Commands\ImportSettingsCommand::class,
            Console\Commands\ListSettingsCommand::class,
            Console\Commands\MakeSettingMigrationCommand::class,
            Console\Commands\MigrateFromSpatieCommand::class,
            Console\Commands\SetSettingCommand::class,
        ]);
    }

    protected function bootRoutes(): void
    {
        $this->registerRoutes();
    }

    /**
     * Check if package migrations have been published to any configured path.
     *
     * Scans all configured migration paths (including module wildcards).
     * Returns true if any package migration is found in any path.
     */
    protected function areMigrationsPublished(): bool
    {
        $packageMigrations = $this->getPackageMigrationFiles();

        if (empty($packageMigrations)) {
            return false;
        }

        foreach ($this->getApplicationMigrationPaths() as $appPath) {
            foreach ($packageMigrations as $packageMigration) {
                if ($this->migrationExistsInPath($packageMigration, $appPath)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get all migration files from the package.
     *
     * @return array<SplFileInfo>
     */
    protected function getPackageMigrationFiles(): array
    {
        $packageMigrationsPath = $this->getPackageMigrationsPath();

        if (! is_dir($packageMigrationsPath)) {
            return [];
        }

        $allMigrations = iterator_to_array(
            (new Finder)
                ->in($packageMigrationsPath)
                ->files()
                ->name('*.php')
                ->sortByName()
        );

        return array_filter($allMigrations, function (SplFileInfo $file) {
            if (str_contains($file->getFilename(), 'add_tenant_id_to_fulcrum_tables')) {
                return Fulcrum::isMultiTenancyEnabled();
            }

            return true;
        });
    }

    /**
     * Check if a package migration exists in a specific path.
     */
    protected function migrationExistsInPath(SplFileInfo $packageMigration, string $searchPath): bool
    {
        if (! is_dir($searchPath)) {
            return false;
        }

        $migrationSuffix = $this->getMigrationSuffix($packageMigration->getFilename());
        $pattern = $searchPath.'/*'.$migrationSuffix;

        return ! empty(glob($pattern));
    }

    /**
     * Extract migration suffix (everything after timestamp).
     *
     * Example: "2024_01_15_120000_create_fulcrum_tables.php"
     *       -> "create_fulcrum_tables.php"
     */
    protected function getMigrationSuffix(string $filename): string
    {
        return substr($filename, 18);
    }

    /**
     * Get the package migrations directory path.
     */
    protected function getPackageMigrationsPath(): string
    {
        return __DIR__.'/../database/migrations';
    }

    /**
     * Get all application migration paths.
     *
     * Supports wildcards for modular applications:
     * - "Modules/* /Database/Migrations" expands to all module paths
     *
     * @return array<string>
     */
    protected function getApplicationMigrationPaths(): array
    {
        $paths = config('fulcrum.migrations.paths', [database_path('migrations')]);

        $expandedPaths = [];
        foreach ((array) $paths as $path) {
            if (str_contains($path, '*')) {
                $expandedPaths = array_merge($expandedPaths, glob($path, GLOB_ONLYDIR));
            } else {
                $expandedPaths[] = $path;
            }
        }

        return array_filter($expandedPaths, fn ($path) => is_string($path) && ! empty($path));
    }

    /**
     * Get the primary migration path (first configured path).
     * Used as the default destination when publishing migrations.
     */
    protected function getPrimaryMigrationPath(): string
    {
        $paths = $this->getApplicationMigrationPaths();

        return $paths[0] ?? database_path('migrations');
    }

    /**
     * Get the path for publishing enums.
     */
    protected function getEnumsPublishPath(string $filename): string
    {
        $basePath = config('fulcrum.publish.enums_path', app_path('Enums'));

        return $basePath.'/'.$filename;
    }

    protected function registerRoutes(): void
    {
        if (! config('fulcrum.portability.routes.enabled', false)) {
            return;
        }

        Route::group([
            'prefix' => config('fulcrum.portability.routes.prefix', 'fulcrum/portability'),
            'middleware' => config('fulcrum.portability.routes.middleware', ['api', 'auth']),
        ], function () {
            Route::get('export/create', [DataPortabilityController::class, 'showExport'])
                 ->name('fulcrum.portability.export.create');
            Route::post('export', [DataPortabilityController::class, 'export'])
                 ->name('fulcrum.portability.export');
            Route::get('import/create', [DataPortabilityController::class, 'showImport'])
                 ->name('fulcrum.portability.import.create');
            Route::post('import', [DataPortabilityController::class, 'import'])
                 ->name('fulcrum.portability.import');
        });
    }

    protected function registerSettingsClasses(): void
    {
        $settingsClasses = config('fulcrum.settings.classes', []);

        if (config('fulcrum.settings.discovery.enabled', false)) {
            $settingsClasses = array_merge($settingsClasses, $this->discoverSettings());
        }

        foreach ($settingsClasses as $class) {
            $this->app->singleton($class);
        }
    }

    protected function discoverSettings(): array
    {
        $paths = config('fulcrum.settings.discovery.paths', []);
        $settings = [];

        foreach ($paths as $path) {
            $expandedPaths = str_contains($path, '*')
                ? glob($path, GLOB_ONLYDIR)
                : [$path];

            foreach ($expandedPaths as $expandedPath) {
                if (! is_dir($expandedPath)) {
                    continue;
                }

                foreach ((new Finder)->in($expandedPath)->files()->name('*.php') as $file) {
                    $class = $this->getClassFromFile($file->getPathname());

                    if ($class && is_subclass_of($class, FulcrumSettings::class) && ! (new ReflectionClass($class))->isAbstract()) {
                        $settings[] = $class;
                    }
                }
            }
        }

        return array_unique($settings);
    }

    protected function getClassFromFile(string $path): ?string
    {
        $content = file_get_contents($path);
        $namespace = '';
        $class = '';

        if (preg_match('/namespace\s+(.+?);/', $content, $matches)) {
            $namespace = $matches[1];
        }

        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            $class = $matches[1];
        }

        return $namespace ? $namespace.'\\'.$class : $class;
    }

    protected function registerGeoResolver(): void
    {
        $this->app->singleton(GeoResolverContract::class, function ($app) {
            $resolverClass = config('fulcrum.geo_resolver');

            return $app->make($resolverClass);
        });
    }

    protected function registerUserAgentResolver(): void
    {
        $this->app->singleton(UserAgentResolverContract::class, function ($app) {
            $resolverClass = config('fulcrum.user_agent_resolver');

            return $app->make($resolverClass);
        });
    }

    protected function registerSegmentDriver(): void
    {
        $driverClass = config('fulcrum.segment_driver');

        if (! is_string($driverClass) || $driverClass === '') {
            return;
        }

        $this->app->singleton(SegmentDriverContract::class, fn ($app) => $app->make($driverClass));
    }

    protected function registerRuleEvaluator(): void
    {
        $this->app->singleton(RuleEvaluatorContract::class, function ($app) {
            $segmentDriver = $app->bound(SegmentDriverContract::class)
                ? $app->make(SegmentDriverContract::class)
                : null;
            $holidayResolver = $app->bound(HolidayResolverContract::class)
                ? $app->make(HolidayResolverContract::class)
                : null;

            return new RuleEvaluator(
                $segmentDriver,
                $holidayResolver,
                $app->make(ConditionTypeRegistry::class)
            );
        });
    }

    protected function registerHolidayResolver(): void
    {
        $resolverClass = config('fulcrum.holiday_resolver');

        if (! is_string($resolverClass) || $resolverClass === '') {
            return;
        }

        $this->app->singleton(HolidayResolverContract::class, fn ($app) => $app->make($resolverClass));
    }

    protected function registerBucketCalculator(): void
    {
        $this->app->singleton(BucketCalculatorContract::class, function ($app) {
            $calculatorClass = config('fulcrum.rollout.bucket_calculator', Crc32BucketCalculator::class);

            return $app->make($calculatorClass);
        });
    }

    protected function registerDistributionStrategy(): void
    {
        $this->app->singleton(DistributionStrategyContract::class, function ($app) {
            $strategyClass = config('fulcrum.rollout.distribution_strategy', WeightDistributionStrategy::class);

            return $app->make($strategyClass);
        });
    }

    protected function registerSettingResolver(): void
    {
        $this->app->singleton(SettingResolverContract::class, function ($app) {
            $baseResolver = new SettingResolver(
                $app->make(RuleEvaluatorContract::class),
                $app->make(BucketCalculatorContract::class),
                $app->make(DistributionStrategyContract::class),
            );

            return new CachedSettingResolver(
                $baseResolver,
                config('fulcrum.cache.enabled', false),
                (string) config('fulcrum.cache.prefix', 'fulcrum'),
                (int) config('fulcrum.cache.ttl', 3600),
                config('fulcrum.cache.store')
            );
        });
    }

    protected function registerTypeRegistry(): void
    {
        $this->app->singleton(TypeRegistry::class, fn ($app) => new TypeRegistry);
    }

    protected function registerConditionTypeRegistry(): void
    {
        $this->app->singleton(ConditionTypeRegistry::class, fn ($app) => new ConditionTypeRegistry);
    }
}
