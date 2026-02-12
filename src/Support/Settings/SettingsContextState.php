<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\Settings;

use GaiaTools\FulcrumSettings\Attributes\SettingProperty;
use GaiaTools\FulcrumSettings\Contracts\SettingResolver;
use Illuminate\Contracts\Auth\Authenticatable;

final class SettingsContextState
{
    public function __construct(
        private SettingResolver $resolver,
        private ?Authenticatable $contextUser = null,
        private ?string $tenantId = null,
        private mixed $customContext = null,
        private ?string $timezone = null
    ) {}

    public function configuredResolver(SettingProperty $config): SettingResolver
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

    public function context(): mixed
    {
        return $this->customContext ?? $this->contextUser ?? auth()->user();
    }

    public function runWithTimezone(callable $callback): mixed
    {
        if (! $this->timezone) {
            return $callback();
        }

        app()->instance('fulcrum.context.timezone', $this->timezone);

        try {
            return $callback();
        } finally {
            app()->forgetInstance('fulcrum.context.timezone');
        }
    }

    public function cloneWith(
        ?Authenticatable $contextUser = null,
        ?string $tenantId = null,
        mixed $customContext = null,
        ?string $timezone = null
    ): self {
        return new self(
            $this->resolver,
            $contextUser ?? $this->contextUser,
            $tenantId ?? $this->tenantId,
            $customContext ?? $this->customContext,
            $timezone ?? $this->timezone
        );
    }
}
