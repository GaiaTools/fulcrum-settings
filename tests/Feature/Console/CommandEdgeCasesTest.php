<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Console\Commands\GetSettingCommand;
use GaiaTools\FulcrumSettings\Console\Commands\ModuleStatusCommand;
use GaiaTools\FulcrumSettings\Console\Commands\SetSettingCommand;
use GaiaTools\FulcrumSettings\Contracts\SettingResolver;
use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\Facades\Fulcrum;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Models\SettingRule;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use GaiaTools\FulcrumSettings\Support\Modules\ModuleActivation;
use GaiaTools\FulcrumSettings\Support\Modules\ModuleConfiguration;
use GaiaTools\FulcrumSettings\Support\Modules\ModuleSettings;
use GaiaTools\FulcrumSettings\Support\TypeRegistry;
use Nwidart\Modules\Contracts\RepositoryInterface;

beforeEach(function () {
    $this->invokeCommand = fn ($command, $method, ...$args) => (new ReflectionMethod($command, $method))->invoke($command, ...$args);
    $this->wizard = Mockery::mock(SetSettingCommand::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $this->wizard->shouldReceive('table', 'line', 'info')->andReturnNull();
    $this->setting = Setting::create(['key' => 'edge', 'type' => 'string']);
    $this->registry = app(TypeRegistry::class);
});

test('command options normalize boolean inputs', function ($input, bool $expected) {
    $command = Mockery::mock(GetSettingCommand::class)->makePartial();
    $command->shouldReceive('option')->with('reveal')->andReturn($input);
    expect(($this->invokeCommand)($command, 'getBoolOption', 'reveal'))->toBe($expected);
})->with([['true', true], ['off', false], ['invalid', false], [null, false], [1, false], [true, true], [false, false]]);

test('command options normalize integer inputs', function ($input, int $expected) {
    $command = Mockery::mock(GetSettingCommand::class)->makePartial();
    $command->shouldReceive('option')->with('limit')->andReturn($input);
    expect(($this->invokeCommand)($command, 'getIntOption', 'limit', 12))->toBe($expected);
})->with([['42', 42], ['invalid', 12], [null, 12], [42, 12]]);

test('get command rejects empty and non string keys', function ($key) {
    $command = Mockery::mock(GetSettingCommand::class)->makePartial();
    $command->shouldReceive('argument')->with('key')->andReturn($key);
    $command->shouldReceive('error')->once()->with('A valid setting key is required.');
    expect($command->handle())->toBe(1);
})->with([[''], [null], [123]]);

test('get command reports an unavailable resolver', function () {
    Fulcrum::clearResolvedInstance(SettingResolver::class);
    app()->bind(SettingResolver::class, fn () => null);
    $this->artisan('fulcrum:get', ['key' => 'edge'])
        ->expectsOutput('Setting resolver is not available.')->assertExitCode(1);
});

test('module status reports configuration failures', function () {
    $settings = Mockery::mock(ModuleSettings::class);
    $settings->shouldReceive('statuses')->once()->andThrow(new RuntimeException('Invalid module configuration'));
    $command = Mockery::mock(ModuleStatusCommand::class)->makePartial();
    $command->shouldReceive('error')->once()->with('Invalid module configuration');
    expect($command->handle($settings, app(ModuleActivation::class), app(ModuleConfiguration::class), Mockery::mock(RepositoryInterface::class)))->toBe(1);
});

test('set command updates metadata and resets force after validation failure', function () {
    $this->artisan('fulcrum:set', ['key' => 'edge', 'value' => '4', '--type' => 'integer', '--description' => 'Updated', '--masked' => true, '--immutable' => true, '--force' => true, '--tenant' => ''])
        ->assertExitCode(0);
    $setting = $this->setting->fresh();
    expect($setting->type)->toBe(SettingType::INTEGER)->and($setting->description)->toBe('Updated')
        ->and($setting->masked)->toBeTrue()->and($setting->immutable)->toBeTrue()->and(FulcrumContext::shouldForce())->toBeFalse();
    $this->artisan('fulcrum:set', ['key' => 'bad.array', 'value' => 'invalid', '--type' => 'array', '--force' => true])
        ->expectsOutput('Invalid value for type [array].')->assertExitCode(1);
    expect(FulcrumContext::shouldForce())->toBeFalse();
});

test('value conversion handles valid and invalid typed inputs', function ($value, SettingType $type, $expected) {
    expect(($this->invokeCommand)($this->wizard, 'convertValue', $value, $type))->toBe($expected);
})->with([
    ['nonsense', SettingType::BOOLEAN, false], ['false', SettingType::BOOLEAN, false],
    ['12', SettingType::INTEGER, 12], ['bad', SettingType::INTEGER, 0],
    ['1.5', SettingType::FLOAT, 1.5], ['bad', SettingType::FLOAT, 0.0],
    [['a' => 1], SettingType::JSON, ['a' => 1]], ['{"a":1}', SettingType::JSON, ['a' => 1]],
    ['bad', SettingType::JSON, null], [null, SettingType::JSON, null],
]);

test('wizard rejects a missing key', function () {
    $this->artisan('fulcrum:set')->expectsQuestion('Enter the setting key', '')
        ->expectsOutput('Key is required.')->assertExitCode(1);
});

test('wizard updates an existing setting with a supplied key and force', function () {
    $this->artisan('fulcrum:set', ['key' => 'edge', '--tenant' => '', '--force' => true])
        ->expectsQuestion('Enter description', 'Changed')->expectsQuestion('Is this setting sensitive/masked?', false)
        ->expectsQuestion('Is this setting immutable?', true)->expectsQuestion('Enter default value (string)', 'updated')
        ->expectsQuestion('Do you want to manage targeting rules for this setting?', false)->assertExitCode(0);
    expect($this->setting->fresh()->getDefaultValue())->toBe('updated')->and(FulcrumContext::shouldForce())->toBeFalse();
});

test('wizard cancellation leaves immutable settings unchanged', function () {
    $this->artisan('fulcrum:set', ['key' => 'edge', '--tenant' => ''])
        ->expectsQuestion('Enter description', 'Cancelled')->expectsQuestion('Is this setting sensitive/masked?', false)
        ->expectsQuestion('Is this setting immutable?', true)->expectsQuestion('Setting is/will be immutable. Continue?', false)->assertExitCode(0);
    expect($this->setting->fresh()->description)->toBeNull()->and($this->setting->fresh()->immutable)->toBeFalse();
});

test('wizard reports invalid defaults and clears force on failure', function () {
    $this->setting->update(['type' => 'array']);
    $this->artisan('fulcrum:set', ['key' => 'edge', '--tenant' => '', '--force' => true])
        ->expectsQuestion('Enter description', 'Invalid')->expectsQuestion('Is this setting sensitive/masked?', false)
        ->expectsQuestion('Is this setting immutable?', false)->expectsQuestion('Enter default value (array)', 'invalid')
        ->expectsOutput('Failed to save setting: Invalid value ["invalid"] for type [array].')->assertExitCode(1);
    expect(FulcrumContext::shouldForce())->toBeFalse();
});

test('saving an orphan rule value reports the missing parent', function () {
    expect(fn () => ($this->invokeCommand)($this->wizard, 'saveValue', new SettingRule, 'value', $this->registry, null))
        ->toThrow(RuntimeException::class, 'Could not resolve parent setting');
});

test('rule manager rejects invalid edits and only deletes confirmed rules', function () {
    $rule = $this->setting->rules()->create(['name' => 'Keep or delete']);
    $this->wizard->shouldReceive('choice')->with('Action', Mockery::any(), 'Done')->andReturn('Edit Rule', 'Edit Rule', 'Delete Rule', 'Delete Rule', 'Delete Rule', 'Done');
    $this->wizard->shouldReceive('ask')->with('Enter Rule ID to edit')->andReturn('bad', '9999');
    $this->wizard->shouldReceive('error')->twice()->with('Rule not found.');
    $this->wizard->shouldReceive('ask')->with('Enter Rule ID to delete')->andReturn('bad', (string) $rule->id, (string) $rule->id);
    $this->wizard->shouldReceive('confirm')->with('Are you sure you want to delete rule [Keep or delete]?')->andReturn(false, true);
    ($this->invokeCommand)($this->wizard, 'manageRules', $this->setting, $this->registry, null);
    expect($rule->fresh())->toBeNull();
});

test('rule manager edits an existing direct value and preserves invalid priority input', function () {
    $rule = $this->setting->rules()->create(['name' => 'Before', 'priority' => 7]);
    $rule->value()->create(['value' => 'old']);
    $this->wizard->shouldReceive('choice')->with('Action', Mockery::any(), 'Done')->andReturn('Edit Rule', 'Done');
    $this->wizard->shouldReceive('ask')->with('Enter Rule ID to edit')->andReturn((string) $rule->id);
    $this->wizard->shouldReceive('ask')->with('Rule name', 'Before')->andReturn('After');
    $this->wizard->shouldReceive('ask')->with('Priority (lower runs first)', '7')->andReturn('invalid');
    $this->wizard->shouldReceive('choice')->with('Conditions Action', Mockery::any(), 'Done')->andReturn('Done');
    $this->wizard->shouldReceive('confirm')->with('Is this a percentage rollout?', false)->andReturn(false);
    $this->wizard->shouldReceive('ask')->with('Enter rule value (string)', 'old')->andReturn('new');
    ($this->invokeCommand)($this->wizard, 'manageRules', $this->setting, $this->registry, null);
    expect($rule->fresh()->name)->toBe('After')->and($rule->fresh()->priority)->toBe(7)->and($rule->fresh()->getValue())->toBe('new');
});

test('condition manager validates attributes and stores array string and valueless operators', function () {
    $rule = $this->setting->rules()->create();
    $this->wizard->shouldReceive('choice')->with('Conditions Action', Mockery::any(), 'Done')->andReturn('Add Condition', 'Add Condition', 'Add Condition', 'Add Condition', 'Delete Condition', 'Done');
    $this->wizard->shouldReceive('ask')->with('Attribute (e.g., user_id, email, segment)')->andReturn('', 'role', 'email', 'name');
    $this->wizard->shouldReceive('error')->once()->with('Attribute is required.');
    $this->wizard->shouldReceive('choice')->with('Operator', Mockery::any(), 'equals')->andReturn('number_between', 'equals', 'is_null');
    $this->wizard->shouldReceive('ask')->with('Enter values (comma separated)')->andReturn('admin, editor, ,');
    $this->wizard->shouldReceive('ask')->with('Enter value')->andReturn('"example"');
    $this->wizard->shouldReceive('ask')->with('Enter Condition ID to delete')->andReturn('invalid');
    ($this->invokeCommand)($this->wizard, 'manageConditions', $rule);
    $conditions = $rule->conditions()->get();
    expect($conditions)->toHaveCount(3)->and($conditions[0]->value)->toBe(['admin', 'editor'])
        ->and($conditions[1]->value)->toBe('example')->and($conditions[2]->value)->toBeNull();
});

test('condition manager deletes existing conditions and tolerates missing IDs', function () {
    $rule = $this->setting->rules()->create();
    $condition = $rule->conditions()->create(['type' => 'user', 'attribute' => 'id', 'operator' => 'equals', 'value' => '1']);
    $this->wizard->shouldReceive('choice')->andReturn('Delete Condition', 'Delete Condition', 'Done');
    $this->wizard->shouldReceive('ask')->with('Enter Condition ID to delete')->andReturn('9999', (string) $condition->id);
    ($this->invokeCommand)($this->wizard, 'manageConditions', $rule);
    expect($condition->fresh())->toBeNull();
});

test('rollout manager requires excess weight to be corrected before exiting', function () {
    $rule = $this->setting->rules()->create();
    $variant = $rule->rolloutVariants()->create(['name' => 'Too much', 'weight' => 101000]);
    $this->wizard->shouldReceive('choice')->andReturn('Done', 'Delete Variant', 'Delete Variant', 'Delete Variant', 'Done');
    $this->wizard->shouldReceive('error')->once()->with('Total weight exceeds 100%! Please adjust.');
    $this->wizard->shouldReceive('ask')->with('Enter Variant ID to delete')->andReturn('invalid', '9999', (string) $variant->id);
    ($this->invokeCommand)($this->wizard, 'manageRollouts', $rule, $this->registry, null);
    expect($variant->fresh())->toBeNull();
});

test('get command formats booleans structures and unsupported values', function ($value, string $expected) {
    expect(($this->invokeCommand)(new GetSettingCommand, 'formatValue', $value))->toBe($expected);
})->with([[true, 'true'], [false, 'false'], [12, '12'], [['a' => 1], "{\n    \"a\": 1\n}"], [null, ''], [[NAN], '']]);

test('wizard can confirm creation of an immutable setting', function () {
    config(['fulcrum.multi_tenancy.enabled' => false]);
    $this->artisan('fulcrum:set', ['key' => 'confirmed'])
        ->expectsChoice('Select setting type', 'string', array_column(SettingType::cases(), 'value'))
        ->expectsQuestion('Enter description', 'Confirmed')->expectsQuestion('Is this setting sensitive/masked?', false)
        ->expectsQuestion('Is this setting immutable?', true)->expectsQuestion('Setting is/will be immutable. Continue?', true)
        ->expectsQuestion('Enter default value (string)', 'value')
        ->expectsQuestion('Do you want to manage targeting rules for this setting?', false)->assertExitCode(0);
    expect(Setting::where('key', 'confirmed')->firstOrFail()->immutable)->toBeTrue();
});

test('rule wizard creates a direct value without tenancy', function () {
    config(['fulcrum.multi_tenancy.enabled' => false]);
    $this->wizard->shouldReceive('ask')->with('Rule name', null)->andReturn('Direct');
    $this->wizard->shouldReceive('ask')->with('Priority (lower runs first)', '100')->andReturn('invalid');
    $this->wizard->shouldReceive('choice')->with('Conditions Action', Mockery::any(), 'Done')->andReturn('Done');
    $this->wizard->shouldReceive('confirm')->with('Is this a percentage rollout?', false)->andReturn(false);
    $this->wizard->shouldReceive('ask')->with('Enter rule value (string)', null)->andReturn('direct');
    ($this->invokeCommand)($this->wizard, 'addOrEditRule', $this->setting, null, $this->registry, null);
    $rule = $this->setting->rules()->firstOrFail();
    expect($rule->getValue())->toBe('direct')->and($rule->priority)->toBe(100)->and($rule->tenant_id)->toBeNull();
});

test('rollout wizard handles nonnumeric weights without tenancy', function () {
    config(['fulcrum.multi_tenancy.enabled' => false]);
    $rule = $this->setting->rules()->create();
    $this->wizard->shouldReceive('choice')->andReturn('Add Variant', 'Done');
    $this->wizard->shouldReceive('ask')->with('Variant name')->andReturn('Zero');
    $this->wizard->shouldReceive('ask')->with('Weight percentage (0-100)')->andReturn('invalid');
    $this->wizard->shouldReceive('ask')->with('Variant value (string)')->andReturn('value');
    ($this->invokeCommand)($this->wizard, 'manageRollouts', $rule, $this->registry, null);
    $variant = $rule->rolloutVariants()->firstOrFail();
    expect($variant->weight)->toBe(0)->and($variant->getValue())->toBe('value')->and($variant->tenant_id)->toBeNull();
});

test('saving invalid unencodable values reports validation errors', function () {
    expect(fn () => ($this->invokeCommand)($this->wizard, 'saveValue', $this->setting, NAN, $this->registry, null))
        ->toThrow(InvalidArgumentException::class, 'Invalid value [null] for type [string].');
});
