<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\Lifecycle;

use GaiaTools\FulcrumSettings\Contracts;
use GaiaTools\FulcrumSettings\Facades\Fulcrum;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use Illuminate\Container\Container;

class FulcrumLifecycle
{
    /** @var array<class-string, bool> */
    private array $instances = [];

    public function track(object $instance): void
    {
        $this->instances[$instance::class] = true;
    }

    public function reset(Container $container): void
    {
        FulcrumContext::clear();
        Fulcrum::clearResolvedInstance(Contracts\SettingResolver::class);
        foreach (array_merge([
            Contracts\SettingResolver::class,
            Contracts\RuleEvaluator::class,
            Contracts\GeoResolver::class,
            Contracts\UserAgentResolver::class,
            Contracts\SegmentDriver::class,
            Contracts\HolidayResolver::class,
        ], array_keys($this->instances)) as $abstract) {
            $container->forgetInstance($abstract);
        }
        if ($container->resolved('auth')) {
            $container->make('auth')->forgetGuards();
        }
    }
}
