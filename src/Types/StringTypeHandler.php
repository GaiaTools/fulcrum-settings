<?php

namespace GaiaTools\FulcrumSettings\Types;

use GaiaTools\FulcrumSettings\Contracts\SettingTypeHandler;
use GaiaTools\FulcrumSettings\Enums\SettingType;

class StringTypeHandler implements SettingTypeHandler
{
    public function get(mixed $value): mixed
    {
        return (string) $value;
    }

    public function set(mixed $value): string
    {
        return (string) $value;
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
