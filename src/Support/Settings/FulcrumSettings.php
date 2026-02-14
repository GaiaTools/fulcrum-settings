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
    use ManagesSettingsLifecycle;
    use AccessesSettingsByKey;
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

    public function __get(string $name): mixed
    {
        $config = $this->state->propertyConfigs()[$name] ?? null;

        if (! $config) {
            return null;
        }

        $this->loader->ensurePropertyLoaded($this, $name, $config);

        return $this->{$name} ?? null;
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
