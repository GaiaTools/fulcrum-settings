<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\Settings;

use BadMethodCallException;
use Illuminate\Support\Collection;

/**
 * @property SettingsState $state
 * @property SettingsLoader $loader
 */
trait InterceptsAccess
{
    /**
     * @return Collection<string, mixed>
     */
    abstract public function toCollection(): Collection;

    public function __get(string $name): mixed
    {
        $config = $this->state->propertyConfigs()[$name] ?? null;

        if (! $config) {
            return null;
        }

        $this->loader->ensurePropertyLoaded($this, $name, $config);

        return $this->{$name} ?? null;
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        $collection = $this->toCollection();

        if (method_exists($collection, $name)) {
            return $collection->{$name}(...$arguments);
        }

        throw new BadMethodCallException(sprintf('Method %s::%s does not exist.', static::class, $name));
    }

    public function __set(string $name, mixed $value): void
    {
        $config = $this->state->propertyConfigs()[$name] ?? null;

        if ($config?->readOnly || ! property_exists($this, $name)) {
            return;
        }

        $this->{$name} = $value;

        if ($config) {
            $this->state->markDirty($name);
        }
    }
}
