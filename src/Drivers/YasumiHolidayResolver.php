<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Drivers;

use Carbon\Carbon;
use GaiaTools\FulcrumSettings\Contracts\HolidayResolver;
use Yasumi\ProviderInterface;
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
        if ($provider === null) {
            return false;
        }

        $locale = $this->resolveLocale();
        $holidayProvider = $this->createHolidayProvider($provider, $date, $locale);

        return $holidayProvider ? $holidayProvider->isHoliday($date) : false;
    }

    /**
     * @param  array<int|string, string>|string|null  $region
     */
    protected function resolveRegion(string|array|null $region): ?string
    {
        return match (true) {
            is_array($region) => $this->resolveRegionFromArray($region),
            is_string($region) => $region,
            default => $this->resolveDefaultRegion(),
        };
    }

    protected function resolveProvider(?string $region): ?string
    {
        if ($region === null || $region === '') {
            return null;
        }

        $regionKey = strtoupper($region);
        $mapped = config("fulcrum.holidays.providers.{$regionKey}");

        return is_string($mapped) && $mapped !== '' ? $mapped : $region;
    }

    protected function resolveLocale(): string
    {
        $localeConfig = config('fulcrum.holidays.locale', 'en_US');

        return is_string($localeConfig) ? $localeConfig : 'en_US';
    }

    /**
     * @param  array<int|string, string>  $region
     */
    protected function resolveRegionFromArray(array $region): ?string
    {
        $first = $region[0] ?? null;

        return is_string($first) ? $first : null;
    }

    protected function resolveDefaultRegion(): ?string
    {
        $defaultRegion = config('fulcrum.holidays.default_region');

        return is_string($defaultRegion) ? $defaultRegion : null;
    }

    protected function createHolidayProvider(string $provider, Carbon $date, string $locale): ?ProviderInterface
    {
        $instance = null;

        try {
            $instance = Yasumi::create($provider, (int) $date->format('Y'), $locale);
        } catch (\Throwable) {
            $instance = null;
        }

        return $instance;
    }
}
