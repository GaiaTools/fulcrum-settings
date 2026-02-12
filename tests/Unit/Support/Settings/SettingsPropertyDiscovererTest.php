<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Attributes\SettingGroup;
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

test('discover applies class setting group to property keys', function () {
    $discoverer = new SettingsPropertyDiscoverer;

    $instance = new #[SettingGroup('general')] class
    {
        #[SettingProperty(key: 'site_name')]
        public string $siteName;

        #[SettingProperty(key: 'general.site_url')]
        public string $siteUrl;
    };

    $configs = $discoverer->discover(get_class($instance));

    expect($configs)->toHaveCount(2)
        ->and($configs['siteName']->key)->toBe('general.site_name')
        ->and($configs['siteUrl']->key)->toBe('general.site_url');
});

test('discover supports nested setting groups', function () {
    $discoverer = new SettingsPropertyDiscoverer;

    $instance = new #[SettingGroup('services', 'someService')] class
    {
        #[SettingProperty(key: 'access_token')]
        public string $accessToken;
    };

    $configs = $discoverer->discover(get_class($instance));

    expect($configs)->toHaveCount(1)
        ->and($configs['accessToken']->key)->toBe('services.someService.access_token');
});
