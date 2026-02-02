<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Types;

use GaiaTools\FulcrumSettings\Contracts\SettingTypeHandler as SettingTypeHandlerContract;

abstract class SettingTypeHandler implements SettingTypeHandlerContract
{
    final public function encodeForStorage(mixed $value): string
    {
        $this->assertValidInput($value);

        return $this->encode($value);
    }

    final public function decodeFromStorage(mixed $value): mixed
    {
        return $this->decode($value);
    }

    final public function isValidInput(mixed $value): bool
    {
        return $this->validate($value);
    }

    protected function assertValidInput(mixed $value): void
    {
        if (! $this->validate($value)) {
            $type = static::class;
            $debugType = get_debug_type($value);

            throw new \InvalidArgumentException(
                "Invalid input [{$debugType}] for type handler [{$type}]."
            );
        }
    }

    public function get(mixed $value): mixed
    {
        return $this->decodeFromStorage($value);
    }

    public function set(mixed $value): string
    {
        return $this->encodeForStorage($value);
    }

    abstract protected function encode(mixed $value): string;

    abstract protected function decode(mixed $value): mixed;

    abstract public function validate(mixed $value): bool;

    abstract public function getDatabaseType(): string;
}
