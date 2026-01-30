<?php

namespace GaiaTools\FulcrumSettings\Types;

use GaiaTools\FulcrumSettings\Contracts\SettingTypeHandler;
use GaiaTools\FulcrumSettings\Enums\SettingType;

class IntegerTypeHandler implements SettingTypeHandler
{
    public function get(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }

    public function set(mixed $value): string
    {
        return (string) $this->get($value);
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
