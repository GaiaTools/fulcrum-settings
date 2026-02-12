<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Unit\Support\Settings;

use GaiaTools\FulcrumSettings\Attributes\SettingProperty;
use GaiaTools\FulcrumSettings\Contracts\SettingResolver;
use GaiaTools\FulcrumSettings\Events\LoadingSettings;
use GaiaTools\FulcrumSettings\Events\SavingSettings;
use GaiaTools\FulcrumSettings\Events\SettingsLoaded;
use GaiaTools\FulcrumSettings\Events\SettingsSaved;
use GaiaTools\FulcrumSettings\Support\Settings\FulcrumSettings;
use GaiaTools\FulcrumSettings\Support\Settings\SettingsHydrator;
use GaiaTools\FulcrumSettings\Support\Settings\SettingsPersister;
use GaiaTools\FulcrumSettings\Support\Settings\SettingsPropertyDiscoverer;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Event;
use Mockery;

class TestSettings extends FulcrumSettings
{
    #[SettingProperty(key: 'test.name')]
    protected ?string $name = null;

    #[SettingProperty(key: 'test.lazy', lazy: true)]
    protected ?string $lazyProp = null;

    #[SettingProperty(key: 'test.readonly', readOnly: true)]
    protected ?string $readOnlyProp = 'initial';

    #[SettingProperty(key: 'test.tenant', tenantScoped: true)]
    protected ?string $tenantProp = null;
}

beforeEach(function () {
    $this->resolver = Mockery::mock(SettingResolver::class);
    $this->discoverer = Mockery::mock(SettingsPropertyDiscoverer::class);
    $this->hydrator = Mockery::mock(SettingsHydrator::class);
    $this->persister = Mockery::mock(SettingsPersister::class);

    $this->configs = [
        'name' => new SettingProperty(key: 'test.name'),
        'lazyProp' => new SettingProperty(key: 'test.lazy', lazy: true),
        'readOnlyProp' => new SettingProperty(key: 'test.readonly', readOnly: true),
        'tenantProp' => new SettingProperty(key: 'test.tenant', tenantScoped: true),
    ];

    $this->discoverer->shouldReceive('discover')->andReturn($this->configs);
});

test('load hydrates non-lazy properties and dispatches events', function () {
    Event::fake();

    $this->hydrator->shouldReceive('hydrate')->with(Mockery::any(), 'name', $this->configs['name'], Mockery::any(), Mockery::any())->andReturn('Fulcrum');
    $this->hydrator->shouldReceive('hydrate')->with(Mockery::any(), 'tenantProp', $this->configs['tenantProp'], Mockery::any(), Mockery::any())->andReturn('TenantValue');
    $this->hydrator->shouldReceive('hydrate')->with(Mockery::any(), 'readOnlyProp', $this->configs['readOnlyProp'], Mockery::any(), Mockery::any())->andReturn('InitialValue');
    $this->hydrator->shouldNotReceive('hydrate')->with(Mockery::any(), 'lazyProp', Mockery::any(), Mockery::any(), Mockery::any());

    $settings = new TestSettings($this->resolver, $this->discoverer, $this->hydrator, $this->persister);

    expect($settings->name)->toBe('Fulcrum');
    expect($settings->tenantProp)->toBe('TenantValue');

    Event::assertDispatched(LoadingSettings::class);
    Event::assertDispatched(SettingsLoaded::class, function ($event) {
        return isset($event->settings['test.name']) && $event->settings['test.name'] === 'Fulcrum';
    });
});

test('lazy properties are hydrated on access', function () {
    $this->hydrator->shouldReceive('hydrate')->byDefault()->andReturn('value');
    $settings = new TestSettings($this->resolver, $this->discoverer, $this->hydrator, $this->persister);

    $this->hydrator->shouldReceive('hydrate')
        ->with($settings, 'lazyProp', $this->configs['lazyProp'], Mockery::any(), Mockery::any())
        ->once()
        ->andReturn('LazyValue');

    // Access via __get
    expect($settings->lazyProp)->toBe('LazyValue');
    // Second access should not trigger hydration again
    expect($settings->lazyProp)->toBe('LazyValue');
});

test('magic __get returns null for non-existent properties', function () {
    $this->hydrator->shouldReceive('hydrate')->byDefault()->andReturn('value');
    $settings = new TestSettings($this->resolver, $this->discoverer, $this->hydrator, $this->persister);

    expect($settings->nonExistent)->toBeNull();
});

test('magic __set handles dirty state and read-only properties', function () {
    $this->hydrator->shouldReceive('hydrate')->byDefault()->andReturn('value');
    $settings = new TestSettings($this->resolver, $this->discoverer, $this->hydrator, $this->persister);

    expect($settings->isDirty())->toBeFalse();

    $settings->name = 'New Name';
    expect($settings->name)->toBe('New Name');
    expect($settings->isDirty())->toBeTrue();
    expect($settings->isDirty('name'))->toBeTrue();
    expect($settings->isDirty('lazyProp'))->toBeFalse();

    // Read-only property
    $settings->readOnlyProp = 'Changed';
    expect($settings->readOnlyProp)->toBe('value'); // remains 'value' from hydration
    expect($settings->isDirty('readOnlyProp'))->toBeFalse();

    // Non-existent property
    $settings->somethingElse = 'value';
    expect(isset($settings->somethingElse))->toBeFalse();
});

test('save persists dirty properties and dispatches events', function () {
    Event::fake();
    $this->hydrator->shouldReceive('hydrate')->byDefault()->andReturn('value');
    $settings = new TestSettings($this->resolver, $this->discoverer, $this->hydrator, $this->persister);

    $settings->name = 'Dirty Name';

    $this->persister->shouldReceive('validateWithRules')->once();
    $this->persister->shouldReceive('persist')->with('test.name', 'Dirty Name')->once();

    $settings->save();

    expect($settings->isDirty())->toBeFalse();
    Event::assertDispatched(SavingSettings::class);
    Event::assertDispatched(SettingsSaved::class);
});

test('save returns early if nothing is dirty', function () {
    $this->hydrator->shouldReceive('hydrate')->byDefault()->andReturn('value');
    $settings = new TestSettings($this->resolver, $this->discoverer, $this->hydrator, $this->persister);

    $this->persister->shouldNotReceive('persist');
    $settings->save();
});

test('cloning with forUser, forTenant, and withContext', function () {
    $this->hydrator->shouldReceive('hydrate')->byDefault()->andReturn('value');
    $settings = new TestSettings($this->resolver, $this->discoverer, $this->hydrator, $this->persister);

    $user = Mockery::mock(Authenticatable::class);
    $this->resolver->shouldReceive('forUser')->with($user)->andReturn($this->resolver);
    $clonedUser = $settings->forUser($user);
    expect($clonedUser)->not->toBe($settings);

    $this->resolver->shouldReceive('forTenant')->with('tenant-123')->andReturn($this->resolver);
    $clonedTenant = $settings->forTenant('tenant-123');
    expect($clonedTenant)->not->toBe($settings);

    $clonedContext = $settings->withContext(['foo' => 'bar']);
    expect($clonedContext)->not->toBe($settings);
});

test('refresh reloads all non-lazy properties', function () {
    $this->hydrator->shouldReceive('hydrate')->byDefault()->andReturn('Initial');
    $settings = new TestSettings($this->resolver, $this->discoverer, $this->hydrator, $this->persister);

    $settings->name = 'Dirty';
    expect($settings->isDirty())->toBeTrue();

    $this->hydrator->shouldReceive('hydrate')->andReturn('Refreshed');
    $settings->refresh();

    expect($settings->name)->toBe('Refreshed');
    expect($settings->isDirty())->toBeFalse();
});

test('it sets timezone in container during lazy loading', function () {
    $this->hydrator->shouldReceive('hydrate')->byDefault()->andReturn('value');
    $settings = new TestSettings($this->resolver, $this->discoverer, $this->hydrator, $this->persister);
    $settings = $settings->setTimezone('UTC');

    $this->hydrator->shouldReceive('hydrate')
        ->with($settings, 'lazyProp', $this->configs['lazyProp'], Mockery::any(), Mockery::any())
        ->once()
        ->andReturnUsing(function () {
            expect(app()->bound('fulcrum.context.timezone'))->toBeTrue();
            expect(app('fulcrum.context.timezone'))->toBe('UTC');

            return 'LazyWithTimezone';
        });

    expect($settings->lazyProp)->toBe('LazyWithTimezone');
    expect(app()->bound('fulcrum.context.timezone'))->toBeFalse();
});

test('toArray and toJson serialize settings using setting keys and hydrate lazy properties', function () {
    $this->hydrator->shouldReceive('hydrate')
        ->with(Mockery::any(), 'name', $this->configs['name'], Mockery::any(), Mockery::any())
        ->andReturn('Fulcrum');
    $this->hydrator->shouldReceive('hydrate')
        ->with(Mockery::any(), 'tenantProp', $this->configs['tenantProp'], Mockery::any(), Mockery::any())
        ->andReturn('TenantValue');
    $this->hydrator->shouldReceive('hydrate')
        ->with(Mockery::any(), 'readOnlyProp', $this->configs['readOnlyProp'], Mockery::any(), Mockery::any())
        ->andReturn('InitialValue');

    $settings = new TestSettings($this->resolver, $this->discoverer, $this->hydrator, $this->persister);

    $this->hydrator->shouldReceive('hydrate')
        ->with($settings, 'lazyProp', $this->configs['lazyProp'], Mockery::any(), Mockery::any())
        ->once()
        ->andReturn('LazyValue');

    $expected = [
        'test.name' => 'Fulcrum',
        'test.lazy' => 'LazyValue',
        'test.readonly' => 'InitialValue',
        'test.tenant' => 'TenantValue',
    ];

    expect($settings->toArray())->toBe($expected);
    expect(json_decode($settings->toJson(), true))->toBe($expected);
});

test('collection methods are proxied through settings instances', function () {
    $this->hydrator->shouldReceive('hydrate')->byDefault()->andReturn('value');
    $settings = new TestSettings($this->resolver, $this->discoverer, $this->hydrator, $this->persister);

    $keys = $settings
        ->filter(fn ($value) => $value !== null)
        ->keys()
        ->all();

    expect($keys)->toContain('test.name');
    expect($keys)->toContain('test.readonly');
    expect($keys)->toContain('test.tenant');
});
