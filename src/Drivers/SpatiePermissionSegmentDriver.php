<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Drivers;

use GaiaTools\FulcrumSettings\Contracts\SegmentDriver;
use Illuminate\Contracts\Auth\Authenticatable;
use Spatie\Permission\Traits\HasRoles;

class SpatiePermissionSegmentDriver implements SegmentDriver
{
    public function isInSegment(Authenticatable $user, string $segment): bool
    {
        if (! $this->hasSpatiePermissions($user)) {
            return false;
        }

        /** @var Authenticatable&HasRoles $user */
        return $user->hasAnyRole($segment) || $user->hasAnyPermission($segment);
    }

    /**
     * @return array<int, string>
     */
    public function getUserSegments(Authenticatable $user): array
    {
        if (! $this->hasSpatiePermissions($user)) {
            return [];
        }

        /** @var Authenticatable&HasRoles $user */
        $roles = $user->getRoleNames()->toArray();
        $permissions = $user->getAllPermissions()->pluck('name')->toArray();

        return array_unique(array_merge($roles, $permissions));
    }

    private function hasSpatiePermissions(Authenticatable $user): bool
    {
        return method_exists($user, 'hasAnyRole')
            && method_exists($user, 'hasAnyPermission')
            && method_exists($user, 'getRoleNames')
            && method_exists($user, 'getAllPermissions');
    }
}
