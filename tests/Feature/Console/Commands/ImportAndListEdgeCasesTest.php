<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Console\Commands\ImportSettingsCommand;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\CsvFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\JsonFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\YamlFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\ImportManager;
use Symfony\Component\Console\Input\ArrayInput;

test('import rejects empty and non string paths before invoking the manager', function ($path) {
    $manager = Mockery::mock(ImportManager::class);
    $manager->shouldNotReceive('import');
    $command = Mockery::mock(ImportSettingsCommand::class)->makePartial();
    $command->shouldReceive('argument')->with('path')->andReturn($path);
    $command->shouldReceive('error')->once()->with('A valid path is required.');
    expect($command->handle($manager))->toBe(1);
})->with([[''], [null], [123]]);

test('import considers an absent option to be default', function () {
    $command = new ImportSettingsCommand;
    $command->setInput(new ArrayInput(['path' => 'settings.json'], $command->getDefinition()));
    expect((new ReflectionMethod($command, 'isDefaultOption'))->invoke($command, 'unknown-option'))->toBeTrue();
});

test('import format selection handles aliases unknown extensions and explicit overrides', function (string $path, array $options, string $formatter) {
    $manager = Mockery::mock(ImportManager::class);
    $manager->shouldReceive('import')->once()->with(Mockery::type($formatter), $path, Mockery::any())->andReturn(true);
    app()->instance(ImportManager::class, $manager);
    $this->artisan('fulcrum:import', ['path' => $path, ...$options])
        ->expectsOutput('Settings imported successfully.')->assertExitCode(0);
})->with([
    'yml alias' => ['settings.yml', [], YamlFormatter::class],
    'compressed yml alias' => ['settings.yml.gz', [], YamlFormatter::class],
    'unknown extension falls back to csv' => ['settings.data', [], CsvFormatter::class],
    'explicit json overrides yaml extension' => ['settings.yaml', ['--format' => 'json'], JsonFormatter::class],
]);

test('import normalizes invalid options and retains valid nondefault choices', function (string $mode, string $conflict, string $chunk, string $expectedMode, string $expectedConflict, int $expectedChunk) {
    $manager = Mockery::mock(ImportManager::class);
    $manager->shouldReceive('import')->once()->with(Mockery::type(JsonFormatter::class), 'settings.json', [
        'mode' => $expectedMode,
        'truncate' => false,
        'conflict_handling' => $expectedConflict,
        'dry_run' => false,
        'chunk_size' => $expectedChunk,
    ])->andReturn(true);
    app()->instance(ImportManager::class, $manager);
    $this->artisan('fulcrum:import', ['path' => 'settings.json', '--mode' => $mode, '--conflict-handling' => $conflict, '--chunk-size' => $chunk])->assertExitCode(0);
})->with([
    'invalid options use defaults' => ['invalid', 'invalid', 'invalid', 'upsert', 'fail', 1000],
    'insert and log are retained' => ['insert', 'log', '25', 'insert', 'log', 25],
]);

test('list reports when no settings match', function (array $options) {
    if ($options !== []) {
        Setting::create(['key' => 'other.tenant', 'type' => 'string', 'tenant_id' => 'other']);
    }
    $this->artisan('fulcrum:list', $options)->expectsOutput('No settings found.')->assertExitCode(0);
})->with([
    'empty database' => [[]],
    'unmatched tenant' => [['--tenant' => 'missing']],
    'no global settings' => [['--no-tenants' => true]],
]);

test('list renders metadata and gives an explicit tenant precedence over no tenants', function () {
    Setting::create(['key' => 'global', 'type' => 'boolean']);
    Setting::create(['key' => 'tenant', 'type' => 'string', 'tenant_id' => 'selected', 'immutable' => true, 'masked' => true, 'description' => 'Tenant configuration']);
    $this->artisan('fulcrum:list', ['--tenant' => 'selected', '--no-tenants' => true])
        ->expectsTable(['Key', 'Type', 'Tenant ID', 'Masked', 'Immutable', 'Description'], [
            ['tenant', 'string', 'selected', 'Yes', 'Yes', 'Tenant configuration'],
        ])->assertExitCode(0);
});

test('list ignores tenant filters when multi tenancy is disabled and sorts by key', function () {
    config(['fulcrum.multi_tenancy.enabled' => false]);
    Setting::create(['key' => 'zeta', 'type' => 'string', 'description' => 'Last']);
    Setting::create(['key' => 'alpha', 'type' => 'boolean']);
    $this->artisan('fulcrum:list', ['--tenant' => 'missing', '--no-tenants' => true])
        ->expectsTable(['Key', 'Type', 'Masked', 'Immutable', 'Description'], [
            ['alpha', 'boolean', 'No', 'No', '-'],
            ['zeta', 'string', 'No', 'No', 'Last'],
        ])->assertExitCode(0);
});
