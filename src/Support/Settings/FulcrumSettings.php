<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\Settings;

use BadMethodCallException;
use GaiaTools\FulcrumSettings\Attributes\SettingGroup;
use GaiaTools\FulcrumSettings\Contracts\SettingResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Collection;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
abstract class FulcrumSettings implements Arrayable, Jsonable, JsonSerializable
{
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
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->serializer->toArray($this);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options | JSON_THROW_ON_ERROR);
    }

    /**
     * @return Collection<string, mixed>
     */
    public function toCollection(): Collection
    {
        return $this->serializer->toCollection($this);
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

    /**
     * Return only settings already hydrated without triggering lazy loads.
     *
     * @return array<string, mixed>
     */
    public function onlyLoaded(): array
    {
        return $this->serializer->onlyLoaded($this);
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

    public function forUser(?Authenticatable $user): static
    {
        return $this->cloneWith(contextUser: $user);
    }

    public function forTenant(?string $tenantId): static
    {
        return $this->cloneWith(tenantId: $tenantId);
    }

    public function forGroup(?string $group): static
    {
        return $this->cloneWith(group: $group);
    }

    public function withContext(mixed $context): static
    {
        return $this->cloneWith(customContext: $context);
    }

    public function setTimezone(?string $timezone): static
    {
        return $this->cloneWith(timezone: $timezone);
    }

    private function cloneWith(
        ?Authenticatable $contextUser = null,
        ?string $tenantId = null,
        mixed $customContext = null,
        ?string $timezone = null,
        ?string $group = null
    ): static {
        $clone = clone $this;

        $clone->state = new SettingsState;
        $clone->state->setPropertyConfigs($this->state->propertyConfigs());

        $clone->contextState = $this->contextState->cloneWith(
            $contextUser,
            $tenantId,
            $customContext,
            $timezone,
            $group
        );

        $clone->loader = new SettingsLoader($clone->state, $clone->contextState, $clone->discoverer, $clone->hydrator);
        $clone->saver = new SettingsSaver($clone->state, $clone->persister);
        $clone->serializer = new SettingsSerializer($clone->state, $clone->loader);

        $clone->loader->bootLoad($clone);

        return $clone;
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
