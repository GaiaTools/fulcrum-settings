<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Feature\Console\Commands;

use GaiaTools\FulcrumSettings\Jobs\ExportSettingsJob;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Support\DataPortability\ExportManager;
use GaiaTools\FulcrumSettings\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use ReflectionProperty;

class ExportSettingsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_it_can_export_settings_with_default_options()
    {
        Setting::create([
            'key' => 'test_setting',
            'type' => 'string',
        ]);

        $this->artisan('fulcrum:export', [
            '--format' => 'json',
            '--filename' => 'export.json',
        ])
            ->expectsOutputToContain('Settings exported successfully to:')
            ->assertExitCode(0);

        Storage::disk('local')->assertExists('export.json');
    }

    public function test_it_can_export_to_xml()
    {
        Setting::create([
            'key' => 'test_setting',
            'type' => 'string',
        ]);

        $this->artisan('fulcrum:export', [
            '--format' => 'xml',
            '--filename' => 'export.xml',
        ])
            ->expectsOutputToContain('Settings exported successfully to:')
            ->assertExitCode(0);

        Storage::disk('local')->assertExists('export.xml');
    }

    public function test_it_can_export_to_csv()
    {
        Setting::create([
            'key' => 'test_setting',
            'type' => 'string',
        ]);

        $this->artisan('fulcrum:export', [
            '--format' => 'csv',
            '--filename' => 'export.csv',
        ])
            ->expectsOutputToContain('Settings exported successfully to:')
            ->assertExitCode(0);

        Storage::disk('local')->assertExists('export.csv');
    }

    public function test_it_can_export_to_yaml()
    {
        Setting::create([
            'key' => 'test_setting',
            'type' => 'string',
        ]);

        $this->artisan('fulcrum:export', [
            '--format' => 'yaml',
            '--filename' => 'export.yaml',
        ])
            ->expectsOutputToContain('Settings exported successfully to:')
            ->assertExitCode(0);

        Storage::disk('local')->assertExists('export.yaml');
        $content = Storage::disk('local')->get('export.yaml');
        $this->assertStringContainsString('test_setting', $content);
    }

    public function test_it_can_export_to_yml_alias()
    {
        Setting::create([
            'key' => 'test_setting_yml',
            'type' => 'string',
        ]);

        $this->artisan('fulcrum:export', [
            '--format' => 'yml',
            '--filename' => 'export.yml',
        ])
            ->expectsOutputToContain('Settings exported successfully to:')
            ->assertExitCode(0);

        Storage::disk('local')->assertExists('export.yml');
        $content = Storage::disk('local')->get('export.yml');
        $this->assertStringContainsString('test_setting_yml', $content);
    }

    public function test_it_can_export_to_sql()
    {
        Setting::create([
            'key' => 'test_setting',
            'type' => 'string',
        ]);

        $this->artisan('fulcrum:export', [
            '--format' => 'sql',
            '--filename' => 'export.sql',
        ])
            ->expectsOutputToContain('Settings exported successfully to:')
            ->assertExitCode(0);

        Storage::disk('local')->assertExists('export.sql');
        $content = Storage::disk('local')->get('export.sql');
        $this->assertStringContainsString('INSERT INTO `settings`', $content);
        $this->assertStringContainsString('test_setting', $content);
    }

    public function test_it_fails_on_unsupported_format()
    {
        $this->artisan('fulcrum:export', [
            '--format' => 'invalid',
        ])
            ->expectsOutput('Unsupported format: invalid')
            ->assertExitCode(1);
    }

    public function test_it_can_perform_a_dry_run()
    {
        Setting::create([
            'key' => 'test_setting',
            'type' => 'string',
        ]);

        $this->artisan('fulcrum:export', [
            '--format' => 'json',
            '--dry-run' => true,
        ])
            ->expectsOutput('Dry run completed successfully.')
            ->assertExitCode(0);
    }

    public function test_it_handles_export_failure()
    {
        $manager = Mockery::mock(ExportManager::class);
        $manager->shouldReceive('export')
            ->once()
            ->andReturn(false);

        $this->app->instance(ExportManager::class, $manager);

        $this->artisan('fulcrum:export', [
            '--format' => 'json',
        ])
            ->expectsOutput('Export failed.')
            ->assertExitCode(1);
    }

    public function test_it_handles_exceptions()
    {
        $manager = Mockery::mock(ExportManager::class);
        $manager->shouldReceive('export')
            ->once()
            ->andThrow(new \Exception('Something went wrong'));

        $this->app->instance(ExportManager::class, $manager);

        $this->artisan('fulcrum:export', [
            '--format' => 'json',
        ])
            ->expectsOutput('Export failed: Something went wrong')
            ->assertExitCode(1);
    }

    public function test_it_can_queue_export_job()
    {
        Queue::fake();

        $this->artisan('fulcrum:export', [
            '--format' => 'json',
            '--queue' => true,
        ])
            ->expectsOutputToContain('Export job dispatched successfully with Batch ID:')
            ->assertExitCode(0);

        Queue::assertPushed(ExportSettingsJob::class, function ($job) {
            $formatProp = new ReflectionProperty($job, 'format');
            $formatProp->setAccessible(true);

            return $formatProp->getValue($job) === 'json';
        });
    }

    public function test_it_can_queue_export_job_with_custom_connection_and_queue()
    {
        Queue::fake();

        $this->artisan('fulcrum:export', [
            '--format' => 'csv',
            '--queue' => true,
            '--queue-connection' => 'redis',
            '--queue-name' => 'custom-queue',
        ])
            ->expectsOutputToContain('Export job dispatched successfully with Batch ID:')
            ->assertExitCode(0);

        Queue::assertPushed(ExportSettingsJob::class, function ($job) {
            $formatProp = new ReflectionProperty($job, 'format');
            $formatProp->setAccessible(true);

            return $formatProp->getValue($job) === 'csv' &&
                   $job->connection === 'redis' &&
                   $job->queue === 'custom-queue';
        });
    }
}
