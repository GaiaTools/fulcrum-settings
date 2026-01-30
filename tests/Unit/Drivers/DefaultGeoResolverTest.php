<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Unit\Drivers;

use GaiaTools\FulcrumSettings\Drivers\DefaultGeoResolver;
use Illuminate\Http\Request;

test('it resolves IP from string', function () {
    $resolver = new DefaultGeoResolver;
    $result = $resolver->resolve('1.2.3.4');

    expect($result)->toBe([
        'ip' => '1.2.3.4',
        'country' => null,
        'region' => null,
        'city' => null,
    ]);
});

test('it resolves IP from array', function () {
    $resolver = new DefaultGeoResolver;
    $result = $resolver->resolve(['ip' => '5.6.7.8']);

    expect($result)->toBe([
        'ip' => '5.6.7.8',
        'country' => null,
        'region' => null,
        'city' => null,
    ]);
});

test('it returns null IP from array without ip key', function () {
    $resolver = new DefaultGeoResolver;
    $result = $resolver->resolve(['foo' => 'bar']);

    expect($result['ip'])->toBeNull();
});

test('it resolves IP from Request object', function () {
    $request = Request::create('/', 'GET');
    $request->server->set('REMOTE_ADDR', '9.10.11.12');

    $resolver = new DefaultGeoResolver;
    $result = $resolver->resolve($request);

    expect($result['ip'])->toBe('9.10.11.12');
});

test('it resolves IP from Request facade when input is null', function () {
    $request = Request::create('/', 'GET');
    $request->server->set('REMOTE_ADDR', '13.14.15.16');
    $this->app->instance('request', $request);

    $resolver = new DefaultGeoResolver;
    $result = $resolver->resolve(null);

    expect($result['ip'])->toBe('13.14.15.16');
});

test('it returns null for geo fields by default', function () {
    $resolver = new DefaultGeoResolver;
    $result = $resolver->resolve('1.1.1.1');

    expect($result)->toHaveKeys(['ip', 'country', 'region', 'city'])
        ->and($result['country'])->toBeNull()
        ->and($result['region'])->toBeNull()
        ->and($result['city'])->toBeNull();
});
