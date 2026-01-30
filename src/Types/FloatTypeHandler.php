<?php

namespace GaiaTools\FulcrumSettings\Types;

use GaiaTools\FulcrumSettings\Contracts\SettingTypeHandler;
use GaiaTools\FulcrumSettings\Enums\SettingType;

class FloatTypeHandler implements SettingTypeHandler
{
    public function get(mixed $value): float
    {
        if (is_float($value)) {
            return $value;
        }

        if (is_int($value)) {
            return (float) $value;
        }

        if (is_bool($value)) {
            return $value ? 1.0 : 0.0;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return 0.0;
    }

    public function set(mixed $value): string
    {
        return (string) $this->get($value);
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
