<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Console\Commands;

use GaiaTools\FulcrumSettings\Support\Modules\ModuleActivation;
use GaiaTools\FulcrumSettings\Support\Modules\ModuleConfiguration;
use GaiaTools\FulcrumSettings\Support\Modules\ModuleSettings;
use Illuminate\Console\Command;
use Nwidart\Modules\Contracts\RepositoryInterface;
use Throwable;

class ModuleStatusCommand extends Command
{
    protected $signature = 'fulcrum:modules:status';

    protected $description = 'Compare desired, published and currently loaded module activation';

    public function handle(ModuleSettings $settings, ModuleActivation $activation, ModuleConfiguration $configuration, RepositoryInterface $modules): int
    {
        try {
            $desired = $settings->statuses();
            $published = $activation->read();
            $rows = [];
            foreach (array_keys($configuration->managed()) as $name) {
                $installed = $modules->find($name);
                $loaded = $installed !== null && $installed->isEnabled();
                $loadedStatus = $loaded ? 'enabled' : 'disabled';
                $rows[] = [
                    $name,
                    ($desired[$name] ?? false) ? 'enabled' : 'disabled',
                    ($published[$name] ?? false) ? 'enabled' : 'disabled',
                    $installed === null ? 'not installed' : $loadedStatus,
                ];
            }
            $this->table(['Module', 'Desired', 'Published', 'This process'], $rows);
            $this->line('Other running processes may retain earlier activation until restarted.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
