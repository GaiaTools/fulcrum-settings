<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Contracts\Modules\ActivationStore;
use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\Exceptions\ModuleActivationException;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use GaiaTools\FulcrumSettings\Support\Modules\FulcrumModuleActivator;
use GaiaTools\FulcrumSettings\Support\Modules\ModuleActivation;
use GaiaTools\FulcrumSettings\Support\Modules\ModuleConfiguration;
use GaiaTools\FulcrumSettings\Support\Modules\ModuleSettings;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->directory = sys_get_temp_dir().'/fulcrum-modules-'.bin2hex(random_bytes(8));
    config([
        'fulcrum.modules.mappings' => [
            'Reports' => ['activation_setting' => 'module.reports.loaded', 'access_setting' => 'module.reports.enabled'],
            'Export' => ['activation_setting' => 'module.export.loaded', 'requires' => ['Reports']],
        ],
        'fulcrum.modules.storage.path' => $this->directory.'/activation.json',
        'fulcrum.modules.lock.store' => 'array',
    ]);
});

afterEach(function () {
    app('files')->deleteDirectory($this->directory);
});

test('module activation storage round trips a complete document', function (string $driver) {
    config(['fulcrum.modules.storage.driver' => $driver, 'fulcrum.modules.storage.cache_store' => 'array']);
    $migration = require __DIR__.'/../../../stubs/create_module_activation_table.php.stub';
    $migration->up();
    app(ModuleSettings::class)->set('Reports', true);
    app(ModuleSettings::class)->set('Export', true);
    expect(app(ActivationStore::class)->read())->toBeNull();
    expect(app(ModuleActivation::class)->sync())->toBe(['Reports' => true, 'Export' => true])
        ->and(app(ModuleActivation::class)->read())->toBe(['Reports' => true, 'Export' => true]);
    app(ModuleSettings::class)->set('Export', false);
    app(ModuleActivation::class)->sync();
    expect(app(ModuleActivation::class)->read())->toBe(['Reports' => true, 'Export' => false]);
})->with(['filesystem', 'cache', 'database']);

test('module activation defaults missing settings to disabled', function () {
    expect(app(ModuleActivation::class)->sync())->toBe(['Reports' => false, 'Export' => false]);
});

test('module activation dry run leaves published data unchanged', function () {
    app(ModuleSettings::class)->set('Reports', true);
    app(ModuleActivation::class)->sync();
    $before = file_get_contents(config('fulcrum.modules.storage.path'));
    app(ModuleSettings::class)->set('Reports', false);
    expect(app(ModuleActivation::class)->sync(true))->toBe(['Reports' => false, 'Export' => false])
        ->and(file_get_contents(config('fulcrum.modules.storage.path')))->toBe($before);
});

test('module activation rejects dependencies without replacing published data', function () {
    app(ModuleActivation::class)->sync();
    $before = file_get_contents(config('fulcrum.modules.storage.path'));
    app(ModuleSettings::class)->set('Export', true);
    expect(fn () => app(ModuleActivation::class)->sync())->toThrow(ModuleActivationException::class, 'requires enabled module Reports');
    expect(file_get_contents(config('fulcrum.modules.storage.path')))->toBe($before);
});

test('module activation ignores tenant overrides and current tenant context', function () {
    app(ModuleSettings::class)->set('Reports', false);
    $setting = Setting::create(['key' => 'module.reports.loaded', 'type' => SettingType::BOOLEAN, 'tenant_id' => 'tenant']);
    $setting->defaultValue()->create(['value' => true]);
    FulcrumContext::setTenantId('tenant');
    expect(app(ModuleActivation::class)->sync()['Reports'])->toBeFalse();
});

test('module activation rejects targeted masked and non boolean settings without publishing', function (string $invalid) {
    app(ModuleSettings::class)->set('Reports', true);
    app(ModuleActivation::class)->sync();
    $before = file_get_contents(config('fulcrum.modules.storage.path'));
    $setting = Setting::where('key', 'module.reports.loaded')->firstOrFail();
    match ($invalid) {
        'targeted' => $setting->rules()->create(['name' => 'Targeted']),
        'masked' => $setting->update(['masked' => true]),
        'string' => $setting->update(['type' => SettingType::STRING]),
    };
    expect(fn () => app(ModuleActivation::class)->sync())->toThrow(ModuleActivationException::class, 'unmasked global boolean');
    expect(file_get_contents(config('fulcrum.modules.storage.path')))->toBe($before);
})->with(['targeted', 'masked', 'string']);

test('module activation freezes statuses until another activator is constructed', function () {
    app(ModuleSettings::class)->set('Reports', true);
    app(ModuleActivation::class)->sync();
    $activator = new FulcrumModuleActivator($this->app);
    $activator->setActiveByName('Reports', false);
    expect($activator->hasStatus('Reports', true))->toBeTrue()
        ->and(app(ModuleSettings::class)->statuses()['Reports'])->toBeFalse();
    app(ModuleActivation::class)->sync();
    expect($activator->hasStatus('Reports', true))->toBeTrue()
        ->and((new FulcrumModuleActivator($this->app))->hasStatus('Reports', false))->toBeTrue();
});

test('module activation read failures disable managed modules without throwing', function (string $failure) {
    $store = Mockery::mock(ActivationStore::class);
    $state = ['version' => 1, 'definitions' => app(ModuleConfiguration::class)->fingerprint(), 'modules' => ['Reports' => true, 'Export' => false]];
    if ($failure === 'unavailable') {
        $store->shouldReceive('read')->andThrow(new RuntimeException('Storage unavailable'));
    } else {
        $state = match ($failure) {
            'missing' => null,
            'version' => array_replace($state, ['version' => 2]),
            'outdated' => array_replace($state, ['definitions' => 'old']),
            'invalid' => array_replace($state, ['modules' => ['Reports' => 'true']]),
            'dependency' => array_replace($state, ['modules' => ['Reports' => false, 'Export' => true]]),
        };
        $store->shouldReceive('read')->andReturn($state);
    }
    $this->app->instance(ActivationStore::class, $store);
    $activator = new FulcrumModuleActivator($this->app);
    expect($activator->hasStatus('Reports', false))->toBeTrue()
        ->and($activator->hasStatus('Export', false))->toBeTrue();
})->with(['missing', 'unavailable', 'version', 'outdated', 'invalid', 'dependency']);

test('module activation publishing respects the configured lock', function () {
    config(['fulcrum.modules.lock.wait' => 0]);
    $lock = Cache::store('array')->lock(config('fulcrum.modules.lock.key'), 60);
    $lock->get();
    try {
        expect(fn () => app(ModuleActivation::class)->sync())->toThrow(LockTimeoutException::class);
        expect(file_exists(config('fulcrum.modules.storage.path')))->toBeFalse();
    } finally {
        $lock->release();
    }
});

test('module activation configuration rejects duplicate keys and invalid dependencies', function () {
    config(['fulcrum.modules.mappings.Export.activation_setting' => 'module.reports.loaded']);
    expect(fn () => app(ModuleConfiguration::class)->mappings())->toThrow(InvalidArgumentException::class);
});

test('module activation store construction failures do not prevent startup', function () {
    $this->app->bind(ActivationStore::class, fn () => throw new RuntimeException('Store unavailable'));
    expect((new FulcrumModuleActivator($this->app))->hasStatus('Reports', false))->toBeTrue();
});

test('a corrupt filesystem document disables managed modules', function () {
    app('files')->ensureDirectoryExists($this->directory);
    file_put_contents(config('fulcrum.modules.storage.path'), '{invalid');
    expect((new FulcrumModuleActivator($this->app))->hasStatus('Reports', false))->toBeTrue();
});

test('a failed publish preserves the previous filesystem document', function () {
    app(ModuleSettings::class)->set('Reports', true);
    app(ModuleActivation::class)->sync();
    $path = config('fulcrum.modules.storage.path');
    $before = file_get_contents($path);
    $store = Mockery::mock(ActivationStore::class);
    $store->shouldReceive('write')->andThrow(new RuntimeException('Publish failed'));
    $this->app->instance(ActivationStore::class, $store);
    expect(fn () => app(ModuleActivation::class)->sync())->toThrow(RuntimeException::class, 'Publish failed');
    expect(file_get_contents($path))->toBe($before);
});

test('activation mapping changes invalidate published activation but access changes do not', function () {
    app(ModuleSettings::class)->set('Reports', true);
    app(ModuleActivation::class)->sync();
    config(['fulcrum.modules.mappings.Reports.access_setting' => 'other.access']);
    expect(app(ModuleActivation::class)->read()['Reports'])->toBeTrue();
    config(['fulcrum.modules.mappings.Reports.activation_setting' => 'other.activation']);
    expect(app(ModuleActivation::class)->read())->toBe([]);
});

test('a partial activation document defaults unspecified modules to disabled', function () {
    app(ActivationStore::class)->write([
        'version' => 1,
        'definitions' => app(ModuleConfiguration::class)->fingerprint(),
        'modules' => ['Reports' => true],
    ]);
    expect(app(ModuleActivation::class)->read())->toBe(['Reports' => true, 'Export' => false]);
});

test('module mappings reject invalid definitions and relationships', function (array $mappings) {
    config(['fulcrum.modules.mappings' => $mappings]);
    expect(fn () => app(ModuleConfiguration::class)->mappings())->toThrow(InvalidArgumentException::class);
})->with([
    'missing module name' => [['' => 'reports.enabled']],
    'invalid mapping' => [['Reports' => true]],
    'invalid activation key' => [['Reports' => ['activation_setting' => false]]],
    'empty access key' => [['Reports' => ['access_setting' => '']]],
    'dependencies must be a list' => [['Reports' => ['activation_setting' => 'reports.loaded', 'requires' => ['required' => 'Other']]]],
    'self dependency' => [['Reports' => ['activation_setting' => 'reports.loaded', 'requires' => ['Reports']]]],
    'dependencies need managed activation' => [['Reports' => ['access_setting' => 'reports.enabled', 'requires' => ['Other']]]],
    'unknown dependency' => [['Reports' => ['activation_setting' => 'reports.loaded', 'requires' => ['Other']]]],
    'access key cannot be another activation key' => [[
        'Reports' => ['activation_setting' => 'reports.loaded'],
        'Other' => ['access_setting' => 'reports.loaded'],
    ]],
]);
