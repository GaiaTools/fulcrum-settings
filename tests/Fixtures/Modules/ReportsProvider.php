<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Fixtures\Modules;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class ReportsProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->instance('module.Reports.registered', true);
    }

    public function boot(): void
    {
        $this->app->make(Router::class)->get('/fixture-reports', fn () => response('Reports'));
    }
}
