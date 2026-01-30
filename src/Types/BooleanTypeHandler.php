<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Types;

use GaiaTools\FulcrumSettings\Contracts\SettingTypeHandler;

class BooleanTypeHandler implements SettingTypeHandler
{
    /**
     * Convert stored value to PHP boolean.
     */
    public function get(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_float($value)) {
            return $value !== 0.0;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    /**
     * Convert PHP value to storage format.
     */
    public function set(mixed $value): string
    {
        return $this->get($value) ? '1' : '0';
    }

    /**
     * Validate that the value can be cast to boolean.
     */
    public function validate(mixed $value): bool
    {
        return ! is_array($value) && ! is_object($value);
    }

    /**
     * Database column type for this setting type.
     */
    public function getDatabaseType(): string
    {
        return 'string';
    }
}
