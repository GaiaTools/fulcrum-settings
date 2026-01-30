<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Unit;

use GaiaTools\FulcrumSettings\Drivers\SpatiePermissionSegmentDriver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Mockery;

class SpatieUser implements Authenticatable
{
    public function getAuthIdentifierName() {}

    public function getAuthIdentifier() {}

    public function getAuthPassword() {}

    public function getAuthPasswordName() {}

    public function getRememberToken() {}

    public function setRememberToken($value) {}

    public function getRememberTokenName() {}

    public function hasAnyRole($roles) {}

    public function hasAnyPermission($permissions) {}

    public function getRoleNames() {}

    public function getAllPermissions() {}
}

test('it returns false if user does not have spatie methods', function () {
    $user = Mockery::mock(Authenticatable::class);
    $driver = new SpatiePermissionSegmentDriver;

    expect($driver->isInSegment($user, 'admin'))->toBeFalse();
});

test('it checks for roles', function () {
    $user = Mockery::mock(SpatieUser::class);
    $user->shouldReceive('hasAnyRole')->with('admin')->andReturn(true);
    $user->shouldReceive('hasAnyPermission')->with('admin')->andReturn(false);

    $driver = new SpatiePermissionSegmentDriver;

    expect($driver->isInSegment($user, 'admin'))->toBeTrue();
});

test('it checks for permissions', function () {
    $user = Mockery::mock(SpatieUser::class);
    $user->shouldReceive('hasAnyRole')->with('edit-posts')->andReturn(false);
    $user->shouldReceive('hasAnyPermission')->with('edit-posts')->andReturn(true);

    $driver = new SpatiePermissionSegmentDriver;

    expect($driver->isInSegment($user, 'edit-posts'))->toBeTrue();
});

test('it returns all segments', function () {
    $user = Mockery::mock(SpatieUser::class);
    $user->shouldReceive('getRoleNames')->andReturn(new Collection(['admin', 'editor']));

    $permission1 = new \stdClass;
    $permission1->name = 'edit-posts';
    $permission2 = new \stdClass;
    $permission2->name = 'delete-posts';

    $user->shouldReceive('getAllPermissions')->andReturn(new Collection([$permission1, $permission2]));

    $driver = new SpatiePermissionSegmentDriver;
    $segments = $driver->getUserSegments($user);

    expect($segments)->toBeArray()
        ->and($segments)->toContain('admin')
        ->and($segments)->toContain('editor')
        ->and($segments)->toContain('edit-posts')
        ->and($segments)->toContain('delete-posts');
});

test('it returns empty array if user does not have spatie methods for segments', function () {
    $user = Mockery::mock(Authenticatable::class);
    $driver = new SpatiePermissionSegmentDriver;

    expect($driver->getUserSegments($user))->toBe([]);
});
