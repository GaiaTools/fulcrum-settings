<?php

namespace GaiaTools\FulcrumSettings\Types;

use GaiaTools\FulcrumSettings\Contracts\SettingTypeHandler;
use GaiaTools\FulcrumSettings\Enums\SettingType;

class StringTypeHandler implements SettingTypeHandler
{
    public function get(mixed $value): string
    {
        return match (true) {
            is_string($value) => $value,
            is_scalar($value) => (string) $value,
            is_object($value) && method_exists($value, '__toString') => (string) $value,
            default => '',
        };
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
