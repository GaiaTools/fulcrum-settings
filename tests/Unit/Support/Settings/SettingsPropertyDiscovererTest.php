<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Attributes\SettingProperty;
use GaiaTools\FulcrumSettings\Support\Settings\SettingsPropertyDiscoverer;

test('discover finds properties with SettingProperty attribute', function () {
    $discoverer = new SettingsPropertyDiscoverer;

    $instance = new class
    {
        #[SettingProperty(key: 'site.name')]
        public string $siteName;

        #[SettingProperty(key: 'site.url')]
        public string $siteUrl;

        public string $notASetting;

        #[SettingProperty(key: 'private.setting')]
        private string $privateSetting;
    };

    $configs = $discoverer->discover(get_class($instance));

    expect($configs)->toHaveCount(3)
        ->and($configs)->toHaveKeys(['siteName', 'siteUrl', 'privateSetting'])
        ->and($configs['siteName']->key)->toBe('site.name')
        ->and($configs['siteUrl']->key)->toBe('site.url')
        ->and($configs['privateSetting']->key)->toBe('private.setting');
});
