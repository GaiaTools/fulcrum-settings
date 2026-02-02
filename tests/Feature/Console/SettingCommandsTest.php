<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Feature\Console;

use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use GaiaTools\FulcrumSettings\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;

class SettingCommandsTest extends TestCase
{
    public function test_get_setting_command()
    {
        FulcrumContext::setTenantId('tenant-1');
        Setting::create(['key' => 'test.key', 'type' => 'string', 'tenant_id' => 'tenant-1'])
            ->defaultValue()->create(['value' => 'test-value', 'tenant_id' => 'tenant-1']);

        $this->artisan('fulcrum:get', ['key' => 'test.key', '--tenant' => 'tenant-1'])
            ->expectsOutput('test-value')
            ->assertExitCode(0);
    }

    public function test_get_setting_command_not_found()
    {
        $this->artisan('fulcrum:get', ['key' => 'non.existent'])
            ->expectsOutput('Setting [non.existent] not found.')
            ->assertExitCode(1);
    }

    public function test_get_masked_setting_command()
    {
        $setting = Setting::create(['key' => 'secret.key', 'type' => 'string', 'masked' => true]);
        $setting->defaultValue()->create(['value' => 'secret-value']);

        // Without reveal
        $this->artisan('fulcrum:get', ['key' => 'secret.key'])
            ->expectsOutput('The value for [secret.key] is masked. Use --reveal to see the actual value.')
            ->expectsOutput('********')
            ->assertExitCode(0);

        // With reveal (CLI context bypasses gate)
        $this->artisan('fulcrum:get', ['key' => 'secret.key', '--reveal' => true])
            ->expectsOutput('secret-value')
            ->assertExitCode(0);
    }

    public function test_set_setting_command()
    {
        $this->artisan('fulcrum:set', [
            'key' => 'new.setting',
            'value' => 'new-value',
            '--description' => 'A new setting',
            '--type' => 'string',
        ])
            ->expectsOutput('Setting [new.setting] updated successfully.')
            ->assertExitCode(0);

        $this->assertEquals('new-value', \GaiaTools\FulcrumSettings\Facades\Fulcrum::get('new.setting'));

        $setting = Setting::where('key', 'new.setting')->first();
        $this->assertEquals('A new setting', $setting->description);
    }

    public function test_set_setting_command_with_types()
    {
        $this->artisan('fulcrum:set', [
            'key' => 'int.setting',
            'value' => '123',
            '--type' => 'integer',
        ])->assertExitCode(0);

        $this->assertSame(123, \GaiaTools\FulcrumSettings\Facades\Fulcrum::get('int.setting'));

        $this->artisan('fulcrum:set', [
            'key' => 'bool.setting',
            'value' => 'true',
            '--type' => 'boolean',
        ])->assertExitCode(0);

        $this->assertSame(true, \GaiaTools\FulcrumSettings\Facades\Fulcrum::get('bool.setting'));
    }

    public function test_set_immutable_setting_command()
    {
        $setting = Setting::create(['key' => 'immutable.key', 'type' => 'string', 'immutable' => true]);
        $setting->defaultValue()->create(['value' => 'old-value']);

        // Without force
        $this->artisan('fulcrum:set', [
            'key' => 'immutable.key',
            'value' => 'new-value',
        ])
            ->expectsOutput('Failed to set setting: Setting is immutable. Changes are not allowed.')
            ->assertExitCode(1);

        // With force
        $this->artisan('fulcrum:set', [
            'key' => 'immutable.key',
            'value' => 'new-value',
            '--force' => true,
        ])
            ->expectsOutput('Setting [immutable.key] updated successfully.')
            ->assertExitCode(0);

        $this->assertEquals('new-value', \GaiaTools\FulcrumSettings\Facades\Fulcrum::get('immutable.key'));
    }

    public function test_list_settings_command()
    {
        Config::set('fulcrum.multi_tenancy.enabled', true);
        Setting::create(['key' => 'a.key', 'type' => 'string']);
        Setting::create(['key' => 'b.key', 'type' => 'boolean', 'masked' => true]);

        $this->artisan('fulcrum:list')
            ->expectsTable(
                ['Key', 'Type', 'Tenant ID', 'Masked', 'Immutable', 'Description'],
                [
                    ['a.key', 'string', '-', 'No', 'No', '-'],
                    ['b.key', 'boolean', '-', 'Yes', 'No', '-'],
                ]
            )
            ->assertExitCode(0);
    }

    public function test_list_settings_command_without_multi_tenancy()
    {
        Config::set('fulcrum.multi_tenancy.enabled', false);
        Setting::create(['key' => 'a.key', 'type' => 'string']);

        $this->artisan('fulcrum:list')
            ->expectsTable(
                ['Key', 'Type', 'Masked', 'Immutable', 'Description'],
                [
                    ['a.key', 'string', 'No', 'No', '-'],
                ]
            )
            ->assertExitCode(0);
    }

    public function test_list_settings_with_tenant()
    {
        Setting::create(['key' => 'global.key', 'type' => 'string']);
        Setting::create(['key' => 'tenant.key', 'type' => 'string', 'tenant_id' => 'tenant-123']);

        $this->artisan('fulcrum:list', ['--tenant' => 'tenant-123'])
            ->expectsTable(
                ['Key', 'Type', 'Tenant ID', 'Masked', 'Immutable', 'Description'],
                [
                    ['tenant.key', 'string', 'tenant-123', 'No', 'No', '-'],
                ]
            )
            ->assertExitCode(0);
    }

    public function test_list_settings_with_no_tenants()
    {
        Setting::create(['key' => 'global.key', 'type' => 'string']);
        Setting::create(['key' => 'tenant.key', 'type' => 'string', 'tenant_id' => 'tenant-123']);

        $this->artisan('fulcrum:list', ['--no-tenants' => true])
            ->expectsTable(
                ['Key', 'Type', 'Tenant ID', 'Masked', 'Immutable', 'Description'],
                [
                    ['global.key', 'string', '-', 'No', 'No', '-'],
                ]
            )
            ->assertExitCode(0);
    }
}
