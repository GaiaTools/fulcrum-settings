<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Unit\Database\Migrations;

use GaiaTools\FulcrumSettings\Database\Migrations\VariantModifier;
use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Models\SettingRuleRolloutVariant;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use GaiaTools\FulcrumSettings\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;

class VariantModifierTest extends TestCase
{
    use RefreshDatabase;

    protected SettingRuleRolloutVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        FulcrumContext::force(true);
        $setting = Setting::create(['key' => 'test', 'type' => SettingType::STRING]);
        $rule = $setting->rules()->create(['name' => 'rule', 'priority' => 1]);
        $this->variant = $rule->rolloutVariants()->create(['name' => 'v1', 'weight' => 50000]);
        $this->variant->value()->create([
            'valuable_type' => SettingRuleRolloutVariant::class,
            'valuable_id' => $this->variant->id,
            'value' => 'old_val',
        ]);
    }

    public function test_it_updates_properties()
    {
        $modifier = new VariantModifier($this->variant);
        $modifier->updateName('new_name')
            ->updateWeight(75.5)
            ->updateValue('new_val');

        $modifier->apply();

        $this->variant->refresh();
        $this->assertEquals('new_name', $this->variant->name);
        $this->assertEquals(75500, $this->variant->weight);
        $this->assertEquals('new_val', $this->variant->value->value);
    }

    public function test_it_creates_value_if_not_exists()
    {
        $this->variant->value->delete();
        $this->variant->unsetRelation('value');

        $modifier = new VariantModifier($this->variant);
        $modifier->updateValue('brand_new_val');
        $modifier->apply();

        $this->assertEquals('brand_new_val', $this->variant->refresh()->value->value);
    }

    public function test_it_can_remove_value()
    {
        $modifier = new VariantModifier($this->variant);
        $modifier->removeValue();
        $modifier->apply();

        $this->assertNull($this->variant->refresh()->value);
    }

    public function test_it_validates_weight()
    {
        $modifier = new VariantModifier($this->variant);

        try {
            $modifier->updateWeight(-1);
            $this->fail('Should have thrown an exception for negative weight');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('must be between 0 and 100', $e->getMessage());
        }

        try {
            $modifier->updateWeight(101);
            $this->fail('Should have thrown an exception for weight > 100');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('must be between 0 and 100', $e->getMessage());
        }
    }

    public function test_it_returns_the_variant_model()
    {
        $modifier = new VariantModifier($this->variant);
        $this->assertSame($this->variant, $modifier->getVariant());
    }
}
