<?php

namespace GaiaTools\FulcrumSettings\Types;

use GaiaTools\FulcrumSettings\Contracts\SettingTypeHandler;
use GaiaTools\FulcrumSettings\Enums\SettingType;

class JsonTypeHandler implements SettingTypeHandler
{
    public function get(mixed $value): mixed
    {
        if (is_array($value) || is_object($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '' || $value === 'null') {
            return $value === 'null' ? null : $value;
        }

        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return $value;
    }

    public function set(mixed $value): string
    {
        $encoded = json_encode($value);

        return $encoded === false ? 'null' : $encoded;
    }

    public function validate(mixed $value): bool
    {
        if (is_array($value) || is_object($value)) {
            return true;
        }

        if (! is_string($value)) {
            return false;
        }

        json_decode($value);

        return json_last_error() === JSON_ERROR_NONE;
    }

    public function getDatabaseType(): string
    {
        return SettingType::JSON->value;
    }
}
