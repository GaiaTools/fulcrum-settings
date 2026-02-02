<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Unit\Database\Migrations;

use GaiaTools\FulcrumSettings\Database\Migrations\ConditionModifier;
use GaiaTools\FulcrumSettings\Enums\ComparisonOperator;
use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Models\SettingRuleCondition;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use GaiaTools\FulcrumSettings\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ConditionModifierTest extends TestCase
{
    use RefreshDatabase;

    protected SettingRuleCondition $condition;

    protected function setUp(): void
    {
        parent::setUp();
        FulcrumContext::force(true);
        $setting = Setting::create(['key' => 'test', 'type' => SettingType::STRING]);
        $rule = $setting->rules()->create(['name' => 'rule', 'priority' => 1]);
        $this->condition = $rule->conditions()->create([
            'attribute' => 'old_attr',
            'operator' => ComparisonOperator::EQUALS->value,
            'value' => 'old_val',
        ]);
    }

    public function test_it_updates_properties()
    {
        $modifier = new ConditionModifier($this->condition);
        $modifier->updateAttribute('new_attr')
            ->updateOperator(ComparisonOperator::NOT_EQUALS)
            ->updateValue('new_val');

        $modifier->apply();

        $this->condition->refresh();
        $this->assertEquals('new_attr', $this->condition->attribute);
        $this->assertEquals(ComparisonOperator::NOT_EQUALS, $this->condition->operator);
        $this->assertEquals('new_val', $this->condition->value);
    }

    public function test_it_updates_with_string_operator()
    {
        $modifier = new ConditionModifier($this->condition);
        $modifier->updateOperator('is_true');
        $modifier->apply();

        $this->assertEquals(ComparisonOperator::IS_TRUE, $this->condition->refresh()->operator);
    }

    public function test_it_updates_multiple_at_once()
    {
        $modifier = new ConditionModifier($this->condition);
        $modifier->update('attr', ComparisonOperator::CONTAINS_ANY, [1, 2]);
        $modifier->apply();

        $this->condition->refresh();
        $this->assertEquals('attr', $this->condition->attribute);
        $this->assertEquals(ComparisonOperator::CONTAINS_ANY, $this->condition->operator);
        $this->assertEquals([1, 2], $this->condition->value);
    }

    public function test_it_returns_the_condition_model()
    {
        $modifier = new ConditionModifier($this->condition);
        $this->assertSame($this->condition, $modifier->getCondition());
    }
}
