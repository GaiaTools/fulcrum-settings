<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Feature\Console;

use GaiaTools\FulcrumSettings\Enums\ComparisonOperator;
use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Tests\TestCase;

class SetSettingWizardTest extends TestCase
{
    public function test_interactive_wizard_creates_setting_with_rule_and_rollout()
    {
        $this->artisan('fulcrum:set')
            ->expectsOutput('--- Fulcrum Setting Wizard ---')
            ->expectsQuestion('Enter the setting key', 'wizard.test')
            ->expectsQuestion('Enter tenant ID (optional, leave empty for global)', '')
            ->expectsOutput('Creating new setting [wizard.test] for tenant [global]')
            ->expectsChoice('Select setting type', 'string', array_column(SettingType::cases(), 'value'))
            ->expectsQuestion('Enter description', 'Wizard description')
            ->expectsQuestion('Is this setting sensitive/masked?', false)
            ->expectsQuestion('Is this setting immutable?', false)
            ->expectsQuestion('Enter default value (string)', 'default-val')
            ->expectsQuestion('Do you want to manage targeting rules for this setting?', true)
            // Rule Action
            ->expectsChoice('Action', 'Add Rule', ['Add Rule', 'Edit Rule', 'Delete Rule', 'Done'])
            ->expectsQuestion('Rule name', 'Test Rule')
            ->expectsQuestion('Priority (lower runs first)', '10')
            // Condition Action
            ->expectsChoice('Conditions Action', 'Add Condition', ['Add Condition', 'Delete Condition', 'Done'])
            ->expectsQuestion('Attribute (e.g., user_id, email, segment)', 'user_id')
            ->expectsChoice('Operator', 'equals', array_column(ComparisonOperator::cases(), 'value'))
            ->expectsQuestion('Enter value', '1')
            ->expectsChoice('Conditions Action', 'Done', ['Add Condition', 'Delete Condition', 'Done'])
            // Rollout?
            ->expectsQuestion('Is this a percentage rollout?', true)
            // Rollout Action
            ->expectsChoice('Rollout Action', 'Add Variant', ['Add Variant', 'Delete Variant', 'Done'])
            ->expectsQuestion('Variant name', 'A')
            ->expectsQuestion('Weight percentage (0-100)', '50')
            ->expectsQuestion('Variant value (string)', 'variant-a')
            ->expectsChoice('Rollout Action', 'Done', ['Add Variant', 'Delete Variant', 'Done'])
            // Back to Rule Action
            ->expectsChoice('Action', 'Done', ['Add Rule', 'Edit Rule', 'Delete Rule', 'Done'])
            ->expectsOutput('Setting [wizard.test] saved successfully.')
            ->assertExitCode(0);

        $setting = Setting::withoutGlobalScope(\GaiaTools\FulcrumSettings\Models\Scopes\TenantScope::class)->where('key', 'wizard.test')->first();
        $this->assertNotNull($setting);
        $this->assertEquals('Wizard description', $setting->description);
        $this->assertEquals('default-val', $setting->getDefaultValue());

        $rule = $setting->rules()->withoutGlobalScope(\GaiaTools\FulcrumSettings\Models\Scopes\TenantScope::class)->first();
        $this->assertNotNull($rule);
        $this->assertEquals('Test Rule', $rule->name);

        $condition = $rule->conditions()->withoutGlobalScope(\GaiaTools\FulcrumSettings\Models\Scopes\TenantScope::class)->first();
        $this->assertNotNull($condition);
        $this->assertEquals('user_id', $condition->attribute);
        $this->assertEquals('1', $condition->value);

        $variant = $rule->rolloutVariants()->withoutGlobalScope(\GaiaTools\FulcrumSettings\Models\Scopes\TenantScope::class)->first();
        $this->assertNotNull($variant);
        $this->assertEquals('A', $variant->name);
        $this->assertEquals(50.0, $variant->weight_percentage);
        $this->assertEquals('variant-a', $variant->getValue());
    }
}
