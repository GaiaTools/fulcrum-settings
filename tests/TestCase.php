<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests;

use GaiaTools\FulcrumSettings\FulcrumSettingsServiceProvider;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $token = getenv('TEST_TOKEN') ?: (string) getmypid();
        $storagePath = sys_get_temp_dir().'/fulcrum-settings-tests/'.$token;
        if (! is_dir($storagePath)) {
            mkdir($storagePath, 0777, true);
        }
        $this->app->useStoragePath($storagePath);

        FulcrumContext::clear();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function getPackageProviders($app): array
    {
        return [
            FulcrumSettingsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:u3B8vL+f0L4+nQ6E6u6z6f/7Z6z6v7v8+z6v7v8z6v8=');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('fulcrum.multi_tenancy.enabled', true);
    }
}
