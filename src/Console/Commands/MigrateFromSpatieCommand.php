<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Console\Commands;

use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateFromSpatieCommand extends Command
{
    protected $signature = 'fulcrum:migrate-spatie
                            {--table=settings : The Spatie settings table name}
                            {--connection= : The database connection where Spatie settings are located}
                            {--force : Force the migration even if settings already exist}';

    protected $description = 'Migrate settings from Spatie Laravel Settings to Fulcrum';

    protected function getSettingModel(): Setting
    {
        return new Setting;
    }

    public function handle(): int
    {
        $spatieTable = $this->option('table');
        $connection = $this->option('connection') ?: config('database.default');

        if ($spatieTable === 'settings' && ! Schema::connection($connection)->hasColumn('settings', 'group') && Schema::connection($connection)->hasTable('spatie_settings')) {
            $spatieTable = 'spatie_settings';
            $this->info('Defaulting to [spatie_settings] table as [settings] table was not found or already migrated.');
        }

        if (! Schema::connection($connection)->hasTable($spatieTable)) {
            $this->error("Spatie settings table [{$spatieTable}] not found on connection [{$connection}].");

            return 1;
        }

        $spatieSettings = DB::connection($connection)->table($spatieTable)->get();

        if ($spatieSettings->isEmpty()) {
            $this->info("No settings found in Spatie table [{$spatieTable}].");

            return 0;
        }

        $this->info("Found {$spatieSettings->count()} settings to migrate.");

        FulcrumContext::force();

        $count = 0;
        foreach ($spatieSettings as $spatieSetting) {
            $key = "{$spatieSetting->group}.{$spatieSetting->name}";

            if (Setting::where('key', $key)->exists() && ! $this->option('force')) {
                $this->warn("Setting [{$key}] already exists in Fulcrum. Skipping.");

                continue;
            }

            try {
                $payload = json_decode($spatieSetting->payload, true);
                $type = $this->inferType($payload);

                $setting = $this->getSettingModel()->updateOrCreate(
                    ['key' => $key],
                    [
                        'type' => $type,
                        'immutable' => (bool) ($spatieSetting->locked ?? false),
                    ]
                );

                $setting->defaultValue()->updateOrCreate(
                    ['valuable_id' => $setting->id, 'valuable_type' => Setting::class],
                    ['value' => $payload]
                );

                $count++;
            } catch (\Throwable $e) {
                $this->error("Failed to migrate setting [{$key}]: {$e->getMessage()}");
            }
        }

        $this->info("Successfully migrated {$count} settings.");

        return 0;
    }

    protected function inferType(mixed $value): SettingType
    {
        if (is_bool($value)) {
            return SettingType::BOOLEAN;
        }

        if (is_int($value)) {
            return SettingType::INTEGER;
        }

        if (is_float($value)) {
            return SettingType::FLOAT;
        }

        if (is_string($value)) {
            // Check if it's a date
            try {
                Carbon::parse($value);
                // Simple heuristic: if it contains - and : it's likely a date string
                if (str_contains($value, '-') && str_contains($value, ':')) {
                    return SettingType::CARBON;
                }
            } catch (\Throwable) {
                // Not a date
            }

            return SettingType::STRING;
        }

        if (is_array($value)) {
            return SettingType::JSON;
        }

        return SettingType::STRING;
    }
}
