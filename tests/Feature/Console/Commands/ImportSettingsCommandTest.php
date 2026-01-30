<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Feature\Console\Commands;

use GaiaTools\FulcrumSettings\Jobs\ImportSettingsJob;
use GaiaTools\FulcrumSettings\Support\DataPortability\ImportManager;
use GaiaTools\FulcrumSettings\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use ReflectionProperty;
use Symfony\Component\Yaml\Yaml;

class ImportSettingsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_it_can_import_settings()
    {
        $data = [['key' => 'test_setting', 'type' => 'string']];
        Storage::disk('local')->put('import.json', json_encode($data));
        $fullPath = Storage::disk('local')->path('import.json');

        $this->artisan('fulcrum:import', [
            'path' => $fullPath,
            '--format' => 'json',
        ])
            ->expectsOutput('Settings imported successfully.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('settings', ['key' => 'test_setting']);
    }

    public function test_it_auto_detects_format_from_extension()
    {
        $data = [['key' => 'test_setting_auto', 'type' => 'string']];
        Storage::disk('local')->put('import.json', json_encode($data));
        $fullPath = Storage::disk('local')->path('import.json');

        // We don't provide --format, it should detect 'json' from extension
        $this->artisan('fulcrum:import', [
            'path' => $fullPath,
        ])
            ->expectsOutput('Settings imported successfully.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('settings', ['key' => 'test_setting_auto']);
    }

    public function test_it_auto_detects_format_from_gz_extension()
    {
        $data = [['key' => 'test_setting_gz', 'type' => 'string']];
        $json = json_encode($data);
        Storage::disk('local')->put('import.json.gz', gzencode($json));
        $fullPath = Storage::disk('local')->path('import.json.gz');

        $this->artisan('fulcrum:import', [
            'path' => $fullPath,
        ])
            ->expectsOutput('Settings imported successfully.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('settings', ['key' => 'test_setting_gz']);
    }

    public function test_it_fails_on_unsupported_format()
    {
        $this->artisan('fulcrum:import', [
            'path' => 'file.txt',
            '--format' => 'invalid',
        ])
            ->expectsOutput('Unsupported format: invalid')
            ->assertExitCode(1);
    }

    public function test_it_handles_import_failure()
    {
        $manager = Mockery::mock(ImportManager::class);
        $manager->shouldReceive('import')
            ->once()
            ->andReturn(false);

        $this->app->instance(ImportManager::class, $manager);

        $this->artisan('fulcrum:import', [
            'path' => 'some-path.json',
        ])
            ->expectsOutput('Import failed.')
            ->assertExitCode(1);
    }

    public function test_it_handles_exceptions()
    {
        $manager = Mockery::mock(ImportManager::class);
        $manager->shouldReceive('import')
            ->once()
            ->andThrow(new \Exception('Import error'));

        $this->app->instance(ImportManager::class, $manager);

        $this->artisan('fulcrum:import', [
            'path' => 'some-path.json',
        ])
            ->expectsOutput('Import failed: Import error')
            ->assertExitCode(1);
    }

    public function test_it_does_not_auto_detect_if_format_is_explicitly_provided()
    {
        $data = [['key' => 'test_setting_explicit', 'type' => 'string']];
        Storage::disk('local')->put('import.json', json_encode($data));
        $fullPath = Storage::disk('local')->path('import.json');

        $manager = Mockery::mock(ImportManager::class);
        $manager->shouldReceive('import')
            ->once()
            ->with(Mockery::type(\GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\JsonFormatter::class), Mockery::any(), Mockery::any())
            ->andReturn(true);

        $this->app->instance(ImportManager::class, $manager);

        // We provide --format=json explicitly, it is NOT the default 'csv', so it shouldn't call isDefaultOption or at least it should work
        $this->artisan('fulcrum:import', [
            'path' => $fullPath,
            '--format' => 'json',
        ])
            ->assertExitCode(0);
    }

    public function test_it_does_not_detect_if_format_is_default_csv()
    {
        $data = [['key' => 'test_setting_csv', 'type' => 'string']];
        Storage::disk('local')->put('import.csv', 'key,type\ntest_setting_csv,string');
        $fullPath = Storage::disk('local')->path('import.csv');

        // We provide --format=csv explicitly
        $this->artisan('fulcrum:import', [
            'path' => $fullPath,
            '--format' => 'csv',
        ])
            ->expectsOutput('Settings imported successfully.')
            ->assertExitCode(0);
    }

    public function test_it_can_import_settings_xml()
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<settings>
    <item>
        <key>xml_setting</key>
        <type>string</type>
    </item>
</settings>';
        Storage::disk('local')->put('import.xml', $xml);
        $fullPath = Storage::disk('local')->path('import.xml');

        $this->artisan('fulcrum:import', [
            'path' => $fullPath,
            '--format' => 'xml',
        ])
            ->expectsOutput('Settings imported successfully.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('settings', ['key' => 'xml_setting']);
    }

    public function test_it_auto_detects_xml_format_from_extension()
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<settings>
    <item>
        <key>xml_auto_setting</key>
        <type>string</type>
    </item>
</settings>';
        Storage::disk('local')->put('import.xml', $xml);
        $fullPath = Storage::disk('local')->path('import.xml');

        $this->artisan('fulcrum:import', [
            'path' => $fullPath,
        ])
            ->expectsOutput('Settings imported successfully.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('settings', ['key' => 'xml_auto_setting']);
    }

    public function test_it_can_import_settings_yaml()
    {
        $data = [['key' => 'yaml_setting', 'type' => 'string']];
        Storage::disk('local')->put('import.yaml', Yaml::dump($data));
        $fullPath = Storage::disk('local')->path('import.yaml');

        $this->artisan('fulcrum:import', [
            'path' => $fullPath,
            '--format' => 'yaml',
        ])
            ->expectsOutput('Settings imported successfully.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('settings', ['key' => 'yaml_setting']);
    }

    public function test_it_can_import_settings_sql()
    {
        $sql = "INSERT INTO settings (`key`, `type`, `description`, `masked`, `immutable`) VALUES ('sql_setting', 'string', 'SQL Import', 0, 0);";
        Storage::disk('local')->put('import.sql', $sql);
        $fullPath = Storage::disk('local')->path('import.sql');

        $this->artisan('fulcrum:import', [
            'path' => $fullPath,
            '--format' => 'sql',
        ])
            ->expectsOutput('Settings imported successfully.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('settings', ['key' => 'sql_setting']);
    }

    public function test_it_auto_detects_yaml_format_from_extension()
    {
        $data = [['key' => 'yaml_auto_setting', 'type' => 'string']];
        Storage::disk('local')->put('import.yaml', Yaml::dump($data));
        $fullPath = Storage::disk('local')->path('import.yaml');

        $this->artisan('fulcrum:import', [
            'path' => $fullPath,
        ])
            ->expectsOutput('Settings imported successfully.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('settings', ['key' => 'yaml_auto_setting']);
    }

    public function test_it_auto_detects_sql_format_from_extension()
    {
        $sql = "INSERT INTO settings (`key`, `type`, `description`, `masked`, `immutable`) VALUES ('sql_auto_setting', 'string', 'SQL Auto Import', 0, 0);";
        Storage::disk('local')->put('import.sql', $sql);
        $fullPath = Storage::disk('local')->path('import.sql');

        $this->artisan('fulcrum:import', [
            'path' => $fullPath,
        ])
            ->expectsOutput('Settings imported successfully.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('settings', ['key' => 'sql_auto_setting']);
    }

    public function test_it_can_queue_import_job()
    {
        Queue::fake();

        $this->artisan('fulcrum:import', [
            'path' => 'import.json',
            '--format' => 'json',
            '--queue' => true,
        ])
            ->expectsOutputToContain('Import job dispatched successfully with Batch ID:')
            ->assertExitCode(0);

        Queue::assertPushed(ImportSettingsJob::class, function ($job) {
            $pathProp = new ReflectionProperty($job, 'path');
            $pathProp->setAccessible(true);
            $formatProp = new ReflectionProperty($job, 'format');
            $formatProp->setAccessible(true);

            return $pathProp->getValue($job) === 'import.json' &&
                   $formatProp->getValue($job) === 'json';
        });
    }

    public function test_it_can_queue_import_job_with_custom_connection_and_queue()
    {
        Queue::fake();

        $this->artisan('fulcrum:import', [
            'path' => 'import.csv',
            '--format' => 'csv',
            '--queue' => true,
            '--queue-connection' => 'redis',
            '--queue-name' => 'custom-queue',
        ])
            ->expectsOutputToContain('Import job dispatched successfully with Batch ID:')
            ->assertExitCode(0);

        Queue::assertPushed(ImportSettingsJob::class, function ($job) {
            $pathProp = new ReflectionProperty($job, 'path');
            $pathProp->setAccessible(true);
            $formatProp = new ReflectionProperty($job, 'format');
            $formatProp->setAccessible(true);

            return $pathProp->getValue($job) === 'import.csv' &&
                   $formatProp->getValue($job) === 'csv' &&
                   $job->connection === 'redis' &&
                   $job->queue === 'custom-queue';
        });
    }

    public function test_it_can_queue_import_job_with_only_custom_connection()
    {
        Queue::fake();

        $this->artisan('fulcrum:import', [
            'path' => 'import.csv',
            '--queue' => true,
            '--queue-connection' => 'sqs',
        ])
            ->assertExitCode(0);

        Queue::assertPushed(ImportSettingsJob::class, function ($job) {
            return $job->connection === 'sqs';
        });
    }

    public function test_it_can_queue_import_job_with_only_custom_queue()
    {
        Queue::fake();

        $this->artisan('fulcrum:import', [
            'path' => 'import.csv',
            '--queue' => true,
            '--queue-name' => 'high-priority',
        ])
            ->assertExitCode(0);

        Queue::assertPushed(ImportSettingsJob::class, function ($job) {
            return $job->queue === 'high-priority';
        });
    }

    public function test_it_can_queue_import_job_with_all_options()
    {
        Queue::fake();

        $this->artisan('fulcrum:import', [
            'path' => 'import.json',
            '--queue' => true,
            '--mode' => 'insert',
            '--truncate' => true,
            '--conflict-handling' => 'skip',
            '--dry-run' => true,
            '--connection' => 'other',
            '--chunk-size' => 500,
        ])
            ->assertExitCode(0);

        Queue::assertPushed(ImportSettingsJob::class, function ($job) {
            $optionsProp = new ReflectionProperty($job, 'options');
            $optionsProp->setAccessible(true);
            $options = $optionsProp->getValue($job);

            return $options['mode'] === 'insert' &&
                   $options['truncate'] === true &&
                   $options['conflict_handling'] === 'skip' &&
                   $options['dry_run'] === true &&
                   $options['connection'] === 'other' &&
                   $options['chunk_size'] === 500;
        });
    }
}
