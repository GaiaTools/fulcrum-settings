<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\Settings;

use GaiaTools\FulcrumSettings\Attributes\SettingProperty;
use GaiaTools\FulcrumSettings\Contracts\SettingResolver;
use GaiaTools\FulcrumSettings\Events\LoadingSettings;
use GaiaTools\FulcrumSettings\Events\SavingSettings;
use GaiaTools\FulcrumSettings\Events\SettingsLoaded;
use GaiaTools\FulcrumSettings\Events\SettingsSaved;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Collection;
use JsonSerializable;
use BadMethodCallException;

abstract class FulcrumSettings implements Arrayable, Jsonable, JsonSerializable
{
    /** @var array<string, SettingProperty> */
    protected array $propertyConfigs = [];

    /** @var array<string> */
    protected array $dirty = [];

    /** @var array<string> */
    protected array $lazyLoaded = [];

    protected ?Authenticatable $contextUser = null;

    protected ?string $tenantId = null;

    protected mixed $customContext = null;

    protected ?string $timezone = null;

    public function __construct(
        protected SettingResolver $resolver,
        protected SettingsPropertyDiscoverer $discoverer,
        protected SettingsHydrator $hydrator,
        protected SettingsPersister $persister
    ) {
        $this->propertyConfigs = $this->discoverer->discover(static::class);
        $this->load();
    }

    protected function load(): void
    {
        event(new LoadingSettings);

        if ($this->timezone) {
            app()->instance('fulcrum.context.timezone', $this->timezone);
        }

        try {
            $resolved = [];

            foreach ($this->propertyConfigs as $property => $config) {
                if ($config->lazy) {
                    continue;
                }

                $value = $this->hydrateProperty($property, $config);
                $resolved[$config->key] = $value;
            }

            event(new SettingsLoaded($resolved));
        } finally {
            if ($this->timezone) {
                app()->forgetInstance('fulcrum.context.timezone');
            }
        }
    }

    protected function hydrateProperty(string $property, SettingProperty $config): mixed
    {
        $resolver = $this->configuredResolver($config);
        $value = $this->hydrator->hydrate($this, $property, $config, $resolver, $this->context());
        $this->{$property} = $value;

        return $value;
    }

    protected function configuredResolver(SettingProperty $config): SettingResolver
    {
        $resolver = $this->resolver;

        if ($this->contextUser) {
            $resolver = $resolver->forUser($this->contextUser);
        }

        if ($config->tenantScoped && $this->tenantId) {
            $resolver = $resolver->forTenant($this->tenantId);
        }

        return $resolver;
    }

    protected function context(): mixed
    {
        return $this->customContext ?? $this->contextUser ?? auth()->user();
    }

    public function save(): void
    {
        $toSave = $this->collectSavableProperties();

        if (empty($toSave)) {
            return;
        }

        $data = $this->collectPropertyValues($toSave);
        $this->persister->validateWithRules($data, $toSave);

        event(new SavingSettings($data));

        foreach ($toSave as $property => $config) {
            $this->persister->persist($config->key, $this->{$property});
        }

        $this->dirty = [];
        event(new SettingsSaved($data));

        $this->load();
    }

    /**
     * @return array<string, SettingProperty>
     */
    protected function collectSavableProperties(): array
    {
        $toSave = [];

        foreach ($this->dirty as $property) {
            $config = $this->propertyConfigs[$property] ?? null;

            if ($config && ! $config->readOnly) {
                $toSave[$property] = $config;
            }
        }

        return $toSave;
    }

    /**
     * @param  array<string, SettingProperty>  $properties
     * @return array<string, mixed>
     */
    protected function collectPropertyValues(array $properties): array
    {
        $values = [];

        foreach ($properties as $property => $config) {
            $values[$config->key] = $this->{$property};
        }

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $values = [];

        foreach ($this->propertyConfigs as $property => $config) {
            $this->ensurePropertyLoaded($property, $config);
            $values[$config->key] = $this->{$property};
        }

        return $values;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options | JSON_THROW_ON_ERROR);
    }

    public function toCollection(): Collection
    {
        return collect($this->toArray());
    }

    public function __get(string $name): mixed
    {
        $config = $this->propertyConfigs[$name] ?? null;

        if (! $config) {
            return null;
        }

        $this->ensurePropertyLoaded($name, $config);

        return $this->{$name} ?? null;
    }

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
        $config = $this->propertyConfigs[$name] ?? null;

        if ($config?->readOnly || ! property_exists($this, $name)) {
            return;
        }

        $this->{$name} = $value;

        if ($config && ! in_array($name, $this->dirty, true)) {
            $this->dirty[] = $name;
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

    public function withContext(mixed $context): static
    {
        return $this->cloneWith(customContext: $context);
    }

    public function setTimezone(?string $timezone): static
    {
        return $this->cloneWith(timezone: $timezone);
    }

    protected function cloneWith(
        ?Authenticatable $contextUser = null,
        ?string $tenantId = null,
        mixed $customContext = null,
        ?string $timezone = null
    ): static {
        $clone = clone $this;
        $clone->contextUser = $contextUser ?? $this->contextUser;
        $clone->tenantId = $tenantId ?? $this->tenantId;
        $clone->customContext = $customContext ?? $this->customContext;
        $clone->timezone = $timezone ?? $this->timezone;
        $clone->dirty = [];
        $clone->lazyLoaded = [];
        $clone->load();

        return $clone;
    }

    public function refresh(): void
    {
        $this->dirty = [];
        $this->lazyLoaded = [];
        $this->load();
    }

    public function isDirty(?string $property = null): bool
    {
        return $property === null
            ? ! empty($this->dirty)
            : in_array($property, $this->dirty, true);
    }

    protected function ensurePropertyLoaded(string $property, SettingProperty $config): void
    {
        if (! $config->lazy || in_array($property, $this->lazyLoaded, true)) {
            return;
        }

        if ($this->timezone) {
            app()->instance('fulcrum.context.timezone', $this->timezone);
        }

        try {
            $this->hydrateProperty($property, $config);
            $this->lazyLoaded[] = $property;
        } finally {
            if ($this->timezone) {
                app()->forgetInstance('fulcrum.context.timezone');
            }
        }
    }
}
