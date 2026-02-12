<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Contracts\SettingResolver;
use GaiaTools\FulcrumSettings\Support\GroupedSettingResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Mockery;

test('grouped resolver supports forUser chaining', function () {
    $resolver = Mockery::mock(SettingResolver::class);
    $user = Mockery::mock(Authenticatable::class);
    $userResolver = Mockery::mock(SettingResolver::class);

    $resolver->shouldReceive('forUser')->with($user)->andReturn($userResolver);
    $userResolver->shouldReceive('getGroupKeys')->with('my_links')->andReturn(['my_links.twitter']);
    $userResolver->shouldReceive('resolve')->with('my_links.twitter', $user)->andReturn('https://twitter.com/example');

    $grouped = new GroupedSettingResolver($resolver, 'my_links');
    $values = $grouped->forUser($user)->all(scope: $user);

    expect($values)->toBe([
        'twitter' => 'https://twitter.com/example',
    ]);
});
