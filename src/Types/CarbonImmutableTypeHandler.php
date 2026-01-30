<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Types;

use Carbon\CarbonImmutable;
use GaiaTools\FulcrumSettings\Contracts\SettingTypeHandler;
use Illuminate\Support\Facades\Config;

class CarbonImmutableTypeHandler implements SettingTypeHandler
{
    public function get(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $carbon = CarbonImmutable::parse($value);

        $timezone = $this->getTimezone();
        if ($timezone) {
            $carbon = $carbon->setTimezone($timezone);
        }

        return $carbon;
    }

    protected function getTimezone(): ?string
    {
        // Check if there is a context-specific timezone set
        if (app()->bound('fulcrum.context.timezone')) {
            return app('fulcrum.context.timezone');
        }

        return Config::get('fulcrum.carbon.output_timezone');
    }

    public function set(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! $value instanceof CarbonImmutable) {
            $value = CarbonImmutable::parse($value);
        }

        if (Config::get('fulcrum.carbon.store_utc', true)) {
            return $value->utc()->toIso8601String();
        }

        return $value->toIso8601String();
    }

    public function validate(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if ($value instanceof CarbonImmutable) {
            return true;
        }

        if (! is_string($value)) {
            return false;
        }

        try {
            CarbonImmutable::parse($value);

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    public function getDatabaseType(): string
    {
        return 'carbon_immutable';
    }
}
