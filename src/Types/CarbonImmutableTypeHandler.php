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
        $carbon = $this->resolveCarbon($value);
        $timezone = $this->getTimezone();

        return $this->applyTimezone($carbon, $timezone);
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
        $carbon = $this->resolveCarbon($value);
        if (! $carbon) {
            return null;
        }

        $storeUtc = (bool) Config::get('fulcrum.carbon.store_utc', true);

        return $storeUtc ? $carbon->utc()->toIso8601String() : $carbon->toIso8601String();
    }

    public function validate(mixed $value): bool
    {
        return match (true) {
            $value === null || $value === '' => true,
            $value instanceof CarbonImmutable => true,
            is_string($value) => $this->canParseCarbon($value),
            default => false,
        };
    }

    protected function resolveCarbon(mixed $value): ?CarbonImmutable
    {
        return match (true) {
            $value === null || $value === '' => null,
            $value instanceof \DateTimeInterface => CarbonImmutable::instance($value),
            is_string($value) || is_int($value) || is_float($value) => CarbonImmutable::parse($value),
            default => null,
        };
    }

    protected function applyTimezone(?CarbonImmutable $carbon, ?string $timezone): ?CarbonImmutable
    {
        if (! $carbon) {
            return null;
        }

        return $timezone ? $carbon->setTimezone($timezone) : $carbon;
    }

    protected function canParseCarbon(string $value): bool
    {
        $parsed = false;

        try {
            CarbonImmutable::parse($value);
            $parsed = true;
        } catch (\Exception) {
            $parsed = false;
        }

        return $parsed;
    }

    public function getDatabaseType(): string
    {
        return 'carbon_immutable';
    }
}
