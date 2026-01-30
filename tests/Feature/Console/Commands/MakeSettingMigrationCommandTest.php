<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Feature\Console\Commands;

use GaiaTools\FulcrumSettings\Tests\TestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

class MakeSettingMigrationCommandTest extends TestCase
{
    protected string $migrationPath;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-01-13 15:38:52');
        $this->migrationPath = storage_path('migrations');
        if (! File::isDirectory($this->migrationPath)) {
            File::makeDirectory($this->migrationPath, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        $this->cleanupMigrations();
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function cleanupMigrations(): void
    {
        if (File::isDirectory($this->migrationPath)) {
            File::deleteDirectory($this->migrationPath);
        }
    }

    public function test_it_can_create_a_new_setting_migration()
    {
        $name = 'create_test_setting';

        $this->artisan('make:setting-migration', [
            'name' => $name,
            '--path' => 'storage/migrations',
        ])
            ->expectsOutputToContain('created successfully')
            ->assertExitCode(0);

        $files = File::glob($this->migrationPath.'/*_'.$name.'.php');
        $this->assertCount(1, $files);

        $content = File::get($files[0]);
        $this->assertStringContainsString('use GaiaTools\FulcrumSettings\Database\Migrations\SettingMigration;', $content);
        $this->assertStringContainsString('return new class extends SettingMigration', $content);
    }

    public function test_it_can_create_a_migration_with_custom_path()
    {
        $name = 'add_feature_flag';
        $customPath = 'custom_migrations';
        $fullCustomPath = base_path($customPath);

        if (! File::isDirectory($fullCustomPath)) {
            File::makeDirectory($fullCustomPath);
        }

        try {
            $this->artisan('make:setting-migration', [
                'name' => $name,
                '--path' => $customPath,
            ])
                ->assertExitCode(0);

            $files = File::glob($fullCustomPath.'/*_'.$name.'.php');
            $this->assertCount(1, $files);
        } finally {
            File::deleteDirectory($fullCustomPath);
        }
    }

    public function test_it_creates_directory_if_it_does_not_exist()
    {
        $name = 'new_dir_test';
        $customPath = 'non_existent_path';
        $fullCustomPath = base_path($customPath);

        if (File::isDirectory($fullCustomPath)) {
            File::deleteDirectory($fullCustomPath);
        }

        try {
            $this->artisan('make:setting-migration', [
                'name' => $name,
                '--path' => $customPath,
            ])
                ->assertExitCode(0);

            $this->assertDirectoryExists($fullCustomPath);

            $files = File::glob($fullCustomPath.'/*_'.$name.'.php');
            $this->assertCount(1, $files);
        } finally {
            File::deleteDirectory($fullCustomPath);
        }
    }

    public function test_it_fails_if_migration_already_exists()
    {
        $name = 'collision_test';
        $this->artisan('make:setting-migration', [
            'name' => $name,
            '--path' => 'storage/migrations',
        ])->assertExitCode(0);

        $files = File::glob($this->migrationPath.'/*_'.$name.'.php');
        $this->assertCount(1, $files);

        // Run again immediately, should have same timestamp and thus same filename
        $this->artisan('make:setting-migration', [
            'name' => $name,
            '--path' => 'storage/migrations',
        ])
            ->assertExitCode(1)
            ->expectsOutputToContain('already exists');
    }

    public function test_it_uses_default_migration_path()
    {
        $name = 'unique_default_path_test';
        $fullDefaultPath = $this->app->databasePath('migrations');
        $tempPath = storage_path('temp_migrations');

        if (! File::isDirectory($tempPath)) {
            File::makeDirectory($tempPath, 0755, true);
        }

        // We temporarily change the database path for the application
        $this->app->useDatabasePath($tempPath);

        try {
            $this->artisan('make:setting-migration', [
                'name' => $name,
            ])
                ->assertExitCode(0)
                ->expectsOutputToContain('created successfully');

            $files = File::glob($tempPath.DIRECTORY_SEPARATOR.'migrations'.DIRECTORY_SEPARATOR.'*_'.$name.'.php');
            $this->assertCount(1, $files);
        } finally {
            File::deleteDirectory($tempPath);
            // Reset database path - Orchestra handles this mostly but better be safe
            $this->app->useDatabasePath(base_path('vendor/orchestra/testbench-core/laravel/database'));
        }
    }
}
