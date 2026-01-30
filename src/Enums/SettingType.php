<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Enums;

enum SettingType: string
{
    case BOOLEAN = 'boolean';
    case INTEGER = 'integer';
    case FLOAT = 'float';
    case STRING = 'string';
    case ARRAY = 'array';
    case JSON = 'json';
    case CARBON = 'carbon';
}
