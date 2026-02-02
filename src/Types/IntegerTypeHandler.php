<?php

namespace GaiaTools\FulcrumSettings\Types;

use GaiaTools\FulcrumSettings\Contracts\SettingTypeHandler;
use GaiaTools\FulcrumSettings\Enums\SettingType;

class IntegerTypeHandler implements SettingTypeHandler
{
    public function get(mixed $value): int
    {
        return match (true) {
            is_int($value) => $value,
            is_bool($value) => $value ? 1 : 0,
            is_float($value) => (int) $value,
            is_string($value) && is_numeric($value) => (int) $value,
            default => 0,
        };
    }

    public function set(mixed $value): string
    {
        return (string) $this->get($value);
    }

    public function validate(mixed $value): bool
    {
        return match (true) {
            is_int($value) => true,
            is_string($value) => preg_match('/^-?\d+$/', $value) === 1,
            default => false,
        };
    }

    public function getDatabaseType(): string
    {
        return SettingType::INTEGER->value;
    }
}
