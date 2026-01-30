<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Feature\Console;

use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Tests\TestCase;
use Illuminate\Support\Facades\Config;

class SetSettingWizardMultiTenancyTest extends TestCase
{
    public function test_wizard_prompts_for_tenant_id_when_multi_tenancy_is_enabled()
    {
        Config::set('fulcrum.multi_tenancy.enabled', true);

        $this->artisan('fulcrum:set')
            ->expectsQuestion('Enter the setting key', 'tenancy.test')
            ->expectsQuestion('Enter tenant ID (optional, leave empty for global)', 'tenant-123')
            ->expectsOutput('Creating new setting [tenancy.test] for tenant [tenant-123]')
            ->expectsChoice('Select setting type', 'string', array_column(SettingType::cases(), 'value'))
            ->expectsQuestion('Enter description', '')
            ->expectsQuestion('Is this setting sensitive/masked?', false)
            ->expectsQuestion('Is this setting immutable?', false)
            ->expectsQuestion('Enter default value (string)', 'val')
            ->expectsQuestion('Do you want to manage targeting rules for this setting?', false)
            ->assertExitCode(0);

        $setting = Setting::withoutGlobalScope(\GaiaTools\FulcrumSettings\Models\Scopes\TenantScope::class)->where('key', 'tenancy.test')->first();
        $this->assertEquals('tenant-123', $setting->tenant_id);
    }

    public function test_wizard_does_not_prompt_for_tenant_id_when_multi_tenancy_is_disabled()
    {
        Config::set('fulcrum.multi_tenancy.enabled', false);

        $this->artisan('fulcrum:set')
            ->expectsQuestion('Enter the setting key', 'no.tenancy.test')
            // Note: We don't expect 'Enter tenant ID (optional, leave empty for global)' here
            ->expectsOutput('Creating new setting [no.tenancy.test] for tenant [global]')
            ->expectsChoice('Select setting type', 'string', array_column(SettingType::cases(), 'value'))
            ->expectsQuestion('Enter description', '')
            ->expectsQuestion('Is this setting sensitive/masked?', false)
            ->expectsQuestion('Is this setting immutable?', false)
            ->expectsQuestion('Enter default value (string)', 'val')
            ->expectsQuestion('Do you want to manage targeting rules for this setting?', false)
            ->assertExitCode(0);

        $setting = Setting::withoutGlobalScope(\GaiaTools\FulcrumSettings\Models\Scopes\TenantScope::class)->where('key', 'no.tenancy.test')->first();
        $this->assertNull($setting->tenant_id);
    }

    public function test_tenant_id_is_ignored_when_multi_tenancy_is_disabled_even_if_passed_as_option()
    {
        Config::set('fulcrum.multi_tenancy.enabled', false);

        $this->artisan('fulcrum:set', [
            'key' => 'ignore.tenant.test',
            'value' => 'val',
            '--tenant' => 'should-be-ignored',
        ])
            ->expectsOutput('Setting [ignore.tenant.test] updated successfully.')
            ->assertExitCode(0);

        $setting = Setting::withoutGlobalScope(\GaiaTools\FulcrumSettings\Models\Scopes\TenantScope::class)
            ->where('key', 'ignore.tenant.test')
            ->first();

        $this->assertNotNull($setting);
        $this->assertNull($setting->tenant_id);

        // Also check default value record
        $valueRecord = $setting->defaultValue;
        $this->assertNull($valueRecord->tenant_id);
    }
}
