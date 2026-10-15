<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Console\Commands;

use GaiaTools\FulcrumSettings\Support\Modules\ModuleActivation;
use GaiaTools\FulcrumSettings\Support\Modules\ModuleConfiguration;
use Illuminate\Console\Command;
use Nwidart\Modules\Contracts\RepositoryInterface;
use RuntimeException;
use Throwable;

class SyncModulesCommand extends Command
{
    protected $signature = 'fulcrum:modules:sync {--dry-run : Validate activation without publishing}';

    protected $description = 'Publish application-wide module activation from global Fulcrum settings';

    public function handle(ModuleActivation $activation, ModuleConfiguration $configuration, RepositoryInterface $modules): int
    {
        try {
            foreach (array_keys($configuration->managed()) as $name) {
                if ($modules->find($name) === null) {
                    throw new RuntimeException("Configured module {$name} is not installed.");
                }
            }
            $statuses = $activation->sync((bool) $this->option('dry-run'));
            $this->table(['Module', 'Published on next startup'], array_map(
                fn (string $name, bool $active): array => [$name, $active ? 'enabled' : 'disabled'],
                array_keys($statuses), array_values($statuses),
            ));
            if (! $this->option('dry-run')) {
                $this->info('Activation published. Refresh module, route and event caches in a new process and restart long-running workers.');
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
