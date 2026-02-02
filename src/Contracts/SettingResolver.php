<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface SettingResolver
{
    /**
     * Resolve a setting value by evaluating rules in priority order.
     * Returns the value from the first matching rule, or the default value if no rules match.
     */
    public function resolve(string $key, mixed $scope = null): mixed;

    /**
     * Check if a boolean setting is active (true).
     */
    public function isActive(string $key, mixed $scope = null): bool;

    /**
     * Set the authenticated user for segment-based rule evaluation.
     */
    public function forUser(?Authenticatable $user): static;

    /**
     * Set the tenant ID for tenant-scoped resolution.
     */
    public function forTenant(?string $tenantId): static;

    /**
     * Get a setting value.
     */
    public function get(string $key, mixed $default = null, mixed $scope = null): mixed;

    /**
     * Enable revealing masked values for the current request.
     * Note: This still requires the user to have the necessary permissions.
     */
    public function reveal(bool $reveal = true): static;

    /**
     * Set a setting value (default value).
     */
    public function set(string $key, mixed $value): void;

    /**
     * Check if multi-tenancy is enabled.
     */
    public function isMultiTenancyEnabled(): bool;
}
