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
use GaiaTools\FulcrumSettings\Providers\FulcrumSettingsBootServiceProvider;
use GaiaTools\FulcrumSettings\Services\CachedSettingResolver;
use GaiaTools\FulcrumSettings\Services\Crc32BucketCalculator;
use GaiaTools\FulcrumSettings\Services\RuleEvaluator;
use GaiaTools\FulcrumSettings\Services\SettingResolver;
use GaiaTools\FulcrumSettings\Support\ConditionTypeRegistry;
use GaiaTools\FulcrumSettings\Support\Registrars\SettingsDiscovery;
use GaiaTools\FulcrumSettings\Support\TypeRegistry;
use Illuminate\Support\ServiceProvider;

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
        $this->app->register(FulcrumSettingsBootServiceProvider::class);
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
            $settingsClasses = array_merge($settingsClasses, (new SettingsDiscovery)->discover($paths));
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
}
