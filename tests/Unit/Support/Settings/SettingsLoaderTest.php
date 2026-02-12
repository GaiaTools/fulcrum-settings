<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Attributes\SettingProperty;
use GaiaTools\FulcrumSettings\Contracts\SettingResolver;
use GaiaTools\FulcrumSettings\Events\LoadingSettings;
use GaiaTools\FulcrumSettings\Events\SettingsLoaded;
use GaiaTools\FulcrumSettings\Support\Settings\SettingsContextState;
use GaiaTools\FulcrumSettings\Support\Settings\SettingsHydrator;
use GaiaTools\FulcrumSettings\Support\Settings\SettingsLoader;
use GaiaTools\FulcrumSettings\Support\Settings\SettingsPropertyDiscoverer;
use GaiaTools\FulcrumSettings\Support\Settings\SettingsState;
use Illuminate\Support\Facades\Event;

class LoaderTestSettings
{
    protected ?string $name = null;

    protected ?string $lazyProp = null;

    protected ?string $tenantProp = null;
}

function getPropertyValue(object $instance, string $property): mixed
{
    $reader = function (string $property): mixed {
        return $this->{$property};
    };

    $bound = $reader->bindTo($instance, $instance);

    return $bound($property);
}

test('loader discovers property configs', function () {
    $state = new SettingsState;
    $resolver = Mockery::mock(SettingResolver::class);
    $contextState = new SettingsContextState($resolver);
    $discoverer = Mockery::mock(SettingsPropertyDiscoverer::class);
    $hydrator = Mockery::mock(SettingsHydrator::class);

    $configs = [
        'name' => new SettingProperty(key: 'test.name'),
    ];

    $discoverer->shouldReceive('discover')->once()->with(LoaderTestSettings::class)->andReturn($configs);

    $loader = new SettingsLoader($state, $contextState, $discoverer, $hydrator);
    $loader->discover(LoaderTestSettings::class);

    expect($state->propertyConfigs())->toBe($configs);
});

test('loader boot loads non-lazy properties and dispatches events', function () {
    Event::fake();

    $state = new SettingsState;
    $resolver = Mockery::mock(SettingResolver::class);
    $contextState = new SettingsContextState($resolver);
    $discoverer = Mockery::mock(SettingsPropertyDiscoverer::class);
    $hydrator = Mockery::mock(SettingsHydrator::class);

    $configs = [
        'name' => new SettingProperty(key: 'test.name'),
        'lazyProp' => new SettingProperty(key: 'test.lazy', lazy: true),
        'tenantProp' => new SettingProperty(key: 'test.tenant'),
    ];

    $state->setPropertyConfigs($configs);

    $hydrator->shouldReceive('hydrate')
        ->with(Mockery::any(), 'name', $configs['name'], Mockery::any(), Mockery::any())
        ->once()
        ->andReturn('Fulcrum');
    $hydrator->shouldReceive('hydrate')
        ->with(Mockery::any(), 'tenantProp', $configs['tenantProp'], Mockery::any(), Mockery::any())
        ->once()
        ->andReturn('TenantValue');

    $loader = new SettingsLoader($state, $contextState, $discoverer, $hydrator);
    $settings = new LoaderTestSettings;

    $loader->bootLoad($settings);

    expect(getPropertyValue($settings, 'name'))->toBe('Fulcrum');
    expect(getPropertyValue($settings, 'tenantProp'))->toBe('TenantValue');

    Event::assertDispatched(LoadingSettings::class);
    Event::assertDispatched(SettingsLoaded::class, function ($event) {
        return $event->settings['test.name'] === 'Fulcrum'
            && $event->settings['test.tenant'] === 'TenantValue';
    });
});

test('loader loads lazy properties and tracks lazy state', function () {
    $state = new SettingsState;
    $resolver = Mockery::mock(SettingResolver::class);
    $contextState = new SettingsContextState($resolver);
    $discoverer = Mockery::mock(SettingsPropertyDiscoverer::class);
    $hydrator = Mockery::mock(SettingsHydrator::class);

    $configs = [
        'lazyProp' => new SettingProperty(key: 'test.lazy', lazy: true),
    ];
    $state->setPropertyConfigs($configs);

    $hydrator->shouldReceive('hydrate')
        ->with(Mockery::any(), 'lazyProp', $configs['lazyProp'], Mockery::any(), Mockery::any())
        ->once()
        ->andReturn('LazyValue');

    $loader = new SettingsLoader($state, $contextState, $discoverer, $hydrator);
    $settings = new LoaderTestSettings;

    $loader->load($settings);

    expect(getPropertyValue($settings, 'lazyProp'))->toBe('LazyValue');
    expect($state->lazyLoadedProperties())->toBe(['lazyProp']);
});

test('loader reloads selected properties and clears dirty state', function () {
    $state = new SettingsState;
    $resolver = Mockery::mock(SettingResolver::class);
    $contextState = new SettingsContextState($resolver);
    $discoverer = Mockery::mock(SettingsPropertyDiscoverer::class);
    $hydrator = Mockery::mock(SettingsHydrator::class);

    $configs = [
        'name' => new SettingProperty(key: 'test.name'),
        'lazyProp' => new SettingProperty(key: 'test.lazy', lazy: true),
    ];
    $state->setPropertyConfigs($configs);
    $state->markDirty('name');
    $state->markDirty('lazyProp');

    $hydrator->shouldReceive('hydrate')
        ->with(Mockery::any(), 'name', $configs['name'], Mockery::any(), Mockery::any())
        ->once()
        ->andReturn('Reloaded');

    $loader = new SettingsLoader($state, $contextState, $discoverer, $hydrator);
    $settings = new LoaderTestSettings;

    $loader->reload($settings, ['test.name']);

    expect(getPropertyValue($settings, 'name'))->toBe('Reloaded');
    expect($state->isDirty('name'))->toBeFalse();
    expect($state->isDirty('lazyProp'))->toBeTrue();
});

test('loader ensures lazy properties are hydrated once', function () {
    $state = new SettingsState;
    $resolver = Mockery::mock(SettingResolver::class);
    $contextState = new SettingsContextState($resolver);
    $discoverer = Mockery::mock(SettingsPropertyDiscoverer::class);
    $hydrator = Mockery::mock(SettingsHydrator::class);

    $configs = [
        'lazyProp' => new SettingProperty(key: 'test.lazy', lazy: true),
    ];
    $state->setPropertyConfigs($configs);

    $hydrator->shouldReceive('hydrate')
        ->with(Mockery::any(), 'lazyProp', $configs['lazyProp'], Mockery::any(), Mockery::any())
        ->once()
        ->andReturn('LazyValue');

    $loader = new SettingsLoader($state, $contextState, $discoverer, $hydrator);
    $settings = new LoaderTestSettings;

    $loader->ensurePropertyLoaded($settings, 'lazyProp', $configs['lazyProp']);
    $loader->ensurePropertyLoaded($settings, 'lazyProp', $configs['lazyProp']);

    expect($state->lazyLoadedProperties())->toBe(['lazyProp']);
});

test('loader resolves properties for keys', function () {
    $state = new SettingsState;
    $resolver = Mockery::mock(SettingResolver::class);
    $contextState = new SettingsContextState($resolver);
    $discoverer = Mockery::mock(SettingsPropertyDiscoverer::class);
    $hydrator = Mockery::mock(SettingsHydrator::class);

    $configs = [
        'name' => new SettingProperty(key: 'test.name'),
        'lazyProp' => new SettingProperty(key: 'test.lazy', lazy: true),
    ];
    $state->setPropertyConfigs($configs);

    $loader = new SettingsLoader($state, $contextState, $discoverer, $hydrator);

    $lazyOnly = $loader->resolvePropertiesForKeys(null, true);
    expect(array_keys($lazyOnly))->toBe(['lazyProp']);

    $byKeys = $loader->resolvePropertiesForKeys(['test.name', 'lazyProp'], false);
    expect(array_keys($byKeys))->toBe(['name', 'lazyProp']);
});
