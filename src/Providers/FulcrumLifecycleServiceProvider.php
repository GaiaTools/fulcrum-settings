<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Providers;

use GaiaTools\FulcrumSettings\Support\Lifecycle\FulcrumLifecycle;
use Illuminate\Container\Container;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\ServiceProvider;

class FulcrumLifecycleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FulcrumLifecycle::class);
    }

    public function boot(): void
    {
        $this->app->make('events')->listen(Looping::class, function (): void {
            $this->resetActiveContainer();
        });
        // Early Laravel 11 has no JobAttempted; exceptions also cover retries
        // that do not emit JobFailed.
        $completionEvents = $this->hasJobAttemptedEvent()
            ? [JobAttempted::class]
            : [JobProcessed::class, JobExceptionOccurred::class, JobFailed::class];
        $this->app->make('events')->listen($completionEvents, function ($event): void {
            // Sync and deferred jobs keep their caller scope. Background jobs
            // also execute as SyncJob, in a separate process.
            if (! $event->job instanceof SyncJob) {
                $this->resetActiveContainer();
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

    protected function hasJobAttemptedEvent(): bool
    {
        return class_exists(JobAttempted::class);
    }

    private function resetActiveContainer(): void
    {
        // Resolve the active container, which may be an Octane sandbox.
        $container = Container::getInstance();
        $container->make(FulcrumLifecycle::class)->reset($container);
    }
}
