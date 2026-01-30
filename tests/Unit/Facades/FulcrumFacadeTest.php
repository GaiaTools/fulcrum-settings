<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Contracts\SettingResolver;
use GaiaTools\FulcrumSettings\Facades\Fulcrum;

it('returns the correct facade accessor', function () {
    $facade = new class extends Fulcrum
    {
        public static function getAccessor(): string
        {
            return parent::getFacadeAccessor();
        }
    };

    expect($facade::getAccessor())->toBe(SettingResolver::class);
});
