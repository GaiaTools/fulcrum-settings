<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Feature\Modules;

use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\FulcrumSettingsServiceProvider;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Support\Modules\FulcrumModuleActivator;
use GaiaTools\FulcrumSettings\Support\Modules\ModuleAccess;
use GaiaTools\FulcrumSettings\Support\Modules\ModuleActivation;
use GaiaTools\FulcrumSettings\Support\Modules\ModuleConfiguration;
use GaiaTools\FulcrumSettings\Support\Modules\ModuleSettings;
use GaiaTools\FulcrumSettings\Tests\TestCase;
use Illuminate\Cache\FileStore;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Routing\Router;
use Nwidart\Modules\Contracts\ActivatorInterface;
use Nwidart\Modules\Contracts\RepositoryInterface;
use Nwidart\Modules\LaravelModulesServiceProvider;

class LaravelModulesIntegrationTest extends TestCase
{
    private ?string $directory = null;

    protected function setUp(): void
    {
        if (version_compare(Application::VERSION, '13.0.0', '<')) {
            $this->markTestSkipped('Laravel Modules 13 integration is supported on Laravel 13.');
        }
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        $this->directory ??= sys_get_temp_dir().'/fulcrum-modules-integration-'.bin2hex(random_bytes(8));
        (new Filesystem)->ensureDirectoryExists($this->directory.'/bootstrap/cache');
        $app->useBootstrapPath($this->directory.'/bootstrap');
        $modules = require __DIR__.'/../../../vendor/nwidart/laravel-modules/config/config.php';
        $modules['paths']['modules'] = __DIR__.'/../../Fixtures/Modules';
        $modules['scan']['enabled'] = false;
        $modules['activators']['fulcrum'] = ['class' => FulcrumModuleActivator::class];
        $modules['activators']['file']['statuses-file'] = $this->directory.'/modules_statuses.json';
        $modules['activator'] = 'fulcrum';
        $app['config']->set('modules', $modules);
        $app['config']->set('fulcrum.modules', [
            'mappings' => [
                'Reports' => ['activation_setting' => 'module.reports.loaded', 'access_setting' => 'module.reports.enabled'],
                'Export' => ['activation_setting' => 'module.export.loaded', 'requires' => ['Reports']],
            ],
            'aliases' => ['reports.enabled' => 'Reports'],
            'storage' => ['path' => $this->directory.'/activation.json'],
            'lock' => ['store' => 'array'],
        ]);
        file_put_contents($this->directory.'/modules_statuses.json', '{"Reports":false,"Export":true,"Legacy":true}');
        if (str_contains($this->name(), 'missing_state')) {
            if (is_file($this->directory.'/activation.json')) {
                unlink($this->directory.'/activation.json');
            }
        } else {
            $state = [
                'version' => 1,
                'definitions' => (new ModuleConfiguration($app['config']))->fingerprint(),
                'modules' => ['Reports' => true, 'Export' => false],
            ];
            file_put_contents($this->directory.'/activation.json', json_encode($state, JSON_THROW_ON_ERROR));
            if (str_contains($this->name(), 'cache_storage')) {
                $app['config']->set('fulcrum.modules.storage.driver', 'cache');
                $app['config']->set('fulcrum.modules.storage.cache_store', 'module-fixture');
                $app['config']->set('cache.stores.module-fixture', ['driver' => 'file', 'path' => $this->directory.'/cache']);
                (new FileStore(new Filesystem, $this->directory.'/cache'))->forever('fulcrum:module-activation', $state);
            }
            if (str_contains($this->name(), 'database_storage')) {
                $app['config']->set('fulcrum.modules.storage.driver', 'database');
                $app['config']->set('fulcrum.modules.storage.connection', 'module-state');
                $app['config']->set('database.connections.module-state', ['driver' => 'sqlite', 'database' => $this->directory.'/activation.sqlite']);
                $database = new \PDO('sqlite:'.$this->directory.'/activation.sqlite');
                if (! str_contains($this->name(), 'missing_database_storage')) {
                    $database->exec('CREATE TABLE fulcrum_module_activation (key TEXT PRIMARY KEY, payload TEXT NOT NULL)');
                    $statement = $database->prepare('INSERT INTO fulcrum_module_activation (key, payload) VALUES (?, ?)');
                    $statement->execute(['default', json_encode($state, JSON_THROW_ON_ERROR)]);
                }
            }
            if (str_contains($this->name(), 'cached_configuration')) {
                $defaults = (require __DIR__.'/../../../config/fulcrum.php');
                $custom = $app['config']->get('fulcrum.modules');
                $defaults['modules'] = array_replace($defaults['modules'], $custom);
                foreach (['storage', 'lock'] as $section) {
                    $defaults['modules'][$section] = array_replace((require __DIR__.'/../../../config/fulcrum.php')['modules'][$section], $custom[$section]);
                }
                $app['config']->set('fulcrum', $defaults);
                file_put_contents($app->getCachedConfigPath(), '<?php return [];');
                $app->instance('config_loaded_from_cache', true);
            }
        }

        return str_contains($this->name(), 'fulcrum_first')
            ? [FulcrumSettingsServiceProvider::class, LaravelModulesServiceProvider::class]
            : [LaravelModulesServiceProvider::class, FulcrumSettingsServiceProvider::class];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if ($this->directory !== null) {
            (new Filesystem)->deleteDirectory($this->directory);
        }
    }

    public function test_module_registration_works_when_laravel_modules_registers_first(): void
    {
        $this->assertTrue($this->app->bound('module.Reports.registered'));
        $this->assertFalse($this->app->bound('module.Export.registered'));
        $this->assertTrue($this->app->bound('module.Legacy.registered'));
        $this->get('/fixture-reports')->assertOk();
        $this->get('/fixture-export')->assertNotFound();
        $this->assertInstanceOf(FulcrumModuleActivator::class, $this->app->make(ActivatorInterface::class));
    }

    public function test_cache_storage_is_read_during_early_registration(): void
    {
        $this->assertTrue($this->app->bound('module.Reports.registered'));
        $this->assertFalse($this->app->bound('module.Export.registered'));
    }

    public function test_database_storage_is_read_during_early_registration(): void
    {
        $this->assertTrue($this->app->bound('module.Reports.registered'));
        $this->assertFalse($this->app->bound('module.Export.registered'));
    }

    public function test_missing_database_storage_table_does_not_prevent_startup(): void
    {
        $this->assertFalse($this->app->bound('module.Reports.registered'));
        $this->get('/fixture-legacy')->assertOk();
    }

    public function test_cached_configuration_keeps_custom_activation_and_aliases(): void
    {
        $this->assertTrue($this->app->configurationIsCached());
        $this->assertTrue($this->app->bound('module.Reports.registered'));
        $this->assertSame(['fulcrum.module:Reports'], $this->app->make(Router::class)->getMiddlewareGroups()['reports.enabled']);
    }

    public function test_module_registration_works_with_fulcrum_first(): void
    {
        $this->assertTrue($this->app->bound('module.Reports.registered'));
        $this->assertFalse($this->app->bound('module.Export.registered'));
    }

    public function test_missing_state_keeps_application_and_unmanaged_modules_available(): void
    {
        $this->assertFalse($this->app->bound('module.Reports.registered'));
        $this->assertFalse($this->app->bound('module.Export.registered'));
        $this->get('/fixture-legacy')->assertOk();
    }

    public function test_sync_command_validates_and_publishes_only_managed_modules(): void
    {
        $this->app->make(ModuleSettings::class)->set('Reports', true);
        $this->artisan('fulcrum:modules:sync', ['--dry-run' => true])->assertSuccessful();
        $this->artisan('fulcrum:modules:sync')->expectsOutputToContain('Activation published')->assertSuccessful();
        $this->assertSame(['Reports' => true, 'Export' => false], $this->app->make(ModuleActivation::class)->read());
    }

    public function test_standard_commands_change_desired_state_without_changing_loaded_modules(): void
    {
        $this->app->make(ModuleSettings::class)->set('Reports', true);
        $this->app->make(Kernel::class)->rerouteSymfonyCommandEvents();
        $this->artisan('module:disable', ['module' => ['Reports']])->expectsOutputToContain('application-wide loading')->assertSuccessful();
        $this->assertFalse($this->app->make(ModuleSettings::class)->statuses()['Reports']);
        $this->assertTrue($this->app->make(RepositoryInterface::class)->find('Reports')->isEnabled());
        $this->artisan('module:enable', ['module' => ['Reports']])->assertSuccessful();
        $this->assertTrue($this->app->make(ModuleSettings::class)->statuses()['Reports']);
    }

    public function test_contextual_access_cannot_allow_an_unloaded_module(): void
    {
        config(['fulcrum.modules.mappings.Export.access_setting' => 'module.export.enabled']);
        $setting = Setting::create(['key' => 'module.export.enabled', 'type' => SettingType::BOOLEAN]);
        $setting->defaultValue()->create(['value' => true]);
        $this->assertFalse($this->app->make(ModuleAccess::class)->allows('Export'));
    }

    public function test_status_command_distinguishes_desired_published_and_loaded_activation(): void
    {
        $this->app->make(ModuleSettings::class)->set('Reports', false);
        $this->artisan('fulcrum:modules:status')
            ->expectsTable(['Module', 'Desired', 'Published', 'This process'], [
                ['Reports', 'disabled', 'enabled', 'enabled'],
                ['Export', 'disabled', 'disabled', 'disabled'],
            ])->assertSuccessful();
    }

    public function test_sync_rejects_missing_installed_modules(): void
    {
        config(['fulcrum.modules.mappings.Absent' => ['activation_setting' => 'module.absent.loaded']]);
        $this->artisan('fulcrum:modules:sync')->expectsOutputToContain('not installed')->assertFailed();
    }

    public function test_unmanaged_modules_continue_using_the_file_activator(): void
    {
        $activator = $this->app->make(ActivatorInterface::class);
        $this->assertTrue($activator->hasStatus('Legacy', true));
        $activator->setActiveByName('Legacy', false);
        $this->assertTrue($activator->hasStatus('Legacy', false));
        $this->assertSame(['Reports' => false, 'Export' => true, 'Legacy' => false], json_decode(file_get_contents($this->directory.'/modules_statuses.json'), true));
    }
}
