<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Drivers;

use GaiaTools\FulcrumSettings\Contracts\SegmentDriver;
use Illuminate\Contracts\Auth\Authenticatable;

class SpatiePermissionSegmentDriver implements SegmentDriver
{
    public function isInSegment(Authenticatable $user, string $segment): bool
    {
        if (! $this->hasSpatiePermissions($user)) {
            return false;
        }

        if (! is_callable([$user, 'hasAnyRole']) || ! is_callable([$user, 'hasAnyPermission'])) {
            return false;
        }

        $hasRoleCallable = \Closure::fromCallable([$user, 'hasAnyRole']);
        $hasPermissionCallable = \Closure::fromCallable([$user, 'hasAnyPermission']);
        $hasRole = (bool) $hasRoleCallable($segment);
        $hasPermission = (bool) $hasPermissionCallable($segment);

        return $hasRole || $hasPermission;
    }

    /**
     * @return array<int, string>
     */
    public function getUserSegments(Authenticatable $user): array
    {
        if (! $this->hasSpatiePermissions($user)) {
            return [];
        }

        if (! is_callable([$user, 'getRoleNames']) || ! is_callable([$user, 'getAllPermissions'])) {
            return [];
        }

        $getRoleNames = \Closure::fromCallable([$user, 'getRoleNames']);
        $getAllPermissions = \Closure::fromCallable([$user, 'getAllPermissions']);
        $roles = $getRoleNames();
        $permissions = $getAllPermissions();

        $rolesList = is_object($roles) && method_exists($roles, 'toArray') ? $roles->toArray() : (array) $roles;
        $permissionsList = is_object($permissions) && method_exists($permissions, 'pluck')
            ? $permissions->pluck('name')->toArray()
            : (array) $permissions;

        return array_unique(array_merge($rolesList, $permissionsList));
    }

    private function hasSpatiePermissions(Authenticatable $user): bool
    {
        return method_exists($user, 'hasAnyRole')
            && method_exists($user, 'hasAnyPermission')
            && method_exists($user, 'getRoleNames')
            && method_exists($user, 'getAllPermissions');
    }
}
