<?php

namespace GaiaTools\FulcrumSettings\Types;

use GaiaTools\FulcrumSettings\Contracts\SettingTypeHandler;
use GaiaTools\FulcrumSettings\Enums\SettingType;

class StringTypeHandler implements SettingTypeHandler
{
    public function get(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return '';
    }

    public function set(mixed $value): string
    {
        return (string) $this->get($value);
    }

    public function validate(mixed $value): bool
    {
        return is_string($value);
    }

    public function getDatabaseType(): string
    {
        return SettingType::STRING->value;
    }
}
