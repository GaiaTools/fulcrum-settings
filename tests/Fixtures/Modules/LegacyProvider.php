<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Fixtures\Modules;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class LegacyProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->instance('module.Legacy.registered', true);
    }

    public function boot(): void
    {
        $this->app->make(Router::class)->get('/fixture-legacy', fn () => response('Legacy'));
    }
}
