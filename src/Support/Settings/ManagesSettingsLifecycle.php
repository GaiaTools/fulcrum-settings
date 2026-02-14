<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\Settings;

/**
 * @property SettingsState $state
 * @property SettingsLoader $loader
 * @property SettingsSaver $saver
 */
trait ManagesSettingsLifecycle
{
    public function save(): void
    {
        if ($this->saver->save($this)) {
            $this->loader->bootLoad($this);
        }
    }

    /**
     * Hydrate lazy settings by key.
     *
     * @param  array<int, string>|null  $keys
     */
    public function load(?array $keys = null): static
    {
        $this->loader->load($this, $keys);

        return $this;
    }

    /**
     * Force re-hydration of settings by key.
     *
     * @param  array<int, string>|null  $keys
     */
    public function reload(?array $keys = null): static
    {
        $this->loader->reload($this, $keys);

        return $this;
    }

    public function refresh(): void
    {
        $this->state->clearDirty();
        $this->state->clearLazyLoaded();
        $this->loader->bootLoad($this);
    }

    public function isDirty(?string $property = null): bool
    {
        return $this->state->isDirty($property);
    }
}
