<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Drivers\PennantDriver;
use GaiaTools\FulcrumSettings\Enums\ComparisonOperator;
use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\Exceptions\PennantException;
use GaiaTools\FulcrumSettings\Models\Setting;
use Illuminate\Foundation\Auth\User;

beforeEach(function () {
    if (! class_exists(\Laravel\Pennant\Feature::class)) {
        $this->markTestSkipped('Laravel Pennant is not installed');
    }
});

describe('PennantDriver', function () {
    it('gets a boolean feature value', function () {
        $setting = Setting::create([
            'key' => 'test-feature',
            'type' => SettingType::BOOLEAN,
        ]);
        $setting->defaultValue()->create(['valuable_type' => Setting::class, 'valuable_id' => $setting->id, 'value' => true]);

        $driver = app(PennantDriver::class);

        expect($driver->get('test-feature', null))->toBe(true);
    });

    it('returns null for non-existent feature', function () {
        $driver = app(PennantDriver::class);

        expect($driver->get('non-existent', null))->toBeNull();
    });

    it('evaluates rules with scope context', function () {
        $setting = Setting::create([
            'key' => 'beta-feature',
            'type' => SettingType::BOOLEAN,
        ]);
        $setting->defaultValue()->create(['valuable_type' => Setting::class, 'valuable_id' => $setting->id, 'value' => false]);

        $rule = $setting->rules()->create(['priority' => 1]);
        $rule->conditions()->create([
            'attribute' => 'email',
            'operator' => ComparisonOperator::ENDS_WITH_ANY,
            'value' => ['@company.com'],
        ]);
        $rule->value()->create(['valuable_type' => Setting::class, 'valuable_id' => $setting->id, 'value' => true]);

        $driver = app(PennantDriver::class);

        $context = ['email' => 'user@company.com'];
        expect($driver->get('beta-feature', $context))->toBe(true);

        $context = ['email' => 'user@other.com'];
        expect($driver->get('beta-feature', $context))->toBe(false);
    });

    it('extracts user from authenticatable scope', function () {
        $user = new User;
        $user->id = 1;
        $user->email = 'user@company.com';

        $setting = Setting::create([
            'key' => 'user-feature',
            'type' => SettingType::BOOLEAN,
        ]);
        $setting->defaultValue()->create(['valuable_type' => Setting::class, 'valuable_id' => $setting->id, 'value' => false]);

        $rule = $setting->rules()->create(['priority' => 1]);
        $rule->conditions()->create([
            'attribute' => 'email',
            'operator' => ComparisonOperator::ENDS_WITH_ANY,
            'value' => ['@company.com'],
        ]);
        $rule->value()->create(['valuable_type' => Setting::class, 'valuable_id' => $setting->id, 'value' => true]);

        $driver = app(PennantDriver::class);

        expect($driver->get('user-feature', $user))->toBe(true);
    });

    it('gets multiple features at once', function () {
        $setting1 = Setting::create([
            'key' => 'feature-1',
            'type' => SettingType::BOOLEAN,
        ]);
        $setting1->defaultValue()->create(['valuable_type' => Setting::class, 'valuable_id' => $setting1->id, 'value' => true]);

        $setting2 = Setting::create([
            'key' => 'feature-2',
            'type' => SettingType::BOOLEAN,
        ]);
        $setting2->defaultValue()->create(['valuable_type' => Setting::class, 'valuable_id' => $setting2->id, 'value' => false]);

        $driver = app(PennantDriver::class);

        $results = $driver->getAll(['feature-1', 'feature-2'], null);

        expect($results)->toBe([
            'feature-1' => true,
            'feature-2' => false,
        ]);
    });

    it('handles non-boolean setting types', function () {
        $setting = Setting::create([
            'key' => 'max-upload',
            'type' => SettingType::INTEGER,
        ]);
        $setting->defaultValue()->create(['valuable_type' => Setting::class, 'valuable_id' => $setting->id, 'value' => 100]);

        $driver = app(PennantDriver::class);

        expect($driver->get('max-upload', null))->toBe(100);
    });

    it('handles JSON setting types', function () {
        $setting = Setting::create([
            'key' => 'config',
            'type' => SettingType::JSON,
        ]);
        $setting->defaultValue()->create(['valuable_type' => Setting::class, 'valuable_id' => $setting->id, 'value' => ['key' => 'value', 'number' => 42]]);

        $driver = app(PennantDriver::class);

        expect($driver->get('config', null))->toBe(['key' => 'value', 'number' => 42]);
    });

    it('throws exception when trying to set a feature value', function () {
        $driver = app(PennantDriver::class);

        expect(fn () => $driver->set('feature', null, true))
            ->toThrow(PennantException::class);
    });

    it('throws exception when trying to set for all scopes', function () {
        $driver = app(PennantDriver::class);

        expect(fn () => $driver->setForAllScopes('feature', true))
            ->toThrow(PennantException::class);
    });

    it('throws exception when trying to delete a feature', function () {
        $driver = app(PennantDriver::class);

        expect(fn () => $driver->delete('feature', null))
            ->toThrow(PennantException::class);
    });

    it('throws exception when trying to purge features', function () {
        $driver = app(PennantDriver::class);

        expect(fn () => $driver->purge(['feature']))
            ->toThrow(PennantException::class);
    });

    it('handles array scope context', function () {
        $setting = Setting::create([
            'key' => 'env-feature',
            'type' => SettingType::BOOLEAN,
        ]);
        $setting->defaultValue()->create(['valuable_type' => Setting::class, 'valuable_id' => $setting->id, 'value' => false]);

        $rule = $setting->rules()->create(['priority' => 1]);
        $rule->conditions()->create([
            'attribute' => 'environment',
            'operator' => ComparisonOperator::EQUALS,
            'value' => 'production',
        ]);
        $rule->value()->create(['valuable_type' => Setting::class, 'valuable_id' => $setting->id, 'value' => true]);

        $driver = app(PennantDriver::class);

        $context = ['environment' => 'production'];
        expect($driver->get('env-feature', $context))->toBe(true);

        $context = ['environment' => 'local'];
        expect($driver->get('env-feature', $context))->toBe(false);
    });

    it('handles scalar scope by wrapping in array', function () {
        $setting = Setting::create([
            'key' => 'scalar-feature',
            'type' => SettingType::BOOLEAN,
        ]);
        $setting->defaultValue()->create(['valuable_type' => Setting::class, 'valuable_id' => $setting->id, 'value' => false]);

        $rule = $setting->rules()->create(['priority' => 1]);
        $rule->conditions()->create([
            'attribute' => 'scope',
            'operator' => ComparisonOperator::EQUALS,
            'value' => 'test',
        ]);
        $rule->value()->create(['valuable_type' => Setting::class, 'valuable_id' => $setting->id, 'value' => true]);

        $driver = app(PennantDriver::class);

        expect($driver->get('scalar-feature', 'test'))->toBe(true);
        expect($driver->get('scalar-feature', 'other'))->toBe(false);
    });

    it('converts object scope to context array', function () {
        $scope = (object) [
            'email' => 'user@company.com',
            'tier' => 'premium',
        ];

        $setting = Setting::create([
            'key' => 'object-feature',
            'type' => SettingType::BOOLEAN,
        ]);
        $setting->defaultValue()->create(['valuable_type' => Setting::class, 'valuable_id' => $setting->id, 'value' => false]);

        $rule = $setting->rules()->create(['priority' => 1]);
        $rule->conditions()->create([
            'attribute' => 'tier',
            'operator' => ComparisonOperator::EQUALS,
            'value' => 'premium',
        ]);
        $rule->value()->create(['valuable_type' => Setting::class, 'valuable_id' => $setting->id, 'value' => true]);

        $driver = app(PennantDriver::class);

        expect($driver->get('object-feature', $scope))->toBe(true);
    });

    it('does not require pre-defining features', function () {
        $driver = app(PennantDriver::class);

        // define() is a no-op for Fulcrum
        $driver->define('test', fn () => true);

        // defined() returns empty array
        expect($driver->defined())->toBeArray();

        // definedFeaturesForScope also returns empty array
        expect($driver->definedFeaturesForScope(null))->toBeArray();
    });

    it('handles fallback email attributes in authenticatable scope', function () {
        $user1 = new class implements \Illuminate\Contracts\Auth\Authenticatable
        {
            public $email = 'user1@example.com';

            public function getAuthIdentifierName()
            {
                return 'id';
            }

            public function getAuthIdentifier()
            {
                return 1;
            }

            public function getAuthPassword()
            {
                return '';
            }

            public function getAuthPasswordName()
            {
                return 'password';
            }

            public function getRememberToken()
            {
                return '';
            }

            public function setRememberToken($value) {}

            public function getRememberTokenName()
            {
                return 'remember_token';
            }
        };

        $user2 = new class implements \Illuminate\Contracts\Auth\Authenticatable
        {
            public $attributes = ['email' => 'user2@example.com'];

            public function getAuthIdentifierName()
            {
                return 'id';
            }

            public function getAuthIdentifier()
            {
                return 2;
            }

            public function getAuthPassword()
            {
                return '';
            }

            public function getAuthPasswordName()
            {
                return 'password';
            }

            public function getRememberToken()
            {
                return '';
            }

            public function setRememberToken($value) {}

            public function getRememberTokenName()
            {
                return 'remember_token';
            }
        };

        $driver = app(PennantDriver::class);

        $setting = Setting::create([
            'key' => 'email-feature',
            'type' => SettingType::BOOLEAN,
        ]);
        $setting->defaultValue()->create(['valuable_type' => Setting::class, 'valuable_id' => $setting->id, 'value' => false]);
        $rule = $setting->rules()->create(['priority' => 1]);
        $rule->conditions()->create([
            'attribute' => 'email',
            'operator' => ComparisonOperator::EQUALS,
            'value' => 'user1@example.com',
        ]);
        $rule->value()->create(['valuable_type' => Setting::class, 'valuable_id' => $setting->id, 'value' => true]);

        expect($driver->get('email-feature', $user1))->toBeTrue();

        // Check user2
        $rule->conditions()->first()->update(['value' => 'user2@example.com']);
        expect($driver->get('email-feature', $user2))->toBeTrue();
    });

    it('handles objects with toArray but no getAttributes', function () {
        $scope = new class
        {
            public function toArray()
            {
                return ['tier' => 'gold'];
            }
        };

        $setting = Setting::create([
            'key' => 'tier-feature',
            'type' => SettingType::BOOLEAN,
        ]);
        $setting->defaultValue()->create(['valuable_type' => Setting::class, 'valuable_id' => $setting->id, 'value' => false]);
        $rule = $setting->rules()->create(['priority' => 1]);
        $rule->conditions()->create([
            'attribute' => 'tier',
            'operator' => ComparisonOperator::EQUALS,
            'value' => 'gold',
        ]);
        $rule->value()->create(['valuable_type' => Setting::class, 'valuable_id' => $setting->id, 'value' => true]);

        $driver = app(PennantDriver::class);
        expect($driver->get('tier-feature', $scope))->toBeTrue();
    });

    it('extracts user from array scope', function () {
        $user = new User;
        $user->id = 1;
        $user->email = 'user@company.com';

        $setting = Setting::create([
            'key' => 'array-user-feature',
            'type' => SettingType::BOOLEAN,
        ]);
        $setting->defaultValue()->create(['valuable_type' => Setting::class, 'valuable_id' => $setting->id, 'value' => true]);

        $driver = app(PennantDriver::class);

        // This should hit line 190 in extractUser
        expect($driver->get('array-user-feature', [$user]))->toBe(true);
    });

    it('returns null for missing features in getAll', function () {
        $driver = app(PennantDriver::class);
        $results = $driver->getAll(['non-existent']);
        expect($results)->toBe(['non-existent' => null]);

        // Non-convenience call
        $results = $driver->getAll(['non-existent' => [null]]);
        expect($results)->toBe(['non-existent' => [null]]);
    });

    it('returns null for features without default values in getAll', function () {
        Setting::create([
            'key' => 'no-default',
            'type' => SettingType::BOOLEAN,
        ]);

        $driver = app(PennantDriver::class);
        $results = $driver->getAll(['no-default'], null);
        expect($results)->toBe(['no-default' => null]);
    });
});
