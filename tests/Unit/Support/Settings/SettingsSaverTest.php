<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Attributes\SettingProperty;
use GaiaTools\FulcrumSettings\Support\Settings\SettingsPersister;
use GaiaTools\FulcrumSettings\Support\Settings\SettingsSaver;
use GaiaTools\FulcrumSettings\Support\Settings\SettingsState;

class SaverTestSettings
{
    public string $name = 'Dirty Name';

    public string $readOnlyProp = 'ReadOnly';
}

test('saver persists dirty writable properties and clears dirty state', function () {
    $state = new SettingsState;
    $persister = Mockery::mock(SettingsPersister::class);
    $saver = new SettingsSaver($state, $persister);

    $configs = [
        'name' => new SettingProperty(key: 'test.name'),
        'readOnlyProp' => new SettingProperty(key: 'test.readonly', readOnly: true),
    ];
    $state->setPropertyConfigs($configs);
    $state->markDirty('name');
    $state->markDirty('readOnlyProp');

    $persister->shouldReceive('validateWithRules')->once();
    $persister->shouldReceive('persist')->once()->with('test.name', 'Dirty Name');
    $persister->shouldNotReceive('persist')->with('test.readonly', Mockery::any());

    $settings = new SaverTestSettings;
    $saved = $saver->save($settings);

    expect($saved)->toBeTrue();
    expect($state->isDirty())->toBeFalse();
});

test('saver returns false when nothing is dirty', function () {
    $state = new SettingsState;
    $persister = Mockery::mock(SettingsPersister::class);
    $saver = new SettingsSaver($state, $persister);

    $state->setPropertyConfigs([
        'name' => new SettingProperty(key: 'test.name'),
    ]);

    $persister->shouldNotReceive('validateWithRules');
    $persister->shouldNotReceive('persist');

    $saved = $saver->save(new SaverTestSettings);

    expect($saved)->toBeFalse();
});
