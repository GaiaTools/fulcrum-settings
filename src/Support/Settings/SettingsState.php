<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\Settings;

use GaiaTools\FulcrumSettings\Attributes\SettingProperty;

final class SettingsState
{
    /** @var array<string, SettingProperty> */
    private array $propertyConfigs = [];

    /** @var array<string> */
    private array $dirty = [];

    /** @var array<string> */
    private array $lazyLoaded = [];

    /**
     * @param  array<string, SettingProperty>  $configs
     */
    public function setPropertyConfigs(array $configs): void
    {
        $this->propertyConfigs = $configs;
    }

    /**
     * @return array<string, SettingProperty>
     */
    public function propertyConfigs(): array
    {
        return $this->propertyConfigs;
    }

    public function markDirty(string $property): void
    {
        if (! in_array($property, $this->dirty, true)) {
            $this->dirty[] = $property;
        }
    }

    public function isDirty(?string $property = null): bool
    {
        return $property === null
            ? ! empty($this->dirty)
            : in_array($property, $this->dirty, true);
    }

    /**
     * @return array<string>
     */
    public function dirtyProperties(): array
    {
        return $this->dirty;
    }

    public function clearDirty(): void
    {
        $this->dirty = [];
    }

    /**
     * @param  array<int, string>  $properties
     */
    public function clearDirtyFor(array $properties): void
    {
        if (empty($this->dirty)) {
            return;
        }

        $this->dirty = array_values(array_filter(
            $this->dirty,
            fn (string $property) => ! in_array($property, $properties, true)
        ));
    }

    public function markLazyLoaded(string $property): void
    {
        if (! in_array($property, $this->lazyLoaded, true)) {
            $this->lazyLoaded[] = $property;
        }
    }

    /**
     * @return array<string>
     */
    public function lazyLoadedProperties(): array
    {
        return $this->lazyLoaded;
    }

    public function clearLazyLoaded(): void
    {
        $this->lazyLoaded = [];
    }
}
