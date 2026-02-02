<?php

namespace GaiaTools\FulcrumSettings\Types;

use GaiaTools\FulcrumSettings\Contracts\SettingTypeHandler;
use GaiaTools\FulcrumSettings\Enums\SettingType;

class FloatTypeHandler implements SettingTypeHandler
{
    public function get(mixed $value): float
    {
        return match (true) {
            is_float($value) => $value,
            is_int($value) => (float) $value,
            is_bool($value) => $value ? 1.0 : 0.0,
            is_string($value) && is_numeric($value) => (float) $value,
            default => 0.0,
        };
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
