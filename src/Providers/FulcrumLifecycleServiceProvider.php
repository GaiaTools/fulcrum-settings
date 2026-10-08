<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Providers;

use GaiaTools\FulcrumSettings\Contracts;
use GaiaTools\FulcrumSettings\Support\Lifecycle\FulcrumLifecycle;
use GaiaTools\FulcrumSettings\Support\Settings\FulcrumSettings;
use Illuminate\Container\Container;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\ServiceProvider;

class FulcrumLifecycleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FulcrumLifecycle::class);
        foreach ([Contracts\SettingResolver::class, Contracts\RuleEvaluator::class, Contracts\GeoResolver::class, Contracts\UserAgentResolver::class, Contracts\SegmentDriver::class, Contracts\HolidayResolver::class, Contracts\ConditionTypeHandler::class, FulcrumSettings::class] as $abstract) {
            $this->app->afterResolving($abstract, fn ($instance) => $this->app->make(FulcrumLifecycle::class)->track($instance));
        }
    }

    public function boot(): void
    {
        $this->app->make('events')->listen([JobProcessing::class, JobProcessed::class, JobExceptionOccurred::class, JobAttempted::class], function ($event): void {
            // Inline jobs share the caller's lifecycle and must not erase its context.
            if (! $event->job instanceof SyncJob) {
                app(FulcrumLifecycle::class)->reset(app());
            }
        });
        $this->app->make('events')->listen([
            'Laravel\\Octane\\Events\\RequestReceived',
            'Laravel\\Octane\\Events\\RequestTerminated',
            'Laravel\\Octane\\Events\\TaskReceived',
            'Laravel\\Octane\\Events\\TaskTerminated',
            'Laravel\\Octane\\Events\\TickReceived',
            'Laravel\\Octane\\Events\\TickTerminated',
            'Laravel\\Octane\\Events\\WorkerErrorOccurred',
        ], function ($event): void {
            if ($event->sandbox instanceof Container) {
                $event->sandbox->make(FulcrumLifecycle::class)->reset($event->sandbox);
            }
        });
    }
}
