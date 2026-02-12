<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Attributes\SettingProperty;
use GaiaTools\FulcrumSettings\Contracts\SettingResolver;
use GaiaTools\FulcrumSettings\Support\Settings\SettingsContextState;
use GaiaTools\FulcrumSettings\Support\Settings\SettingsHydrator;
use GaiaTools\FulcrumSettings\Support\Settings\SettingsLoader;
use GaiaTools\FulcrumSettings\Support\Settings\SettingsPropertyDiscoverer;
use GaiaTools\FulcrumSettings\Support\Settings\SettingsSerializer;
use GaiaTools\FulcrumSettings\Support\Settings\SettingsState;

class SerializerTestSettings
{
    public string $name = 'Fulcrum';

    public string $lazyProp = 'LazyValue';
}

test('serializer builds arrays and collections using setting keys', function () {
    $state = new SettingsState;
    $resolver = Mockery::mock(SettingResolver::class);
    $contextState = new SettingsContextState($resolver);
    $discoverer = Mockery::mock(SettingsPropertyDiscoverer::class);
    $hydrator = Mockery::mock(SettingsHydrator::class);
    $loader = new SettingsLoader($state, $contextState, $discoverer, $hydrator);
    $serializer = new SettingsSerializer($state, $loader);

    $configs = [
        'name' => new SettingProperty(key: 'test.name'),
        'lazyProp' => new SettingProperty(key: 'test.lazy', lazy: true),
    ];
    $state->setPropertyConfigs($configs);

    $hydrator->shouldReceive('hydrate')
        ->with(Mockery::any(), 'lazyProp', $configs['lazyProp'], Mockery::any(), Mockery::any())
        ->once()
        ->andReturn('LazyValue');

    $settings = new SerializerTestSettings;
    $array = $serializer->toArray($settings);

    expect($array)->toBe([
        'test.name' => 'Fulcrum',
        'test.lazy' => 'LazyValue',
    ]);

    $collection = $serializer->toCollection($settings);
    expect($collection->all())->toBe($array);
});

test('serializer only returns loaded lazy settings', function () {
    $state = new SettingsState;
    $resolver = Mockery::mock(SettingResolver::class);
    $contextState = new SettingsContextState($resolver);
    $discoverer = Mockery::mock(SettingsPropertyDiscoverer::class);
    $hydrator = Mockery::mock(SettingsHydrator::class);
    $loader = new SettingsLoader($state, $contextState, $discoverer, $hydrator);
    $serializer = new SettingsSerializer($state, $loader);

    $configs = [
        'name' => new SettingProperty(key: 'test.name'),
        'lazyProp' => new SettingProperty(key: 'test.lazy', lazy: true),
    ];
    $state->setPropertyConfigs($configs);

    $settings = new SerializerTestSettings;
    $loaded = $serializer->onlyLoaded($settings);

    expect($loaded)->toBe([
        'test.name' => 'Fulcrum',
    ]);

    $state->markLazyLoaded('lazyProp');
    $loaded = $serializer->onlyLoaded($settings);

    expect($loaded)->toBe([
        'test.name' => 'Fulcrum',
        'test.lazy' => 'LazyValue',
    ]);
});
