<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Console\Commands\Concerns;

trait InteractsWithCommandOptions
{
    protected function getStringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) ? $value : null;
    }

    protected function getStringArgument(string $name): ?string
    {
        $value = $this->argument($name);

        return is_string($value) ? $value : null;
    }

    protected function getBoolOption(string $name): bool
    {
        $value = $this->option($name);

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            return $parsed ?? false;
        }

        return false;
    }

    protected function getIntOption(string $name, int $default = 0): int
    {
        $value = $this->option($name);

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }
}
