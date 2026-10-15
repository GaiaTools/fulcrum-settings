<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\Modules;

use GaiaTools\FulcrumSettings\Providers\FulcrumModulesServiceProvider;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use LogicException;
use Nwidart\Modules\Activators\FileActivator;
use Nwidart\Modules\Contracts\ActivatorInterface;
use Nwidart\Modules\Module;
use Throwable;

class FulcrumModuleActivator implements ActivatorInterface
{
    /** @var array<string, bool> */
    private readonly array $statuses;

    /** @var array<string, array{activation_setting: string, access_setting: ?string, requires: list<string>}> */
    private readonly array $managed;

    private ?FileActivator $fileActivator = null;

    public function __construct(private readonly Container $app)
    {
        // Laravel Modules can construct us before Fulcrum's main provider registers.
        if ($app instanceof Application) {
            $app->register(FulcrumModulesServiceProvider::class);
        }
        $this->managed = $app->make(ModuleConfiguration::class)->managed();
        try {
            $this->statuses = $app->make(ModuleActivation::class)->read();
        } catch (Throwable $exception) {
            // A store that cannot be constructed must also leave managed modules disabled.
            try {
                report($exception);
            } catch (Throwable) {
            }
            $this->statuses = [];
        }
    }

    public function hasStatus(Module|string $module, bool $status): bool
    {
        $name = $module instanceof Module ? $module->getName() : $module;
        if (array_key_exists($name, $this->managed)) {
            return ($this->statuses[$name] ?? false) === $status;
        }

        return $this->fallback()->hasStatus($module, $status);
    }

    public function enable(Module $module): void
    {
        $this->setActive($module, true);
    }

    public function disable(Module $module): void
    {
        $this->setActive($module, false);
    }

    public function setActive(Module $module, bool $active): void
    {
        $this->setActiveByName($module->getName(), $active);
    }

    public function setActiveByName(string $name, bool $active): void
    {
        if (! array_key_exists($name, $this->managed)) {
            $this->fallback()->setActiveByName($name, $active);

            return;
        }
        $this->app->make(ModuleSettings::class)->set($name, $active);
    }

    public function delete(Module $module): void
    {
        if (array_key_exists($module->getName(), $this->managed)) {
            throw new LogicException('Remove the module mapping and its Fulcrum settings explicitly before deleting the module.');
        }
        $this->fallback()->delete($module);
    }

    public function reset(): void
    {
        throw new LogicException('Use Fulcrum settings and fulcrum:modules:sync to manage activation.');
    }

    private function fallback(): FileActivator
    {
        return $this->fileActivator ??= $this->app->make(FileActivator::class);
    }
}
