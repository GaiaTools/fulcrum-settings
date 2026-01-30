<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Feature\Console\Commands;

use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateFromSpatieCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Spatie settings table
        Schema::create('spatie_settings', function ($table) {
            $table->id();
            $table->string('group');
            $table->string('name');
            $table->boolean('locked')->default(false);
            $table->json('payload');
            $table->timestamps();
        });
    }

    public function test_it_can_migrate_settings_from_spatie()
    {
        // Insert some dummy Spatie settings
        DB::table('spatie_settings')->insert([
            [
                'group' => 'general',
                'name' => 'site_name',
                'locked' => false,
                'payload' => json_encode('My Site'),
            ],
            [
                'group' => 'general',
                'name' => 'maintenance_mode',
                'locked' => true,
                'payload' => json_encode(false),
            ],
            [
                'group' => 'app',
                'name' => 'max_users',
                'locked' => false,
                'payload' => json_encode(100),
            ],
            [
                'group' => 'app',
                'name' => 'config',
                'locked' => false,
                'payload' => json_encode(['key' => 'value']),
            ],
        ]);

        $this->artisan('fulcrum:migrate-spatie', ['--table' => 'spatie_settings'])
            ->expectsOutputToContain('Found 4 settings to migrate.')
            ->expectsOutputToContain('Successfully migrated 4 settings.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('settings', [
            'key' => 'general.site_name',
            'type' => SettingType::STRING->value,
            'immutable' => false,
        ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'general.maintenance_mode',
            'type' => SettingType::BOOLEAN->value,
            'immutable' => true,
        ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'app.max_users',
            'type' => SettingType::INTEGER->value,
            'immutable' => false,
        ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'app.config',
            'type' => SettingType::JSON->value,
            'immutable' => false,
        ]);

        // Verify values
        $this->assertEquals('My Site', Setting::where('key', 'general.site_name')->first()->getDefaultValue());
        $this->assertEquals(false, Setting::where('key', 'general.maintenance_mode')->first()->getDefaultValue());
        $this->assertEquals(100, Setting::where('key', 'app.max_users')->first()->getDefaultValue());
        $this->assertEquals(['key' => 'value'], Setting::where('key', 'app.config')->first()->getDefaultValue());
    }

    public function test_it_skips_existing_settings_unless_forced()
    {
        // Pre-create a setting in Fulcrum
        Setting::create([
            'key' => 'general.site_name',
            'type' => SettingType::STRING->value,
        ]);

        DB::table('spatie_settings')->insert([
            'group' => 'general',
            'name' => 'site_name',
            'locked' => false,
            'payload' => json_encode('New Name'),
        ]);

        $this->artisan('fulcrum:migrate-spatie', ['--table' => 'spatie_settings'])
            ->expectsOutputToContain('Setting [general.site_name] already exists in Fulcrum. Skipping.')
            ->assertExitCode(0);

        // Value should not have changed
        $this->assertNull(Setting::where('key', 'general.site_name')->first()->getDefaultValue());

        // Now force it
        $this->artisan('fulcrum:migrate-spatie', ['--table' => 'spatie_settings', '--force' => true])
            ->expectsOutputToContain('Successfully migrated 1 settings.')
            ->assertExitCode(0);

        $this->assertEquals('New Name', Setting::where('key', 'general.site_name')->first()->getDefaultValue());
    }

    public function test_it_fails_if_table_does_not_exist()
    {
        $this->artisan('fulcrum:migrate-spatie', ['--table' => 'non_existent'])
            ->expectsOutputToContain('Spatie settings table [non_existent] not found')
            ->assertExitCode(1);
    }

    public function test_it_defaults_to_spatie_settings_table_if_settings_table_is_already_migrated()
    {
        // Spatie table exists as spatie_settings
        // settings table exists but doesn't have 'group' column (which is true in Fulcrum as it has 'key')
        // Actually, Fulcrum 'settings' table DOES NOT have 'group' column.

        DB::table('spatie_settings')->insert([
            'group' => 'general',
            'name' => 'site_name',
            'payload' => json_encode('My Site'),
        ]);

        $this->artisan('fulcrum:migrate-spatie')
            ->expectsOutputToContain('Defaulting to [spatie_settings] table as [settings] table was not found or already migrated.')
            ->expectsOutputToContain('Successfully migrated 1 settings.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('settings', ['key' => 'general.site_name']);
    }

    public function test_it_handles_empty_spatie_table()
    {
        $this->artisan('fulcrum:migrate-spatie', ['--table' => 'spatie_settings'])
            ->expectsOutputToContain('No settings found in Spatie table [spatie_settings].')
            ->assertExitCode(0);
    }

    public function test_it_infers_carbon_type()
    {
        DB::table('spatie_settings')->insert([
            'group' => 'general',
            'name' => 'created_at',
            'payload' => json_encode('2024-01-15 12:00:00'),
        ]);

        $this->artisan('fulcrum:migrate-spatie', ['--table' => 'spatie_settings'])
            ->assertExitCode(0);

        $this->assertDatabaseHas('settings', [
            'key' => 'general.created_at',
            'type' => SettingType::CARBON->value,
        ]);
    }

    public function test_it_infers_float_type()
    {
        DB::table('spatie_settings')->insert([
            'group' => 'general',
            'name' => 'tax_rate',
            'payload' => json_encode(12.5),
        ]);

        $this->artisan('fulcrum:migrate-spatie', ['--table' => 'spatie_settings'])
            ->assertExitCode(0);

        $this->assertDatabaseHas('settings', [
            'key' => 'general.tax_rate',
            'type' => SettingType::FLOAT->value,
        ]);
    }

    public function test_it_falls_back_to_string_for_invalid_date_format()
    {
        DB::table('spatie_settings')->insert([
            'group' => 'general',
            'name' => 'not_a_date',
            'payload' => json_encode('2024-01-15'), // contains - but no :
        ]);

        $this->artisan('fulcrum:migrate-spatie', ['--table' => 'spatie_settings'])
            ->assertExitCode(0);

        $this->assertDatabaseHas('settings', [
            'key' => 'general.not_a_date',
            'type' => SettingType::STRING->value,
        ]);
    }

    public function test_it_falls_back_to_string_for_other_types()
    {
        // Spatie stores payload as JSON. If we have something that is not bool, int, float, string, or array.
        // In PHP terms, after json_decode, it could be null.

        DB::table('spatie_settings')->insert([
            'group' => 'general',
            'name' => 'null_value',
            'payload' => json_encode(null),
        ]);

        $this->artisan('fulcrum:migrate-spatie', ['--table' => 'spatie_settings'])
            ->assertExitCode(0);

        $this->assertDatabaseHas('settings', [
            'key' => 'general.null_value',
            'type' => SettingType::STRING->value,
        ]);
    }

    public function test_it_handles_exceptions_during_migration()
    {
        DB::table('spatie_settings')->insert([
            'group' => 'general',
            'name' => 'site_name',
            'payload' => json_encode('value'),
        ]);

        // Create a partial mock of the command to override getSettingModel
        $command = \Mockery::mock('GaiaTools\FulcrumSettings\Console\Commands\MigrateFromSpatieCommand[getSettingModel]')
            ->makePartial();
        $command->shouldAllowMockingProtectedMethods();

        $settingMock = \Mockery::mock(Setting::class);
        $settingMock->shouldReceive('updateOrCreate')->andThrow(new \Exception('Mocked exception'));

        $command->shouldReceive('getSettingModel')->andReturn($settingMock);

        // Register the mocked command in the artisan application
        $this->app['Illuminate\Contracts\Console\Kernel']->registerCommand($command);

        $this->artisan('fulcrum:migrate-spatie', ['--table' => 'spatie_settings'])
            ->expectsOutputToContain('Failed to migrate setting [general.site_name]: Mocked exception')
            ->assertExitCode(0);
    }
}
