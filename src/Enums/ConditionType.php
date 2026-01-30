<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Enums;

enum ConditionType: string
{
    case USER = 'user';
    case GEOCODING = 'geocoding';
    case USERAGENT = 'user_agent';
    case DATE_TIME = 'date_time';

    public static function default(): string
    {
        return config()->string('fulcrum.condition_types_default', self::USER->value);
    }
}
