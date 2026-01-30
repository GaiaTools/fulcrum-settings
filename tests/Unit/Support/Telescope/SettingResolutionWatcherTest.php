<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Unit\Support\Telescope;

use GaiaTools\FulcrumSettings\Events\SettingResolved;
use GaiaTools\FulcrumSettings\Models\SettingRule;
use GaiaTools\FulcrumSettings\Support\MaskedValue;
use GaiaTools\FulcrumSettings\Support\Telescope\SettingResolutionWatcher;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeServiceProvider;

uses()->group('telescope');

beforeEach(function () {
    $this->app->register(TelescopeServiceProvider::class);
});

test('it registers event listener', function () {
    $watcher = new SettingResolutionWatcher([]);
    $watcher->register($this->app);

    expect($this->app['events']->hasListeners(SettingResolved::class))->toBeTrue();
});

test('it does not record when telescope is not recording', function () {
    Telescope::stopRecording();

    $event = new SettingResolved('foo', 'bar');

    $watcher = new SettingResolutionWatcher([]);
    $watcher->recordResolution($event);

    expect(Telescope::isRecording())->toBeFalse();
});

test('it records resolution when telescope is recording', function () {
    Telescope::startRecording();

    $event = new SettingResolved(
        key: 'site_name',
        value: 'My Awesome Site',
        source: 'database',
        durationMs: 1.23
    );

    $watcher = new SettingResolutionWatcher([]);

    $recordedEntry = null;
    Telescope::filter(function (IncomingEntry $entry) use (&$recordedEntry) {
        if ($entry->type === 'log' && str_contains($entry->content['message'], 'Fulcrum: site_name')) {
            $recordedEntry = $entry;
        }

        return true;
    });

    $watcher->recordResolution($event);

    expect($recordedEntry)->not->toBeNull()
        ->and($recordedEntry->content['level'])->toBe('debug')
        ->and($recordedEntry->content['message'])->toBe('Fulcrum: site_name → database')
        ->and($recordedEntry->content['context']['setting'])->toBe('site_name')
        ->and($recordedEntry->content['context']['value'])->toBe('My Awesome Site')
        ->and($recordedEntry->content['context']['source'])->toBe('database');
});

test('it masks values correctly', function () {
    Telescope::startRecording();

    $maskedValue = new MaskedValue('secret-api-key');
    $event = new SettingResolved('api_key', $maskedValue);

    $watcher = new SettingResolutionWatcher([]);

    $recordedEntry = null;
    Telescope::filter(function (IncomingEntry $entry) use (&$recordedEntry) {
        if ($entry->type === 'log' && str_contains($entry->content['message'], 'Fulcrum: api_key')) {
            $recordedEntry = $entry;
        }

        return true;
    });

    $watcher->recordResolution($event);

    expect($recordedEntry)->not->toBeNull()
        ->and($recordedEntry->content['context']['value'])->toBe('[MASKED]');
});

test('it formats source from rule', function () {
    Telescope::startRecording();

    $rule = new SettingRule(['name' => 'Premium Users']);
    $event = new SettingResolved(
        key: 'discount',
        value: 20,
        matchedRule: $rule
    );

    $watcher = new SettingResolutionWatcher([]);

    $recordedEntry = null;
    Telescope::filter(function (IncomingEntry $entry) use (&$recordedEntry) {
        if ($entry->type === 'log' && str_contains($entry->content['message'], 'Fulcrum: discount')) {
            $recordedEntry = $entry;
        }

        return true;
    });

    $watcher->recordResolution($event);

    expect($recordedEntry)->not->toBeNull()
        ->and($recordedEntry->content['message'])->toBe('Fulcrum: discount → rule:Premium Users')
        ->and($recordedEntry->content['context']['source'])->toBe('rule:Premium Users')
        ->and($recordedEntry->content['context']['matched_rule']['name'])->toBe('Premium Users');
});

test('it includes tenant info', function () {
    Telescope::startRecording();

    $event = new SettingResolved(
        key: 'theme',
        value: 'dark',
        tenantId: 'tenant-123'
    );

    $watcher = new SettingResolutionWatcher([]);

    $recordedEntry = null;
    Telescope::filter(function (IncomingEntry $entry) use (&$recordedEntry) {
        if ($entry->type === 'log' && str_contains($entry->content['message'], 'Fulcrum: theme')) {
            $recordedEntry = $entry;
        }

        return true;
    });

    $watcher->recordResolution($event);

    expect($recordedEntry)->not->toBeNull()
        ->and($recordedEntry->content['message'])->toBe('Fulcrum: theme → default [tenant:tenant-123]')
        ->and($recordedEntry->content['context']['tenant_id'])->toBe('tenant-123');
});

test('it includes and sanitizes scope when enabled', function () {
    Telescope::startRecording();

    $scope = [
        'user_id' => 1,
        'api_token' => 'sensitive-stuff',
        'email' => 'user@example.com',
    ];

    $event = new SettingResolved(
        key: 'feature_x',
        value: true,
        scope: $scope
    );

    $watcher = new SettingResolutionWatcher(['include_scope' => true]);

    $recordedEntry = null;
    Telescope::filter(function (IncomingEntry $entry) use (&$recordedEntry) {
        if ($entry->type === 'log' && str_contains($entry->content['message'], 'Fulcrum: feature_x')) {
            $recordedEntry = $entry;
        }

        return true;
    });

    $watcher->recordResolution($event);

    expect($recordedEntry)->not->toBeNull();
    $recordedScope = $recordedEntry->content['context']['scope'];
    expect($recordedScope['user_id'])->toBe(1)
        ->and($recordedScope['api_token'])->toBe('[REDACTED]')
        ->and($recordedScope['email'])->toBe('user@example.com');
});

test('it adds tags', function () {
    Telescope::startRecording();

    $event = new SettingResolved(
        key: 'welcome_message',
        value: 'Hello',
        tenantId: 'abc'
    );

    $watcher = new SettingResolutionWatcher([]);

    $recordedEntry = null;
    Telescope::filter(function (IncomingEntry $entry) use (&$recordedEntry) {
        if ($entry->type === 'log' && str_contains($entry->content['message'], 'Fulcrum: welcome_message')) {
            $recordedEntry = $entry;
        }

        return true;
    });

    $watcher->recordResolution($event);

    expect($recordedEntry)->not->toBeNull();
    $tags = $recordedEntry->tags;
    expect($tags)->toContain('fulcrum')
        ->and($tags)->toContain('setting:welcome_message')
        ->and($tags)->toContain('source:default')
        ->and($tags)->toContain('tenant:abc');
});

test('it formats objects correctly', function () {
    Telescope::startRecording();

    $obj = new class
    {
        public function toArray()
        {
            return ['foo' => 'bar'];
        }
    };
    $event = new SettingResolved('obj_setting', $obj);

    $watcher = new SettingResolutionWatcher([]);
    $recordedEntry = null;
    Telescope::filter(function (IncomingEntry $entry) use (&$recordedEntry) {
        if ($entry->type === 'log' && str_contains($entry->content['message'], 'Fulcrum: obj_setting')) {
            $recordedEntry = $entry;
        }

        return true;
    });

    $watcher->recordResolution($event);
    expect($recordedEntry->content['context']['value'])->toBe(['foo' => 'bar']);

    // Test __toString
    $obj2 = new class
    {
        public function __toString()
        {
            return 'stringified';
        }
    };
    $event2 = new SettingResolved('obj_setting_2', $obj2);
    Telescope::filter(function (IncomingEntry $entry) use (&$recordedEntry) {
        if ($entry->type === 'log' && str_contains($entry->content['message'], 'Fulcrum: obj_setting_2')) {
            $recordedEntry = $entry;
        }

        return true;
    });
    $watcher->recordResolution($event2);
    expect($recordedEntry->content['context']['value'])->toBe('stringified');

    // Test generic object
    $obj3 = new \stdClass;
    $event3 = new SettingResolved('obj_setting_3', $obj3);
    Telescope::filter(function (IncomingEntry $entry) use (&$recordedEntry) {
        if ($entry->type === 'log' && str_contains($entry->content['message'], 'Fulcrum: obj_setting_3')) {
            $recordedEntry = $entry;
        }

        return true;
    });
    $watcher->recordResolution($event3);
    expect($recordedEntry->content['context']['value'])->toBe('[Object: stdClass]');

    // Test array
    $arrayValue = ['a' => 1];
    $event4 = new SettingResolved('array_setting', $arrayValue);
    Telescope::filter(function (IncomingEntry $entry) use (&$recordedEntry) {
        if ($entry->type === 'log' && str_contains($entry->content['message'], 'Fulcrum: array_setting')) {
            $recordedEntry = $entry;
        }

        return true;
    });
    $watcher->recordResolution($event4);
    expect($recordedEntry->content['context']['value'])->toBe(['a' => 1]);
});

test('it includes user_id in context', function () {
    Telescope::startRecording();

    $event = new SettingResolved(
        key: 'theme',
        value: 'dark',
        userId: 999
    );

    $watcher = new SettingResolutionWatcher([]);

    $recordedEntry = null;
    Telescope::filter(function (IncomingEntry $entry) use (&$recordedEntry) {
        if ($entry->type === 'log' && str_contains($entry->content['message'], 'Fulcrum: theme')) {
            $recordedEntry = $entry;
        }

        return true;
    });

    $watcher->recordResolution($event);

    expect($recordedEntry)->not->toBeNull()
        ->and($recordedEntry->content['context']['user_id'])->toBe(999);
});

test('it uses custom sensitive keys for scope sanitization', function () {
    Telescope::startRecording();

    $scope = [
        'custom_key' => 'secret-value',
        'normal_key' => 'public-value',
    ];

    $event = new SettingResolved(
        key: 'feature_y',
        value: true,
        scope: $scope
    );

    $watcher = new SettingResolutionWatcher([
        'include_scope' => true,
        'sensitive_scope_keys' => ['custom_key'],
    ]);

    $recordedEntry = null;
    Telescope::filter(function (IncomingEntry $entry) use (&$recordedEntry) {
        if ($entry->type === 'log' && str_contains($entry->content['message'], 'Fulcrum: feature_y')) {
            $recordedEntry = $entry;
        }

        return true;
    });

    $watcher->recordResolution($event);

    expect($recordedEntry)->not->toBeNull();
    $recordedScope = $recordedEntry->content['context']['scope'];
    expect($recordedScope['custom_key'])->toBe('[REDACTED]')
        ->and($recordedScope['normal_key'])->toBe('public-value');
});
