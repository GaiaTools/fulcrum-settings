<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Types;

use GaiaTools\FulcrumSettings\Contracts\SettingTypeHandler;

class ArrayTypeHandler implements SettingTypeHandler
{
    /**
     * @return array<int|string, mixed>
     */
    public function get(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function set(mixed $value): string
    {
        $encoded = json_encode($value);

        return $encoded === false ? 'null' : $encoded;
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
