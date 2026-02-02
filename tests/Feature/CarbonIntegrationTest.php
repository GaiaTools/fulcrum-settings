<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Feature;

use Carbon\Carbon;
use GaiaTools\FulcrumSettings\Attributes\SettingProperty;
use GaiaTools\FulcrumSettings\Support\Settings\FulcrumSettings;
use GaiaTools\FulcrumSettings\Tests\TestCase;

class CarbonIntegrationSettings extends FulcrumSettings
{
    #[SettingProperty(key: 'maintenance_start', cast: 'carbon')]
    protected ?Carbon $maintenanceStart;

    #[SettingProperty(key: 'maintenance_end', cast: 'carbon')]
    protected ?Carbon $maintenanceEnd;
}

class CarbonIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure the config is set for testing
        config(['fulcrum.carbon.store_utc' => true]);

        // Create the settings in the database
        \GaiaTools\FulcrumSettings\Models\Setting::create([
            'key' => 'maintenance_start',
            'type' => 'carbon',
        ]);
        \GaiaTools\FulcrumSettings\Models\Setting::create([
            'key' => 'maintenance_end',
            'type' => 'carbon',
        ]);
    }

    public function test_it_stores_and_retrieves_carbon_instances()
    {
        $settings = app(CarbonIntegrationSettings::class);
        $date = Carbon::parse('2024-03-15 14:30:00', 'America/New_York');

        $settings->maintenanceStart = $date;
        $settings->save();

        // Refresh and check
        $settings->refresh();

        expect($settings->maintenanceStart)->toBeInstanceOf(Carbon::class);
        // Should be stored as UTC, retrieved as UTC (since output_timezone is null)
        expect($settings->maintenanceStart->timezoneName)->toBeIn(['UTC', '+00:00']);
        expect($settings->maintenanceStart->hour)->toBe(18); // 14:30 EDT is 18:30 UTC
    }

    public function test_it_applies_timezone_from_set_timezone()
    {
        $settings = app(CarbonIntegrationSettings::class);
        $date = Carbon::parse('2024-03-15 14:30:00', 'UTC');

        $settings->maintenanceStart = $date;
        $settings->save();

        // Retrieve with different timezone
        $londonSettings = $settings->setTimezone('Europe/London');

        expect($londonSettings->maintenanceStart->timezoneName)->toBe('Europe/London');
        expect($londonSettings->maintenanceStart->hour)->toBe(14); // 14:30 UTC is 14:30 GMT (London) in March

        $nySettings = $settings->setTimezone('America/New_York');
        expect($nySettings->maintenanceStart->timezoneName)->toBe('America/New_York');
        expect($nySettings->maintenanceStart->hour)->toBe(10); // 14:30 UTC is 10:30 EDT
    }

    public function test_it_handles_null_carbon_values()
    {
        $settings = app(CarbonIntegrationSettings::class);
        $settings->maintenanceStart = null;
        $settings->save();

        $settings->refresh();
        expect($settings->maintenanceStart)->toBeNull();
    }
}
