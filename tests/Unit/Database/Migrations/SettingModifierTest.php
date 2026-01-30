<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Unit\Database\Migrations;

use GaiaTools\FulcrumSettings\Database\Migrations\SettingModifier;
use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\Exceptions\InvalidSettingValueException;
use GaiaTools\FulcrumSettings\Exceptions\InvalidTypeHandlerException;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use GaiaTools\FulcrumSettings\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SettingModifierTest extends TestCase
{
    use RefreshDatabase;

    protected Setting $setting;

    protected function setUp(): void
    {
        parent::setUp();
        FulcrumContext::force(true);
        $this->setting = Setting::create([
            'key' => 'test.setting',
            'type' => SettingType::STRING,
        ]);
        $this->setting->defaultValue()->create([
            'valuable_type' => Setting::class,
            'valuable_id' => $this->setting->id,
            'value' => 'old_val',
        ]);
    }

    public function test_it_updates_basic_properties()
    {
        $modifier = new SettingModifier($this->setting);
        $modifier->updateDescription('new desc')
            ->makeMasked()
            ->makeImmutable()
            ->updateTenant('tenant_1');

        $modifier->apply();

        $this->setting->refresh();
        $this->assertEquals('new desc', $this->setting->description);
        $this->assertTrue($this->setting->masked);
        $this->assertTrue($this->setting->immutable);
        $this->assertEquals('tenant_1', $this->setting->tenant_id);
    }

    public function test_it_removes_masking_and_immutability()
    {
        $this->setting->update(['masked' => true, 'immutable' => true]);

        $modifier = new SettingModifier($this->setting);
        $modifier->removeMasking()
            ->makeMutable();

        $modifier->apply();

        $this->setting->refresh();
        $this->assertFalse($this->setting->masked);
        $this->assertFalse($this->setting->immutable);
    }

    public function test_it_updates_type()
    {
        $this->setting->defaultValue->update(['value' => '123']);
        $this->setting->refresh();

        $modifier = new SettingModifier($this->setting);
        $modifier->updateType(SettingType::INTEGER);
        $modifier->apply();

        $this->assertEquals(SettingType::INTEGER, $this->setting->refresh()->type);
        $this->assertEquals(123, $this->setting->defaultValue->value);
    }

    public function test_it_throws_exception_for_invalid_type()
    {
        $modifier = new SettingModifier($this->setting);

        $this->expectException(InvalidTypeHandlerException::class);
        $modifier->updateType('non_existent_type');
    }

    public function test_it_updates_default_value()
    {
        $modifier = new SettingModifier($this->setting);
        $modifier->updateDefault('new_val');
        $modifier->apply();

        $this->assertEquals('new_val', $this->setting->refresh()->defaultValue->value);
    }

    public function test_it_creates_default_value_if_not_exists()
    {
        $this->setting->defaultValue->delete();
        $this->setting->unsetRelation('defaultValue');

        $modifier = new SettingModifier($this->setting);
        $modifier->updateDefault('brand_new_val');
        $modifier->apply();

        $this->assertEquals('brand_new_val', $this->setting->refresh()->defaultValue->value);
    }

    public function test_it_validates_new_default_value()
    {
        $modifier = new SettingModifier($this->setting);
        $modifier->updateType(SettingType::INTEGER);
        $modifier->updateDefault('not an integer');

        $this->expectException(InvalidSettingValueException::class);
        $modifier->apply();
    }

    public function test_it_manages_rules()
    {
        $this->setting->rules()->create(['name' => 'rule1', 'priority' => 1]);

        $modifier = new SettingModifier($this->setting);
        $modifier->addRule(function ($rule) {
            $rule->name('rule2')->then('val2');
        })
            ->removeRule('rule1');

        $modifier->apply();

        $this->assertCount(1, $this->setting->rules);
        $this->assertEquals('rule2', $this->setting->rules->first()->name);
    }

    public function test_it_modifies_existing_rule()
    {
        $this->setting->rules()->create(['name' => 'rule1', 'priority' => 1]);

        $modifier = new SettingModifier($this->setting);
        $modifier->modifyRule('rule1', function ($rule) {
            $rule->updatePriority(10);
        });

        $modifier->apply();

        $this->assertEquals(10, $this->setting->rules()->where('name', 'rule1')->first()->priority);
    }

    public function test_it_skips_modifying_non_existent_rule()
    {
        $modifier = new SettingModifier($this->setting);
        $modifier->modifyRule('non_existent', function ($rule) {
            $rule->updatePriority(10);
        });

        // Should not throw exception
        $modifier->apply();
        $this->assertCount(0, $this->setting->rules);
    }

    public function test_it_clears_rules()
    {
        $this->setting->rules()->create(['name' => 'rule1', 'priority' => 1]);
        $this->setting->rules()->create(['name' => 'rule2', 'priority' => 2]);

        $modifier = new SettingModifier($this->setting);
        $modifier->clearRules();
        $modifier->apply();

        $this->assertCount(0, $this->setting->refresh()->rules);
    }

    public function test_it_validates_existing_default_value_against_new_type()
    {
        $this->setting->update(['type' => SettingType::JSON]);
        $this->setting->defaultValue->delete();
        $this->setting->unsetRelation('defaultValue');

        // Manual insert to avoid accessor/mutator issues during setup
        \Illuminate\Support\Facades\DB::table('setting_values')->insert([
            'valuable_type' => Setting::class,
            'valuable_id' => $this->setting->id,
            'value' => 'not-json', // Raw string that is not JSON
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $modifier = new SettingModifier($this->setting->fresh());
        $modifier->updateType(SettingType::INTEGER);

        $this->expectException(InvalidSettingValueException::class);
        $modifier->apply();
    }

    public function test_it_returns_the_setting_model()
    {
        $modifier = new SettingModifier($this->setting);
        $this->assertSame($this->setting, $modifier->getSetting());
    }
}
