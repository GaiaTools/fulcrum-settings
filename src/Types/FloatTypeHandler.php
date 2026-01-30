<?php

namespace GaiaTools\FulcrumSettings\Types;

use GaiaTools\FulcrumSettings\Contracts\SettingTypeHandler;
use GaiaTools\FulcrumSettings\Enums\SettingType;

class FloatTypeHandler implements SettingTypeHandler
{
    public function get(mixed $value): float
    {
        return (float) $value;
    }

    public function set(mixed $value): string
    {
        return (string) (float) $value;
    }

    public function validate(mixed $value): bool
    {
        return is_numeric($value);
    }

    public function getDatabaseType(): string
    {
        return SettingType::STRING->value;
    }
}
