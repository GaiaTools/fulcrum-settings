<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Attributes\SettingProperty;
use GaiaTools\FulcrumSettings\Support\Settings\SettingsState;

test('settings state tracks property configs and dirty state', function () {
    $state = new SettingsState;
    $configs = [
        'name' => new SettingProperty(key: 'test.name'),
        'lazyProp' => new SettingProperty(key: 'test.lazy', lazy: true),
    ];

    $state->setPropertyConfigs($configs);

    expect($state->propertyConfigs())->toBe($configs);
    expect($state->isDirty())->toBeFalse();

    $state->markDirty('name');
    $state->markDirty('name');

    expect($state->isDirty())->toBeTrue();
    expect($state->isDirty('name'))->toBeTrue();
    expect($state->dirtyProperties())->toBe(['name']);

    $state->clearDirtyFor(['name']);
    expect($state->dirtyProperties())->toBe([]);

    $state->markDirty('lazyProp');
    $state->clearDirty();
    expect($state->dirtyProperties())->toBe([]);
});

test('settings state tracks lazy loaded properties', function () {
    $state = new SettingsState;

    $state->markLazyLoaded('lazyProp');
    $state->markLazyLoaded('lazyProp');

    expect($state->lazyLoadedProperties())->toBe(['lazyProp']);

    $state->clearLazyLoaded();
    expect($state->lazyLoadedProperties())->toBe([]);
});
