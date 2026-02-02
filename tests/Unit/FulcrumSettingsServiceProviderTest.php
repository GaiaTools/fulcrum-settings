<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Unit;

use GaiaTools\FulcrumSettings\Contracts\RuleEvaluator;
use GaiaTools\FulcrumSettings\Contracts\SegmentDriver;
use GaiaTools\FulcrumSettings\Contracts\SettingResolver;
use GaiaTools\FulcrumSettings\FulcrumSettingsServiceProvider;
use GaiaTools\FulcrumSettings\Providers\FulcrumSettingsBootServiceProvider;
use GaiaTools\FulcrumSettings\Support\ConditionTypeRegistry;
use GaiaTools\FulcrumSettings\Support\Settings\FulcrumSettings;
use GaiaTools\FulcrumSettings\Support\TypeRegistry;
use GaiaTools\FulcrumSettings\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use ReflectionClass;

class FulcrumSettingsServiceProviderTest extends TestCase
{
    public function test_it_registers_singletons()
    {
        $this->assertFalse($this->app->bound(SegmentDriver::class));
        $this->assertTrue($this->app->bound(RuleEvaluator::class));
        $this->assertTrue($this->app->bound(SettingResolver::class));
        $this->assertTrue($this->app->bound(ConditionTypeRegistry::class));
        $this->assertTrue($this->app->bound(TypeRegistry::class));
        $this->assertTrue($this->app->bound(\GaiaTools\FulcrumSettings\Contracts\BucketCalculator::class));
        $this->assertTrue($this->app->bound(\GaiaTools\FulcrumSettings\Contracts\DistributionStrategy::class));
    }

    public function test_it_registers_settings_classes_from_config()
    {
        Config::set('fulcrum.settings.classes', [
            TestSettings::class,
        ]);

        $provider = new FulcrumSettingsServiceProvider($this->app);
        $provider->register();

        $this->assertTrue($this->app->bound(TestSettings::class));
    }

    public function test_it_discovers_settings_classes()
    {
        $tempPath = storage_path('framework/testing/settings');
        if (! is_dir($tempPath)) {
            mkdir($tempPath, 0777, true);
        }

        $settingsFile = $tempPath.'/MyDiscoveredSettings.php';
        file_put_contents($settingsFile, '<?php
namespace GaiaTools\FulcrumSettings\Tests\Unit;
use GaiaTools\FulcrumSettings\Support\Settings\FulcrumSettings;
class MyDiscoveredSettings extends FulcrumSettings {}
');

        require_once $settingsFile;

        Config::set('fulcrum.settings.discovery.enabled', true);
        Config::set('fulcrum.settings.discovery.paths', [$tempPath]);

        $provider = new FulcrumSettingsServiceProvider($this->app);
        $provider->register();

        $this->assertTrue($this->app->bound('GaiaTools\FulcrumSettings\Tests\Unit\MyDiscoveredSettings'));

        unlink($settingsFile);
        rmdir($tempPath);
    }

    public function test_it_skips_discovery_when_path_does_not_exist()
    {
        Config::set('fulcrum.settings.discovery.enabled', true);
        Config::set('fulcrum.settings.discovery.paths', ['/non/existent/path']);

        $provider = new FulcrumSettingsServiceProvider($this->app);
        $provider->register();

        // No exception should be thrown
        $this->assertTrue(true);
    }

    public function test_it_skips_abstract_classes_during_discovery()
    {
        $tempPath = storage_path('framework/testing/settings_abstract');
        if (! is_dir($tempPath)) {
            mkdir($tempPath, 0777, true);
        }

        $settingsFile = $tempPath.'/AbstractSettings.php';
        file_put_contents($settingsFile, '<?php
namespace GaiaTools\FulcrumSettings\Tests\Unit;
use GaiaTools\FulcrumSettings\Support\Settings\FulcrumSettings;
abstract class AbstractSettings extends FulcrumSettings {}
');

        require_once $settingsFile;

        Config::set('fulcrum.settings.discovery.enabled', true);
        Config::set('fulcrum.settings.discovery.paths', [$tempPath]);

        $provider = new FulcrumSettingsServiceProvider($this->app);
        $provider->register();

        $this->assertFalse($this->app->bound('GaiaTools\FulcrumSettings\Tests\Unit\AbstractSettings'));

        unlink($settingsFile);
        rmdir($tempPath);
    }

    public function test_get_class_from_file_without_namespace()
    {
        $tempPath = storage_path('framework/testing/settings_no_ns');
        if (! is_dir($tempPath)) {
            mkdir($tempPath, 0777, true);
        }

        $settingsFile = $tempPath.'/NoNamespaceSettings.php';
        file_put_contents($settingsFile, '<?php
class NoNamespaceSettings {}
');

        $provider = new FulcrumSettingsServiceProvider($this->app);
        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('getClassFromFile');
        $method->setAccessible(true);

        $className = $method->invoke($provider, $settingsFile);

        $this->assertEquals('NoNamespaceSettings', $className);

        unlink($settingsFile);
        rmdir($tempPath);
    }

    public function test_get_class_from_file_with_no_class()
    {
        $tempPath = storage_path('framework/testing/settings_no_class');
        if (! is_dir($tempPath)) {
            mkdir($tempPath, 0777, true);
        }

        $settingsFile = $tempPath.'/NoClass.php';
        file_put_contents($settingsFile, '<?php namespace GaiaTools\FulcrumSettings\Tests\Unit; ');

        $provider = new FulcrumSettingsServiceProvider($this->app);
        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('getClassFromFile');
        $method->setAccessible(true);

        $className = $method->invoke($provider, $settingsFile);

        $this->assertEquals('GaiaTools\FulcrumSettings\Tests\Unit\\', $className);

        unlink($settingsFile);
        rmdir($tempPath);
    }

    public function test_it_resolves_segment_driver()
    {
        Config::set('fulcrum.segment_driver', \GaiaTools\FulcrumSettings\Drivers\SpatiePermissionSegmentDriver::class);

        // Re-register or re-bind because it's a singleton already bound by the provider during TestCase boot
        $this->app->singleton(SegmentDriver::class, function ($app) {
            $driverClass = config('fulcrum.segment_driver');

            return $app->make($driverClass);
        });

        $driver = $this->app->make(SegmentDriver::class);
        $this->assertInstanceOf(\GaiaTools\FulcrumSettings\Drivers\SpatiePermissionSegmentDriver::class, $driver);
    }

    public function test_it_resolves_rule_evaluator()
    {
        $evaluator = $this->app->make(RuleEvaluator::class);
        $this->assertInstanceOf(\GaiaTools\FulcrumSettings\Services\RuleEvaluator::class, $evaluator);
    }

    public function test_it_resolves_setting_resolver()
    {
        Config::set('fulcrum.cache.enabled', true);
        Config::set('fulcrum.cache.prefix', 'test_prefix');
        Config::set('fulcrum.cache.ttl', 100);

        $resolver = $this->app->make(SettingResolver::class);
        $this->assertInstanceOf(\GaiaTools\FulcrumSettings\Services\CachedSettingResolver::class, $resolver);
    }

    public function test_boot_publishes_config_enums_and_migrations()
    {
        $provider = new FulcrumSettingsBootServiceProvider($this->app);
        $provider->boot();

        $publishes = FulcrumSettingsBootServiceProvider::$publishes[FulcrumSettingsBootServiceProvider::class] ?? [];

        $this->assertNotEmpty($publishes);

        $tags = FulcrumSettingsBootServiceProvider::$publishGroups;
        $this->assertArrayHasKey('config', $tags);
        $this->assertArrayHasKey('migrations', $tags);
    }

    public function test_it_detects_published_migrations()
    {
        $provider = new FulcrumSettingsBootServiceProvider($this->app);
        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('areMigrationsPublished');
        $method->setAccessible(true);

        // Initially not published
        $this->assertFalse($method->invoke($provider));

        // Mock migration file in an isolated path to avoid parallel test races
        $migrationPath = storage_path('framework/testing/fulcrum-migrations-'.uniqid());
        mkdir($migrationPath, 0777, true);
        Config::set('fulcrum.migrations.paths', [$migrationPath]);

        // Use a known migration name from the package
        $suffix = 'create_settings_table.php';
        $fakeMigration = $migrationPath.'/2025_01_01_000000_'.$suffix;
        file_put_contents($fakeMigration, '<?php ?>');

        try {
            $this->assertTrue($method->invoke($provider));
        } finally {
            unlink($fakeMigration);
            @rmdir($migrationPath);
        }
    }

    public function test_boot_returns_early_when_not_running_in_console()
    {
        $app = $this->createMock(\Illuminate\Contracts\Foundation\Application::class);
        $app->method('runningInConsole')->willReturn(false);

        $provider = new FulcrumSettingsBootServiceProvider($app);
        $provider->boot();

        $this->assertTrue(true);
    }

    public function test_it_filters_multi_tenancy_migration_when_disabled()
    {
        Config::set('fulcrum.multi_tenancy.enabled', false);

        $provider = new FulcrumSettingsBootServiceProvider($this->app);
        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('getPackageMigrationFiles');
        $method->setAccessible(true);

        $files = $method->invoke($provider);

        foreach ($files as $file) {
            $this->assertStringNotContainsString('add_tenant_id_to_fulcrum_tables', $file->getFilename());
        }
    }

    public function test_it_includes_multi_tenancy_migration_when_enabled()
    {
        Config::set('fulcrum.multi_tenancy.enabled', true);

        $provider = new FulcrumSettingsBootServiceProvider($this->app);
        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('getPackageMigrationFiles');
        $method->setAccessible(true);

        $files = $method->invoke($provider);

        $found = false;
        foreach ($files as $file) {
            if (str_contains($file->getFilename(), 'add_tenant_id_to_fulcrum_tables')) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Multi-tenancy migration should be included when enabled');
    }

    public function test_get_application_migration_paths_with_wildcards()
    {
        $tempPath = storage_path('framework/testing/modules_'.uniqid());
        mkdir($tempPath, 0777, true);
        $moduleAPath = $tempPath.'/ModuleA/Database/Migrations';
        mkdir($moduleAPath, 0777, true);

        Config::set('fulcrum.migrations.paths', [$tempPath.'/*/Database/Migrations']);

        $provider = new FulcrumSettingsBootServiceProvider($this->app);
        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('getApplicationMigrationPaths');
        $method->setAccessible(true);

        $paths = $method->invoke($provider);

        $this->assertContains($moduleAPath, $paths);

        rmdir($moduleAPath);
        rmdir(dirname($moduleAPath));
        rmdir(dirname(dirname($moduleAPath)));
        rmdir($tempPath);
    }

    public function test_migration_exists_in_path_returns_false_if_not_directory()
    {
        $provider = new FulcrumSettingsBootServiceProvider($this->app);
        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('migrationExistsInPath');
        $method->setAccessible(true);

        $mockFile = $this->createMock(\Symfony\Component\Finder\SplFileInfo::class);
        $mockFile->method('getFilename')->willReturn('2024_01_01_000000_test.php');

        $result = $method->invoke($provider, $mockFile, '/non/existent/path');
        $this->assertFalse($result);
    }

    public function test_discover_settings_handles_wildcards_and_missing_directories()
    {
        $tempPath = storage_path('framework/testing/discovery_wildcards');
        if (! is_dir($tempPath)) {
            mkdir($tempPath, 0777, true);
        }

        Config::set('fulcrum.settings.discovery.enabled', true);
        Config::set('fulcrum.settings.discovery.paths', [
            $tempPath.'/*/Settings', // wildcard
            '/non/existent/path',    // missing directory
        ]);

        $provider = new FulcrumSettingsServiceProvider($this->app);
        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('discoverSettings');
        $method->setAccessible(true);

        $result = $method->invoke($provider);
        $this->assertIsArray($result);
        $this->assertEmpty($result);

        rmdir($tempPath);
    }

    public function test_are_migrations_published_returns_false_if_no_package_migrations()
    {
        // To test this we need to mock getPackageMigrationsPath to return a non-existent directory
        $provider = $this->getMockBuilder(FulcrumSettingsBootServiceProvider::class)
            ->setConstructorArgs([$this->app])
            ->onlyMethods(['getPackageMigrationsPath'])
            ->getMock();

        $provider->method('getPackageMigrationsPath')->willReturn('/non/existent/migrations');

        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('areMigrationsPublished');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($provider));
    }

    public function test_boot_migrations_loads_migrations_when_not_published()
    {
        // Mock areMigrationsPublished to return false
        // Mock getPackageMigrationFiles to return a list of migrations
        $provider = $this->getMockBuilder(FulcrumSettingsBootServiceProvider::class)
            ->setConstructorArgs([$this->app])
            ->onlyMethods(['areMigrationsPublished', 'getPackageMigrationFiles', 'loadMigrationsFrom'])
            ->getMock();

        $provider->method('areMigrationsPublished')->willReturn(false);

        $mockFile = $this->createMock(\Symfony\Component\Finder\SplFileInfo::class);
        $mockFile->method('getPathname')->willReturn('/path/to/migration.php');

        $provider->method('getPackageMigrationFiles')->willReturn([$mockFile]);

        $provider->expects($this->once())
            ->method('loadMigrationsFrom')
            ->with('/path/to/migration.php');

        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('bootMigrations');
        $method->setAccessible(true);

        $method->invoke($provider);
    }

    public function test_discover_settings_adds_discovered_class()
    {
        $tempPath = storage_path('framework/testing/discovery_success');
        if (! is_dir($tempPath)) {
            mkdir($tempPath, 0777, true);
        }

        $settingsFile = $tempPath.'/DiscoveredSettings.php';
        file_put_contents($settingsFile, '<?php
namespace GaiaTools\FulcrumSettings\Tests\Unit;
use GaiaTools\FulcrumSettings\Support\Settings\FulcrumSettings;
class DiscoveredSettings extends FulcrumSettings {}
');

        require_once $settingsFile;

        Config::set('fulcrum.settings.discovery.enabled', true);
        Config::set('fulcrum.settings.discovery.paths', [$tempPath]);

        $provider = new FulcrumSettingsServiceProvider($this->app);
        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('discoverSettings');
        $method->setAccessible(true);

        $result = $method->invoke($provider);

        $this->assertContains('GaiaTools\FulcrumSettings\Tests\Unit\DiscoveredSettings', $result);

        unlink($settingsFile);
        rmdir($tempPath);
    }
}

class TestSettings extends FulcrumSettings {}
