<?php

namespace GaiaTools\FulcrumSettings\Types;

use GaiaTools\FulcrumSettings\Contracts\SettingTypeHandler;
use GaiaTools\FulcrumSettings\Enums\SettingType;

class IntegerTypeHandler implements SettingTypeHandler
{
    public function get(mixed $value): mixed
    {
        return (int) $value;
    }

    public function set(mixed $value): string
    {
        return (string) (int) $value;
    }

    public function validate(mixed $value): bool
    {
        if (is_int($value)) {
            return true;
        }

        if (is_string($value)) {
            return preg_match('/^-?\d+$/', $value) === 1;
        }

        return false;
    }

    public function getDatabaseType(): string
    {
        return SettingType::INTEGER->value;
    }
}
