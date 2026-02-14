<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface GroupedSettingResolver
{
    /**
     * Resolve all settings within the group.
     *
     * @return array<string, mixed>
     */
    public function all(mixed $scope = null, bool $stripGroupPrefix = true): array;

    public function forUser(?Authenticatable $user): self;

    public function forTenant(?string $tenantId): self;

    public function forGroup(string $group): self;

    public function group(string $group): self;
}
