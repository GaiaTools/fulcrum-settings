<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\Settings;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * @property SettingsState $state
 * @property SettingsContextState $contextState
 * @property SettingsPropertyDiscoverer $discoverer
 * @property SettingsHydrator $hydrator
 * @property SettingsPersister $persister
 * @property SettingsLoader $loader
 * @property SettingsSaver $saver
 * @property SettingsSerializer $serializer
 */
trait SwitchesContext
{
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
}
