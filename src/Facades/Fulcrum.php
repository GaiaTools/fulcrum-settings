<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Facades;

use GaiaTools\FulcrumSettings\Contracts\SettingResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed resolve(string $key, mixed $scope = null)
 * @method static bool isActive(string $key, mixed $scope = null)
 * @method static SettingResolver forUser(?Authenticatable $user)
 * @method static SettingResolver forTenant(?string $tenantId)
 * @method static mixed get(string $key, mixed $default = null, mixed $scope = null)
 * @method static void set(string $key, mixed $value)
 * @method static bool isMultiTenancyEnabled()
 *
 * @see SettingResolver
 */
class Fulcrum extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SettingResolver::class;
    }
}
