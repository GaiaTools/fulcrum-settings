<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\Modules;

use GaiaTools\FulcrumSettings\Contracts\Modules\ActivationStore;
use GaiaTools\FulcrumSettings\Exceptions\ModuleActivationException;
use Illuminate\Cache\CacheManager;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Cache\LockProvider;
use Throwable;

class ModuleActivation
{
    public function __construct(
        private readonly ModuleConfiguration $configuration,
        private readonly ActivationStore $store,
        private readonly Repository $config,
        private readonly Container $app,
        private readonly ModuleSettings $settings,
    ) {}

    /** @return array<string, bool> */
    public function read(): array
    {
        try {
            $state = $this->store->read();
            if ($state === null) {
                return [];
            }
            if (($state['version'] ?? null) !== 1 || ($state['definitions'] ?? null) !== $this->configuration->fingerprint()
                || ! is_array($state['modules'] ?? null)) {
                throw new ModuleActivationException('Invalid or outdated module activation state. Run fulcrum:modules:sync.');
            }
            $statuses = [];
            foreach ($this->configuration->managed() as $name => $mapping) {
                $value = $state['modules'][$name] ?? false;
                if (! is_bool($value)) {
                    throw new ModuleActivationException("Invalid activation status for module {$name}.");
                }
                $statuses[$name] = $value;
            }
            $this->validate($statuses);

            return $statuses;
        } catch (Throwable $exception) {
            // Logging must not turn an unavailable activation store into a startup failure.
            try {
                report($exception);
            } catch (Throwable) {
                // Logging may be unavailable during early bootstrap; continue with disabled modules.
            }

            return [];
        }
    }

    /** @param array<string, bool> $statuses */
    public function validate(array $statuses): void
    {
        foreach ($this->configuration->managed() as $name => $mapping) {
            if (! ($statuses[$name] ?? false)) {
                continue;
            }
            foreach ($mapping['requires'] as $dependency) {
                if (! ($statuses[$dependency] ?? false)) {
                    throw new ModuleActivationException("Module {$name} requires enabled module {$dependency}.");
                }
            }
        }
    }

    /** @return array<string, bool> */
    public function sync(bool $dryRun = false): array
    {
        // Resolve desired state inside the lock so an older sync cannot overwrite a newer one.
        $store = $this->app->make(CacheManager::class)->store($this->config->string('fulcrum.modules.lock.store'))->getStore();
        if (! $store instanceof LockProvider) {
            throw new ModuleActivationException('The module activation lock store must support atomic locks.');
        }

        $lock = $store->lock($this->config->string('fulcrum.modules.lock.key'), $this->config->integer('fulcrum.modules.lock.seconds'));
        $lock->block($this->config->integer('fulcrum.modules.lock.wait'));
        try {
            $statuses = $this->settings->statuses();
            $this->validate($statuses);
            if (! $dryRun) {
                $this->store->write([
                    'version' => 1,
                    'definitions' => $this->configuration->fingerprint(),
                    'modules' => $statuses,
                ]);
            }

            return $statuses;
        } finally {
            $lock->release();
        }
    }
}
