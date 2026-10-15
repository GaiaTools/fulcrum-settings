<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\Modules;

use GaiaTools\FulcrumSettings\Contracts\SettingResolver;
use Illuminate\Container\Container;
use Nwidart\Modules\Contracts\RepositoryInterface;

class ModuleAccess
{
    public function __construct(
        private readonly ModuleConfiguration $configuration,
        private readonly SettingResolver $resolver,
        private readonly Container $app,
    ) {}

    public function allows(string $module, mixed $scope = null): bool
    {
        $key = $this->configuration->mappings()[$module]['access_setting'] ?? null;

        if ($key === null) {
            return false;
        }
        if ($this->app->bound(RepositoryInterface::class)) {
            $installed = $this->app->make(RepositoryInterface::class)->find($module);
            if ($installed === null || ! $installed->isEnabled()) {
                return false;
            }
        }

        return $this->resolver->get($key, false, $scope) === true;
    }
}
