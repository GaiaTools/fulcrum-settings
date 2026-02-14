<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\Settings;

/**
 * @property SettingsState $state
 * @property SettingsLoader $loader
 */
trait AccessesSettingsByKey
{
    /**
     * Get a setting by its fully resolved key.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $property = $this->propertyForKey($key);

        if ($property === null) {
            return $default;
        }

        $config = $this->state->propertyConfigs()[$property];
        $this->loader->ensurePropertyLoaded($this, $property, $config);

        return $this->{$property};
    }

    /**
     * Return settings for the provided fully resolved keys.
     *
     * @param  array<int, string>|string  $keys
     * @return array<string, mixed>
     */
    public function only(array|string $keys): array
    {
        $values = [];

        foreach ($this->normalizeKeys($keys) as $key) {
            $property = $this->propertyForKey($key);

            if ($property === null) {
                continue;
            }

            $config = $this->state->propertyConfigs()[$property];
            $this->loader->ensurePropertyLoaded($this, $property, $config);
            $values[$config->key] = $this->{$property};
        }

        return $values;
    }

    /**
     * Return all settings except the provided fully resolved keys.
     *
     * @param  array<int, string>|string  $keys
     * @return array<string, mixed>
     */
    public function except(array|string $keys): array
    {
        $excluded = array_fill_keys($this->normalizeKeys($keys), true);
        $values = [];

        foreach ($this->state->propertyConfigs() as $property => $config) {
            if (isset($excluded[$config->key])) {
                continue;
            }

            $this->loader->ensurePropertyLoaded($this, $property, $config);
            $values[$config->key] = $this->{$property};
        }

        return $values;
    }

    /**
     * Determine whether a fully resolved key exists on the settings definition.
     */
    public function has(string $key): bool
    {
        return $this->propertyForKey($key) !== null;
    }

    /**
     * Return fully resolved settings keys.
     *
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_values(array_map(
            static fn ($config) => $config->key,
            $this->state->propertyConfigs()
        ));
    }

    /**
     * @param  array<int, string>|string  $keys
     * @return array<int, string>
     */
    private function normalizeKeys(array|string $keys): array
    {
        if (! is_array($keys)) {
            return [$keys];
        }

        return array_values($keys);
    }

    private function propertyForKey(string $key): ?string
    {
        foreach ($this->state->propertyConfigs() as $property => $config) {
            if ($config->key === $key) {
                return $property;
            }
        }

        return null;
    }
}
