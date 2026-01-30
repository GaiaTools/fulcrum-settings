<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Contracts;

use Carbon\Carbon;

interface HolidayResolver
{
    /**
     * Determine if the given date is a holiday in the provided region.
     */
    public function isHoliday(Carbon $date, string|array|null $region = null): bool;
}
