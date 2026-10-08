<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\Lifecycle;

use GaiaTools\FulcrumSettings\Contracts;
use GaiaTools\FulcrumSettings\Facades\Fulcrum;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use GaiaTools\FulcrumSettings\Support\Settings\FulcrumSettings;
use Illuminate\Container\Container;

class FulcrumLifecycle
{
    public const STATEFUL_TYPES = [
        Contracts\SettingResolver::class,
        Contracts\RuleEvaluator::class,
        Contracts\GeoResolver::class,
        Contracts\UserAgentResolver::class,
        Contracts\SegmentDriver::class,
        Contracts\HolidayResolver::class,
        Contracts\ConditionTypeHandler::class,
        FulcrumSettings::class,
    ];

    private function isStatefulBinding(string $abstract, Container $container): bool
    {
        if (! $container->resolved($abstract) || ! $container->isShared($abstract)) {
            return false;
        }
        foreach (self::STATEFUL_TYPES as $type) {
            if (is_a($abstract, $type, true)) {
                return true;
            }
        }

        return false;
    }

    public function reset(Container $container): void
    {
        FulcrumContext::clear();
        Fulcrum::clearResolvedInstance(Contracts\SettingResolver::class);
        // Daemon workers also flush scoped bindings. Explicit cleanup covers
        // direct Worker::process calls and Octane sandboxes. Use binding keys,
        // including abstract-to-concrete mappings, rather than instance classes.
        foreach (array_keys($container->getBindings()) as $abstract) {
            if ($this->isStatefulBinding($abstract, $container)) {
                $container->forgetInstance($abstract);
            }
        }
        if ($container->make('config')->boolean('fulcrum.lifecycle.reset_authentication', true) && $container->resolved('auth')) {
            $container->make('auth')->forgetGuards();
        }
    }
}
