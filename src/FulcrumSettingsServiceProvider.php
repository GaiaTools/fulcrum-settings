<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings;

use GaiaTools\FulcrumSettings\Console\Commands\ExportSettingsCommand;
use GaiaTools\FulcrumSettings\Console\Commands\GetSettingCommand;
use GaiaTools\FulcrumSettings\Console\Commands\ImportSettingsCommand;
use GaiaTools\FulcrumSettings\Console\Commands\ListSettingsCommand;
use GaiaTools\FulcrumSettings\Console\Commands\MakeSettingMigrationCommand;
use GaiaTools\FulcrumSettings\Console\Commands\MigrateFromSpatieCommand;
use GaiaTools\FulcrumSettings\Console\Commands\SetSettingCommand;
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
use GaiaTools\FulcrumSettings\Support\Registrars\SettingsDiscovery;
use GaiaTools\FulcrumSettings\Support\TypeRegistry;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
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

    public function boot(): void
    {
        $this->bootMigrations();
        $this->bootPublishables();
        $this->bootViews();
        $this->bootCommands();
        $this->bootRoutes();
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

    /**
     * Get the path for publishing enums.
     */
    protected function getEnumsPublishPath(string $filename): string
    {
        $basePath = config('fulcrum.publish.enums_path', app_path('Enums'));
        if (! is_string($basePath)) {
            $basePath = app_path('Enums');
        }

        return $basePath.'/'.$filename;
    }

    protected function registerSettingsClasses(): void
    {
        $settingsClasses = config('fulcrum.settings.classes', []);
        if (! is_array($settingsClasses)) {
            $settingsClasses = [];
        }

        if (config('fulcrum.settings.discovery.enabled', false)) {
            $paths = config('fulcrum.settings.discovery.paths', []);
            $paths = is_array($paths) ? $paths : [];
            $settingsClasses = array_merge($settingsClasses, $this->discoverSettings($paths));
        }

        foreach ($settingsClasses as $class) {
            if (is_string($class)) {
                $this->app->singleton($class);
            }
        }
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
            if (! is_string($strategyClass)) {
                $strategyClass = WeightDistributionStrategy::class;
            }

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
                (bool) config('fulcrum.cache.enabled', false),
                is_string(config('fulcrum.cache.prefix')) ? (string) config('fulcrum.cache.prefix') : 'fulcrum',
                is_numeric(config('fulcrum.cache.ttl')) ? (int) config('fulcrum.cache.ttl') : 3600,
                is_string(config('fulcrum.cache.store')) ? config('fulcrum.cache.store') : null
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

    protected function bootMigrations(): void
    {
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
            ExportSettingsCommand::class,
            GetSettingCommand::class,
            ImportSettingsCommand::class,
            ListSettingsCommand::class,
            MakeSettingMigrationCommand::class,
            MigrateFromSpatieCommand::class,
            SetSettingCommand::class,
        ]);
    }

    protected function bootRoutes(): void
    {
        $this->registerRoutes();
    }

    protected function registerRoutes(): void
    {
        if (! config('fulcrum.portability.routes.enabled', false)) {
            return;
        }

        $prefix = config('fulcrum.portability.routes.prefix', 'fulcrum/portability');
        $prefix = is_string($prefix) ? $prefix : 'fulcrum/portability';
        $middleware = config('fulcrum.portability.routes.middleware', ['api', 'auth']);
        $middleware = is_array($middleware) ? $middleware : ['api', 'auth'];

        Route::group([
            'prefix' => $prefix,
            'middleware' => $middleware,
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

        return array_filter(
            $allMigrations,
            fn (SplFileInfo $file): bool => $this->shouldIncludeMigration($file)
        );
    }

    protected function migrationExistsInPath(SplFileInfo $packageMigration, string $searchPath): bool
    {
        if (! is_dir($searchPath)) {
            return false;
        }

        $migrationSuffix = $this->getMigrationSuffix($packageMigration->getFilename());
        $pattern = $searchPath.'/*'.$migrationSuffix;

        return ! empty(glob($pattern));
    }

    protected function shouldIncludeMigration(SplFileInfo $file): bool
    {
        $filename = $file->getFilename();

        if (str_contains($filename, 'add_tenant_id_to_fulcrum_tables')) {
            return Fulcrum::isMultiTenancyEnabled();
        }

        return true;
    }

    protected function getMigrationSuffix(string $filename): string
    {
        return substr($filename, 18);
    }

    protected function getPackageMigrationsPath(): string
    {
        return __DIR__.'/../database/migrations';
    }

    /**
     * @return array<string>
     */
    protected function getApplicationMigrationPaths(): array
    {
        $paths = config('fulcrum.migrations.paths', [database_path('migrations')]);
        if (! is_array($paths)) {
            $paths = [database_path('migrations')];
        }

        $expandedPaths = [];
        foreach ($paths as $path) {
            if (! is_string($path)) {
                continue;
            }
            if (str_contains($path, '*')) {
                $matches = glob($path, GLOB_ONLYDIR);
                if (is_array($matches)) {
                    $expandedPaths = array_merge($expandedPaths, $matches);
                }
            } else {
                $expandedPaths[] = $path;
            }
        }

        return array_values(array_filter($expandedPaths, fn ($path) => ! empty($path)));
    }

    protected function getPrimaryMigrationPath(): string
    {
        $paths = $this->getApplicationMigrationPaths();

        return $paths[0] ?? database_path('migrations');
    }

    /**
     * @param  array<mixed>  $paths
     * @return array<int, class-string<FulcrumSettings>>
     */
    protected function discoverSettings(?array $paths = null): array
    {
        if ($paths === null) {
            $paths = config('fulcrum.settings.discovery.paths', []);
        }

        if (! is_array($paths)) {
            $paths = [];
        }

        return (new SettingsDiscovery)->discover($paths);
    }

    protected function getClassFromFile(string $path): ?string
    {
        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }
        $namespace = '';
        $class = '';

        if (preg_match('/namespace\\s+(.+?);/', $content, $matches)) {
            $namespace = $matches[1];
        }

        if (preg_match('/class\\s+(\\w+)/', $content, $matches)) {
            $class = $matches[1];
        }

        return $namespace ? $namespace.'\\'.$class : $class;
    }
}
