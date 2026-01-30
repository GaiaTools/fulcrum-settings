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

        if ($value instanceof \DateTimeInterface) {
            $carbon = CarbonImmutable::instance($value);
        } elseif (is_string($value) || is_int($value) || is_float($value)) {
            $carbon = CarbonImmutable::parse($value);
        } else {
            return null;
        }

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
            $timezone = app('fulcrum.context.timezone');

            return is_string($timezone) ? $timezone : null;
        }

        $timezone = Config::get('fulcrum.carbon.output_timezone');

        return is_string($timezone) ? $timezone : null;
    }

    public function set(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! $value instanceof CarbonImmutable) {
            if ($value instanceof \DateTimeInterface) {
                $value = CarbonImmutable::instance($value);
            } elseif (is_string($value) || is_int($value) || is_float($value)) {
                $value = CarbonImmutable::parse($value);
            } else {
                return null;
            }
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
