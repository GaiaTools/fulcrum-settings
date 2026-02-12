<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\Settings;

use GaiaTools\FulcrumSettings\Attributes\SettingProperty;
use GaiaTools\FulcrumSettings\Events\LoadingSettings;
use GaiaTools\FulcrumSettings\Events\SettingsLoaded;

final class SettingsLoader
{
    public function __construct(
        private SettingsState $state,
        private SettingsContextState $contextState,
        private SettingsPropertyDiscoverer $discoverer,
        private SettingsHydrator $hydrator
    ) {}

    /**
     * @param  class-string  $className
     */
    public function discover(string $className): void
    {
        $this->state->setPropertyConfigs($this->discoverer->discover($className));
    }

    public function bootLoad(object $instance): void
    {
        event(new LoadingSettings);

        $resolved = [];
        $configs = $this->state->propertyConfigs();

        $this->contextState->runWithTimezone(function () use ($instance, $configs, &$resolved) {
            foreach ($configs as $property => $config) {
                if ($config->lazy) {
                    continue;
                }

                $value = $this->hydrateProperty($instance, $property, $config);
                $resolved[$config->key] = $value;
            }

            event(new SettingsLoaded($resolved));
        });
    }

    public function hydrateProperty(object $instance, string $property, SettingProperty $config): mixed
    {
        $resolver = $this->contextState->configuredResolver($config);
        $value = $this->hydrator->hydrate($instance, $property, $config, $resolver, $this->contextState->context());
        $this->writeProperty($instance, $property, $value);

        return $value;
    }

    /**
     * @param  array<int, string>|null  $keys
     */
    public function load(object $instance, ?array $keys = null): void
    {
        $properties = $this->resolvePropertiesForKeys($keys, true);

        if (empty($properties)) {
            return;
        }

        $this->contextState->runWithTimezone(function () use ($instance, $properties) {
            foreach ($properties as $property => $config) {
                $this->hydrateProperty($instance, $property, $config);
                $this->state->markLazyLoaded($property);
            }
        });
    }

    /**
     * @param  array<int, string>|null  $keys
     */
    public function reload(object $instance, ?array $keys = null): void
    {
        $properties = $this->resolvePropertiesForKeys($keys, false);

        if (empty($properties)) {
            return;
        }

        $this->contextState->runWithTimezone(function () use ($instance, $properties) {
            foreach ($properties as $property => $config) {
                $this->hydrateProperty($instance, $property, $config);
                if ($config->lazy) {
                    $this->state->markLazyLoaded($property);
                }
            }
        });

        $this->state->clearDirtyFor(array_keys($properties));
    }

    public function ensurePropertyLoaded(object $instance, string $property, SettingProperty $config): void
    {
        if (! $config->lazy || in_array($property, $this->state->lazyLoadedProperties(), true)) {
            return;
        }

        $this->contextState->runWithTimezone(function () use ($instance, $property, $config) {
            $this->hydrateProperty($instance, $property, $config);
            $this->state->markLazyLoaded($property);
        });
    }

    /**
     * @param  array<int, string>|null  $keys
     * @return array<string, SettingProperty>
     */
    public function resolvePropertiesForKeys(?array $keys, bool $onlyLazy): array
    {
        $configs = $this->state->propertyConfigs();

        if ($keys === null) {
            return array_filter(
                $configs,
                fn (SettingProperty $config) => ! $onlyLazy || $config->lazy
            );
        }

        $byKey = [];
        foreach ($configs as $property => $config) {
            $byKey[$config->key] = $property;
        }

        $properties = [];
        foreach ($keys as $key) {
            $property = array_key_exists($key, $configs)
                ? $key
                : ($byKey[$key] ?? null);

            if (! $property) {
                continue;
            }

            $config = $configs[$property] ?? null;
            if (! $config || ($onlyLazy && ! $config->lazy)) {
                continue;
            }

            $properties[$property] = $config;
        }

        return $properties;
    }

    private function writeProperty(object $instance, string $property, mixed $value): void
    {
        // Bind to the settings instance so we can write protected properties directly.
        // This avoids triggering __set, which would mark values dirty and block read-only hydration.
        $setter = function (string $property, mixed $value): void {
            $this->{$property} = $value;
        };

        $bound = $setter->bindTo($instance, $instance);
        $bound($property, $value);
    }
}
