<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Drivers;

use Carbon\Carbon;
use GaiaTools\FulcrumSettings\Contracts\HolidayResolver;
use Yasumi\Yasumi;

class YasumiHolidayResolver implements HolidayResolver
{
    public function isHoliday(Carbon $date, string|array|null $region = null): bool
    {
        if (! class_exists(Yasumi::class)) {
            return false;
        }

        $regionCode = $this->resolveRegion($region);
        $provider = $this->resolveProvider($regionCode);
        $localeConfig = config('fulcrum.holidays.locale', 'en_US');
        $locale = is_string($localeConfig) ? $localeConfig : 'en_US';

        if ($provider === null) {
            return false;
        }

        try {
            $holidayProvider = Yasumi::create($provider, (int) $date->format('Y'), $locale);
        } catch (\Throwable) {
            return false;
        }

        return $holidayProvider->isHoliday($date);
    }

    /**
     * @param  array<int|string, string>|string|null  $region
     */
    protected function resolveRegion(string|array|null $region): ?string
    {
        if (is_array($region)) {
            $first = $region[0] ?? null;

            return is_string($first) ? $first : null;
        }

        if (is_string($region)) {
            return $region;
        }

        $defaultRegion = config('fulcrum.holidays.default_region');

        return is_string($defaultRegion) ? $defaultRegion : null;
    }

    protected function resolveProvider(?string $region): ?string
    {
        if ($region === null || $region === '') {
            return null;
        }

        $regionKey = strtoupper($region);
        $mapped = config("fulcrum.holidays.providers.{$regionKey}");

        if (is_string($mapped) && $mapped !== '') {
            return $mapped;
        }

        return $region;
    }
}
