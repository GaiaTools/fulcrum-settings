<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Providers;

use GaiaTools\FulcrumSettings\Console\Commands\ExportSettingsCommand;
use GaiaTools\FulcrumSettings\Console\Commands\GetSettingCommand;
use GaiaTools\FulcrumSettings\Console\Commands\ImportSettingsCommand;
use GaiaTools\FulcrumSettings\Console\Commands\ListSettingsCommand;
use GaiaTools\FulcrumSettings\Console\Commands\MakeSettingMigrationCommand;
use GaiaTools\FulcrumSettings\Console\Commands\MigrateFromSpatieCommand;
use GaiaTools\FulcrumSettings\Console\Commands\SetSettingCommand;
use GaiaTools\FulcrumSettings\Facades\Fulcrum;
use GaiaTools\FulcrumSettings\Http\Controllers\DataPortabilityController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class FulcrumSettingsBootServiceProvider extends ServiceProvider
{
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
            __DIR__.'/../../config/fulcrum.php' => config_path('fulcrum.php'),
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
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'fulcrum');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../resources/views' => resource_path('views/vendor/fulcrum'),
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

        return array_filter(
            $allMigrations,
            fn (SplFileInfo $file): bool => $this->shouldIncludeMigration($file)
        );
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

    protected function shouldIncludeMigration(SplFileInfo $file): bool
    {
        $filename = $file->getFilename();

        if (str_contains($filename, 'add_tenant_id_to_fulcrum_tables')) {
            return Fulcrum::isMultiTenancyEnabled();
        }

        return true;
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
        return __DIR__.'/../../database/migrations';
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

    /**
     * Get the primary migration path (first configured path).
     * Used as the default destination when publishing migrations.
     */
    protected function getPrimaryMigrationPath(): string
    {
        $paths = $this->getApplicationMigrationPaths();

        return $paths[0] ?? database_path('migrations');
    }
}
