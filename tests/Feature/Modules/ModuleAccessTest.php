<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\Http\Middleware\EnsureModuleAccess;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Providers\FulcrumModulesServiceProvider;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use GaiaTools\FulcrumSettings\Support\Modules\ModuleAccess;
use Illuminate\Auth\GenericUser;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Process\Process;

beforeEach(function () {
    config(['fulcrum.modules.mappings' => ['Reports' => 'module.reports.enabled']]);
    $this->setting = Setting::create(['key' => 'module.reports.enabled', 'type' => SettingType::BOOLEAN]);
    $this->setting->defaultValue()->create(['value' => false]);
    $rule = $this->setting->rules()->create(['name' => 'Test users']);
    $rule->conditions()->create(['type' => 'user', 'attribute' => 'id', 'operator' => 'equals', 'value' => 7]);
    $rule->value()->create(['value' => true]);
    Route::get('/module-reports', fn () => 'reports')->middleware('fulcrum.module:Reports');
});

test('module access evaluates existing Fulcrum rules for each user', function () {
    $access = app(ModuleAccess::class);
    expect($access->allows('Reports', new GenericUser(['id' => 7])))->toBeTrue()
        ->and($access->allows('Reports', new GenericUser(['id' => 8])))->toBeFalse()
        ->and($access->allows('Unknown'))->toBeFalse();
});

test('module access middleware denies guests and ordinary users with 404', function () {
    $this->get('/module-reports')->assertNotFound();
    $this->actingAs(new GenericUser(['id' => 8]))->get('/module-reports')->assertNotFound();
    $this->actingAs(new GenericUser(['id' => 7]))->get('/module-reports')->assertOk()->assertSee('reports');
});

test('module access uses the global denial status', function () {
    config(['fulcrum.modules.denied_status' => 403]);
    $this->get('/module-reports')->assertForbidden();
});

test('module access respects tenant context without retaining the previous decision', function () {
    $tenant = Setting::create(['key' => 'module.reports.enabled', 'type' => SettingType::BOOLEAN, 'tenant_id' => 'test-tenant']);
    $tenant->defaultValue()->create(['value' => true]);
    $access = app(ModuleAccess::class);
    FulcrumContext::setTenantId('test-tenant');
    expect($access->allows('Reports', new GenericUser(['id' => 8])))->toBeTrue();
    FulcrumContext::setTenantId(null);
    expect($access->allows('Reports', new GenericUser(['id' => 8])))->toBeFalse();
});

test('module middleware aliases expand to the same contextual access check', function () {
    config(['fulcrum.modules.aliases' => ['reports.enabled' => 'Reports']]);
    (new FulcrumModulesServiceProvider($this->app))->boot();
    Route::get('/alias-reports', fn () => 'reports')->middleware('reports.enabled');
    $this->get('/alias-reports')->assertNotFound();
    $this->actingAs(new GenericUser(['id' => 7]))->get('/alias-reports')->assertOk();
});

test('module aliases cannot replace an existing middleware alias', function () {
    Route::aliasMiddleware('occupied', EnsureModuleAccess::class);
    config(['fulcrum.modules.aliases' => ['occupied' => 'Reports']]);
    (new FulcrumModulesServiceProvider($this->app))->boot();
})->throws(InvalidArgumentException::class);

test('module access fails closed for missing or non boolean setting values', function () {
    config(['fulcrum.modules.mappings' => ['Missing' => 'missing', 'Text' => 'text']]);
    $setting = Setting::create(['key' => 'text', 'type' => SettingType::STRING]);
    $setting->defaultValue()->create(['value' => 'true']);
    expect(app(ModuleAccess::class)->allows('Missing'))->toBeFalse()
        ->and(app(ModuleAccess::class)->allows('Text'))->toBeFalse();
});

test('Fulcrum boots with Laravel Modules unavailable', function () {
    $process = new Process([PHP_BINARY, __DIR__.'/../../Fixtures/BootWithoutLaravelModules.php']);
    $process->mustRun();
    expect($process->getOutput())->toContain('Fulcrum booted without Laravel Modules.');
});

test('compiled routes retain module middleware aliases', function () {
    config(['fulcrum.modules.aliases' => ['reports.enabled' => 'Reports']]);
    (new FulcrumModulesServiceProvider($this->app))->boot();
    Route::get('/compiled-reports', fn () => 'reports')->middleware('reports.enabled')->name('compiled-reports');
    $router = app(Router::class);
    $router->setCompiledRoutes($router->getRoutes()->compile());
    $this->get('/compiled-reports')->assertNotFound();
    $this->actingAs(new GenericUser(['id' => 7]))->get('/compiled-reports')->assertOk();
});
