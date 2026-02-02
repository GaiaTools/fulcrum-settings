<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Drivers\PennantDriver;
use GaiaTools\FulcrumSettings\Enums\ComparisonOperator;
use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\Models\Setting;
use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;

uses(RefreshDatabase::class);

beforeEach(function () {
    if (! class_exists(Feature::class)) {
        $this->markTestSkipped('Laravel Pennant is not installed');
    }

    // Register Fulcrum driver for Pennant
    Feature::extend('fulcrum', fn () => app(PennantDriver::class));

    // Configure Pennant to use Fulcrum driver
    config([
        'pennant.default' => 'fulcrum',
        'pennant.stores.fulcrum' => ['driver' => 'fulcrum'],
    ]);
});

describe('Pennant Integration', function () {
    it('works with Feature::active() for boolean flags', function () {
        $setting = Setting::create([
            'key' => 'new-dashboard',
            'type' => SettingType::BOOLEAN,
        ]);
        $setting->defaultValue()->create([
            'valuable_type' => Setting::class,
            'valuable_id' => $setting->id,
            'value' => true,
        ]);

        expect(Feature::active('new-dashboard'))->toBe(true);
    });

    it('works with Feature::inactive() for boolean flags', function () {
        $setting = Setting::create([
            'key' => 'disabled-feature',
            'type' => SettingType::BOOLEAN,
        ]);
        $setting->defaultValue()->create([
            'valuable_type' => Setting::class,
            'valuable_id' => $setting->id,
            'value' => false,
        ]);

        expect(Feature::inactive('disabled-feature'))->toBe(true);
    });

    it('works with Feature::value() for any value type', function () {
        $setting = Setting::create([
            'key' => 'max-upload',
            'type' => SettingType::INTEGER,
        ]);
        $setting->defaultValue()->create([
            'valuable_type' => Setting::class,
            'valuable_id' => $setting->id,
            'value' => 100,
        ]);

        expect(Feature::value('max-upload'))->toBe(100);
    });

    it('evaluates rules with user scope', function () {
        $user = new User;
        $user->forceFill([
            'id' => 1,
            'name' => 'Test User',
            'email' => 'user@company.com',
        ]);

        $setting = Setting::create([
            'key' => 'beta-features',
            'type' => SettingType::BOOLEAN,
        ]);
        $setting->defaultValue()->create([
            'valuable_type' => Setting::class,
            'valuable_id' => $setting->id,
            'value' => false,
        ]);

        $rule = $setting->rules()->create(['priority' => 1]);
        $rule->conditions()->create([
            'attribute' => 'email',
            'operator' => ComparisonOperator::ENDS_WITH_ANY,
            'value' => ['@company.com'],
        ]);
        $rule->value()->create([
            'valuable_type' => \GaiaTools\FulcrumSettings\Models\SettingRule::class,
            'valuable_id' => $rule->id,
            'value' => true,
        ]);

        expect(Feature::for($user)->active('beta-features'))->toBe(true);
    });

    it('evaluates rules with custom scope', function () {
        $setting = Setting::create([
            'key' => 'version-feature',
            'type' => SettingType::BOOLEAN,
        ]);
        $setting->defaultValue()->create([
            'valuable_type' => Setting::class,
            'valuable_id' => $setting->id,
            'value' => false,
        ]);

        $rule = $setting->rules()->create(['priority' => 1]);
        $rule->conditions()->create([
            'attribute' => 'app_version',
            'operator' => ComparisonOperator::VERSION_GTE,
            'value' => '2.0.0',
        ]);
        $rule->value()->create([
            'valuable_type' => \GaiaTools\FulcrumSettings\Models\SettingRule::class,
            'valuable_id' => $rule->id,
            'value' => true,
        ]);

        $scope = new User;
        $scope->id = 1;
        $scope->forceFill(['app_version' => '2.1.0']);
        expect(Feature::for($scope)->active('version-feature'))->toBe(true);

        $scope = new User;
        $scope->id = 2;
        $scope->forceFill(['app_version' => '1.9.0']);
        expect(Feature::for($scope)->active('version-feature'))->toBe(false);
    });

    it('works with Feature::when() for conditional execution', function () {
        $setting = Setting::create([
            'key' => 'new-algorithm',
            'type' => SettingType::BOOLEAN,
        ]);
        $setting->defaultValue()->create([
            'valuable_type' => Setting::class,
            'valuable_id' => $setting->id,
            'value' => true,
        ]);

        $executed = false;

        Feature::when('new-algorithm', function () use (&$executed) {
            $executed = true;
        });

        expect($executed)->toBe(true);
    });

    it('works with Feature::unless() for inverse conditional execution', function () {
        $setting = Setting::create([
            'key' => 'disabled-feature',
            'type' => SettingType::BOOLEAN,
        ]);
        $setting->defaultValue()->create([
            'valuable_type' => Setting::class,
            'valuable_id' => $setting->id,
            'value' => false,
        ]);

        $executed = false;

        Feature::unless('disabled-feature', function () use (&$executed) {
            $executed = true;
        });

        expect($executed)->toBe(true);
    });

    it('handles multiple features with all()', function () {
        $setting1 = Setting::create(['key' => 'feature-1', 'type' => SettingType::BOOLEAN]);
        $setting1->defaultValue()->create([
            'valuable_type' => Setting::class,
            'valuable_id' => $setting1->id,
            'value' => true,
        ]);

        $setting2 = Setting::create(['key' => 'feature-2', 'type' => SettingType::BOOLEAN]);
        $setting2->defaultValue()->create([
            'valuable_type' => Setting::class,
            'valuable_id' => $setting2->id,
            'value' => false,
        ]);

        $setting3 = Setting::create(['key' => 'feature-3', 'type' => SettingType::STRING]);
        $setting3->defaultValue()->create([
            'valuable_type' => Setting::class,
            'valuable_id' => $setting3->id,
            'value' => 'foo',
        ]);

        $all = Feature::all();

        expect($all)
            ->toHaveKey('feature-1', true)
            ->toHaveKey('feature-2', false)
            ->toHaveKey('feature-3', 'foo');
    });

    it('provides default value for non-existent feature', function () {
        expect(Feature::value('non-existent'))->toBeNull();
    });

    it('works with JSON configuration values', function () {
        $configValue = ['foo' => 'bar'];

        $setting = Setting::create([
            'key' => 'json-config',
            'type' => SettingType::JSON,
        ]);
        $setting->defaultValue()->create([
            'valuable_type' => Setting::class,
            'valuable_id' => $setting->id,
            'value' => $configValue,
        ]);

        $config = Feature::value('json-config');

        expect($config)->toBe($configValue);
    });

    it('evaluates multiple rules by priority', function () {
        $setting = Setting::create([
            'key' => 'tiered-feature',
            'type' => SettingType::STRING,
        ]);
        $setting->defaultValue()->create([
            'valuable_type' => Setting::class,
            'valuable_id' => $setting->id,
            'value' => 'basic',
        ]);

        // Priority 1: Premium users
        $rule1 = $setting->rules()->create(['priority' => 1]);
        $rule1->conditions()->create([
            'attribute' => 'scope',
            'operator' => ComparisonOperator::EQUALS,
            'value' => 'premium',
        ]);
        $rule1->value()->create([
            'valuable_type' => \GaiaTools\FulcrumSettings\Models\SettingRule::class,
            'valuable_id' => $rule1->id,
            'value' => 'premium',
        ]);

        $rule2 = $setting->rules()->create(['priority' => 2]);
        $rule2->conditions()->create([
            'attribute' => 'scope',
            'operator' => ComparisonOperator::EQUALS,
            'value' => 'pro',
        ]);
        $rule2->value()->create([
            'valuable_type' => \GaiaTools\FulcrumSettings\Models\SettingRule::class,
            'valuable_id' => $rule2->id,
            'value' => 'pro',
        ]);

        expect(Feature::for('premium')->value('tiered-feature'))->toBe('premium');
        expect(Feature::for('pro')->value('tiered-feature'))->toBe('pro');
        expect(Feature::for('free')->value('tiered-feature'))->toBe('basic');
    });

    it('works with blade directive via Pennant', function () {
        $setting = Setting::create([
            'key' => 'blade-feature',
            'type' => SettingType::BOOLEAN,
        ]);
        $setting->defaultValue()->create([
            'valuable_type' => Setting::class,
            'valuable_id' => $setting->id,
            'value' => true,
        ]);

        $blade = '@feature(\'blade-feature\')
            <div>Feature content</div>
        @endfeature';

        expect(Feature::active('blade-feature'))->toBe(true);
    });
});
