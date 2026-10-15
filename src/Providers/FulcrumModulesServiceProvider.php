<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Providers;

use GaiaTools\FulcrumSettings\Console\Commands\ModuleStatusCommand;
use GaiaTools\FulcrumSettings\Console\Commands\SyncModulesCommand;
use GaiaTools\FulcrumSettings\Contracts\Modules\ActivationStore;
use GaiaTools\FulcrumSettings\Http\Middleware\EnsureModuleAccess;
use GaiaTools\FulcrumSettings\Support\Modules\FulcrumModuleActivator;
use GaiaTools\FulcrumSettings\Support\Modules\Stores\CacheActivationStore;
use GaiaTools\FulcrumSettings\Support\Modules\Stores\DatabaseActivationStore;
use GaiaTools\FulcrumSettings\Support\Modules\Stores\FilesystemActivationStore;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Redis\RedisServiceProvider;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Nwidart\Modules\Contracts\ActivatorInterface;

class FulcrumModulesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $path = __DIR__.'/../../config/fulcrum.php';
        if (! $this->app->configurationIsCached()) {
            $defaults = (require $path)['modules'];
            $configured = $this->app->make(Repository::class)->get('fulcrum.modules', []);
            if (! is_array($configured)) {
                throw new InvalidArgumentException('Fulcrum modules configuration must be an array.');
            }
            $modules = array_replace($defaults, $configured);
            foreach (['storage', 'lock'] as $section) {
                if (! is_array($modules[$section])) {
                    throw new InvalidArgumentException("Invalid Fulcrum modules {$section} configuration.");
                }
                $modules[$section] = array_replace($defaults[$section], $modules[$section]);
            }
            $this->app->make(Repository::class)->set('fulcrum.modules', $modules);
        }
        if ($this->app->make(Repository::class)->get('fulcrum.modules.storage.driver') === 'cache') {
            // Deferred framework services may not be registered when Modules reads activation.
            $this->app->register(CacheServiceProvider::class);
            $this->app->register(RedisServiceProvider::class);
        }
        $this->app->bindIf(ActivationStore::class, function ($app): ActivationStore {
            $driver = $app['config']->get('fulcrum.modules.storage.driver', 'filesystem');
            $class = match ($driver) {
                'filesystem' => FilesystemActivationStore::class,
                'cache' => CacheActivationStore::class,
                'database' => DatabaseActivationStore::class,
                default => $driver,
            };
            if (! is_string($class) || ! is_a($class, ActivationStore::class, true)) {
                throw new InvalidArgumentException('Invalid Fulcrum module activation store.');
            }

            return $app->make($class);
        });
    }

    public function boot(): void
    {
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('fulcrum.module', EnsureModuleAccess::class);
        $aliases = config('fulcrum.modules.aliases', []);
        if (! is_array($aliases)) {
            throw new InvalidArgumentException('Fulcrum module aliases must be an array.');
        }
        foreach ($aliases as $alias => $module) {
            if (! is_string($alias) || $alias === '' || ! is_string($module) || $module === ''
                || $alias === 'fulcrum.module' || isset($router->getMiddleware()[$alias]) || isset($router->getMiddlewareGroups()[$alias])) {
                throw new InvalidArgumentException('Fulcrum module aliases must be unique middleware names mapped to module names.');
            }
            $router->middlewareGroup($alias, ['fulcrum.module:'.$module]);
        }
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../stubs/create_module_activation_table.php.stub' => database_path('migrations/2026_10_14_000001_create_fulcrum_module_activation_table.php'),
            ], 'fulcrum-module-migrations');
        }
        if ($this->app->runningInConsole() && interface_exists(ActivatorInterface::class)) {
            $this->commands([SyncModulesCommand::class, ModuleStatusCommand::class]);
            $this->app->make(Dispatcher::class)->listen(CommandFinished::class, function (CommandFinished $event): void {
                if ($event->exitCode === 0 && in_array($event->command, ['module:enable', 'module:disable'], true)
                    && $this->moduleActivator() instanceof FulcrumModuleActivator) {
                    $event->output->writeln('<comment>Fulcrum-managed changes affect application-wide loading for everyone. Run fulcrum:modules:sync, refresh caches and restart workers to apply them.</comment>');
                }
            });
        }
    }

    private function moduleActivator(): ?ActivatorInterface
    {
        // The selected implementation is configurable; callers depend on the activator contract.
        return $this->app->bound(ActivatorInterface::class)
            ? $this->app->make(ActivatorInterface::class)
            : null;
    }
}
