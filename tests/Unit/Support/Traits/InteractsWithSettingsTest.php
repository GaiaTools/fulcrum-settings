<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Support\Builders\SettingBuilder;
use GaiaTools\FulcrumSettings\Support\Traits\InteractsWithSettings;

test('it can create a setting builder', function () {
    $class = new class
    {
        use InteractsWithSettings;

        public function testCreateSetting(string $key): SettingBuilder
        {
            return $this->createSetting($key);
        }
    };

    $builder = $class->testCreateSetting('foo');

    expect($builder)->toBeInstanceOf(SettingBuilder::class);
});
