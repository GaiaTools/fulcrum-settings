<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Exceptions;

use GaiaTools\FulcrumSettings\Enums\SettingType;
use InvalidArgumentException;

class InvalidSettingValueException extends InvalidArgumentException
{
    public static function forType(string|SettingType $type, mixed $value): self
    {
        $typeStr = $type instanceof SettingType ? $type->value : $type;
        $valueType = get_debug_type($value);

        return new self(
            "Value of type [{$valueType}] is not valid for setting type [{$typeStr}]."
        );
    }

    public static function forSetting(string $key, string|SettingType $type, mixed $value): self
    {
        $typeStr = $type instanceof SettingType ? $type->value : $type;
        $valueType = get_debug_type($value);

        return new self(
            "Value of type [{$valueType}] is not valid for setting [{$key}] of type [{$typeStr}]."
        );
    }
}
