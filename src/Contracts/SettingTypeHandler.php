<?php

namespace GaiaTools\FulcrumSettings\Contracts;

interface SettingTypeHandler
{
    public function get(mixed $value): mixed;

    public function set(mixed $value): mixed;

    public function validate(mixed $value): bool;

    public function getDatabaseType(): string;
}
