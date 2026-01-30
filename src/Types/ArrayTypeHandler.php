<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Types;

use GaiaTools\FulcrumSettings\Contracts\SettingTypeHandler;

class ArrayTypeHandler implements SettingTypeHandler
{
    public function get(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        return json_decode($value, true) ?? [];
    }

    public function set(mixed $value): string
    {
        return json_encode($value);
    }

    public function validate(mixed $value): bool
    {
        return is_array($value);
    }

    public function getDatabaseType(): string
    {
        return 'string';
    }
}
