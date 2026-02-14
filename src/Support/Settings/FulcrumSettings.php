<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\Settings;

use GaiaTools\FulcrumSettings\Attributes\SettingGroup;
use GaiaTools\FulcrumSettings\Contracts\SettingResolver;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
abstract class FulcrumSettings implements Arrayable, Jsonable, JsonSerializable
{
    use InterceptsAccess;
    use SerializesSettings;
    use SwitchesContext;

    protected SettingsState $state;

    protected SettingsContextState $contextState;

    protected SettingsLoader $loader;

    protected SettingsSaver $saver;

    protected SettingsSerializer $serializer;

    public function __construct(
        protected SettingResolver $resolver,
        protected SettingsPropertyDiscoverer $discoverer,
        protected SettingsHydrator $hydrator,
        protected SettingsPersister $persister
    ) {
        $this->state = new SettingsState;
        $this->contextState = new SettingsContextState($this->resolver, group: $this->resolveGroup());
        $this->loader = new SettingsLoader($this->state, $this->contextState, $this->discoverer, $this->hydrator);
        $this->saver = new SettingsSaver($this->state, $this->persister);
        $this->serializer = new SettingsSerializer($this->state, $this->loader);

        $this->loader->discover(static::class);
        $this->loader->bootLoad($this);
    }

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

    private function resolveGroup(): ?string
    {
        $attributes = (new \ReflectionClass($this))->getAttributes(SettingGroup::class);
        if (empty($attributes)) {
            return null;
        }

        $group = trim($attributes[0]->newInstance()->group);

        return $group !== '' ? $group : null;
    }
}
