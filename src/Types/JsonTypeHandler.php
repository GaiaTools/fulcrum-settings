<?php

namespace GaiaTools\FulcrumSettings\Types;

use GaiaTools\FulcrumSettings\Contracts\SettingTypeHandler;
use GaiaTools\FulcrumSettings\Enums\SettingType;

class JsonTypeHandler implements SettingTypeHandler
{
    public function get(mixed $value): mixed
    {
        return match (true) {
            is_array($value) || is_object($value) => $value,
            ! is_string($value) || $value === '' || $value === 'null' => $value === 'null' ? null : $value,
            default => $this->decodeJsonValue($value),
        };
    }

    public function set(mixed $value): string
    {
        $encoded = json_encode($value);

        return $encoded === false ? 'null' : $encoded;
    }

    public function validate(mixed $value): bool
    {
        return match (true) {
            is_array($value) || is_object($value) => true,
            is_string($value) => $this->isValidJson($value),
            default => false,
        };
    }

    protected function decodeJsonValue(string $value): mixed
    {
        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    protected function isValidJson(string $value): bool
    {
        json_decode($value);

        return json_last_error() === JSON_ERROR_NONE;
    }

    public function getDatabaseType(): string
    {
        return SettingType::JSON->value;
    }
}
