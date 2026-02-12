<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\Settings;

use GaiaTools\FulcrumSettings\Attributes\SettingProperty;
use Illuminate\Support\Collection;

final class SettingsSerializer
{
    public function __construct(
        private SettingsState $state,
        private SettingsLoader $loader
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $instance): array
    {
        $values = [];

        foreach ($this->state->propertyConfigs() as $property => $config) {
            $this->loader->ensurePropertyLoaded($instance, $property, $config);
            $values[$config->key] = $instance->{$property};
        }

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    public function onlyLoaded(object $instance): array
    {
        $values = [];
        $lazyLoaded = $this->state->lazyLoadedProperties();

        foreach ($this->state->propertyConfigs() as $property => $config) {
            if ($this->shouldSkipLazy($config, $lazyLoaded, $property)) {
                continue;
            }

            $values[$config->key] = $instance->{$property};
        }

        return $values;
    }

    /**
     * @return Collection<string, mixed>
     */
    public function toCollection(object $instance): Collection
    {
        return collect($this->toArray($instance));
    }

    /**
     * @param  array<int, string>  $lazyLoaded
     */
    private function shouldSkipLazy(SettingProperty $config, array $lazyLoaded, string $property): bool
    {
        return $config->lazy && ! in_array($property, $lazyLoaded, true);
    }
}
