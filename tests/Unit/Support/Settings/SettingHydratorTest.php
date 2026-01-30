<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Attributes\SettingProperty;
use GaiaTools\FulcrumSettings\Contracts\SettingResolver;
use GaiaTools\FulcrumSettings\Support\Settings\SettingsHydrator;
use GaiaTools\FulcrumSettings\Support\TypeRegistry;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

uses(MockeryPHPUnitIntegration::class);

test('hydrate resolves and casts value', function () {
    $typeRegistry = app(TypeRegistry::class);
    $hydrator = new SettingsHydrator($typeRegistry);

    $resolver = Mockery::mock(SettingResolver::class);
    $resolver->shouldReceive('resolve')
        ->with('test.key', null)
        ->andReturn('123');

    $instance = new class
    {
        public int $count;
    };

    $config = new SettingProperty(key: 'test.key');

    $value = $hydrator->hydrate($instance, 'count', $config, $resolver, null);

    expect($value)->toBe(123);
});

test('castValue uses cast from config if provided', function () {
    $typeRegistry = app(TypeRegistry::class);
    $hydrator = new SettingsHydrator($typeRegistry);

    $instance = new class
    {
        public $count;
    };

    $config = new SettingProperty(key: 'test.key', cast: 'integer');

    $value = $hydrator->castValue($instance, 'count', $config, '456');

    expect($value)->toBe(456);
});
