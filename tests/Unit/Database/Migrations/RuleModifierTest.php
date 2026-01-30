<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Unit\Database\Migrations;

use GaiaTools\FulcrumSettings\Database\Migrations\RuleModifier;
use GaiaTools\FulcrumSettings\Enums\ComparisonOperator;
use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Models\SettingRule;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use GaiaTools\FulcrumSettings\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RuleModifierTest extends TestCase
{
    use RefreshDatabase;

    protected SettingRule $rule;

    protected function setUp(): void
    {
        parent::setUp();
        FulcrumContext::force(true);
        $setting = Setting::create(['key' => 'test', 'type' => SettingType::STRING]);
        $this->rule = $setting->rules()->create(['name' => 'old_name', 'priority' => 1]);
        $this->rule->value()->create([
            'valuable_type' => SettingRule::class,
            'valuable_id' => $this->rule->id,
            'value' => 'old_val',
        ]);
    }

    public function test_it_updates_basic_properties()
    {
        $modifier = new RuleModifier($this->rule);
        $modifier->updateName('new_name')
            ->updatePriority(10)
            ->updateValue('new_val')
            ->updateSalt('new_salt');

        $modifier->apply();

        $this->rule->refresh();
        $this->assertEquals('new_name', $this->rule->name);
        $this->assertEquals(10, $this->rule->priority);
        $this->assertEquals('new_val', $this->rule->value->value);
    }

    public function test_it_updates_existing_value_model_in_modifier()
    {
        $modifier = new RuleModifier($this->rule);
        $modifier->convertToDirectValue('updated-val');
        $modifier->apply();

        $this->assertEquals('updated-val', $this->rule->fresh()->value->value);
    }

    public function test_it_updates_time_bounds()
    {
        $start = now()->addDay()->startOfMinute();
        $end = now()->addDays(2)->startOfMinute();

        $modifier = new RuleModifier($this->rule);
        $modifier->updateTimeBounds($start, $end);
        $modifier->apply();

        $this->rule->refresh();
        $this->assertEquals($start->toDateTimeString(), $this->rule->starts_at->toDateTimeString());
        $this->assertEquals($end->toDateTimeString(), $this->rule->ends_at->toDateTimeString());

        $modifier->removeTimeBounds();
        $modifier->apply();
        $this->rule->refresh();
        $this->assertNull($this->rule->starts_at);
        $this->assertNull($this->rule->ends_at);
    }

    public function test_it_manages_conditions()
    {
        $this->rule->conditions()->create([
            'attribute' => 'attr1',
            'operator' => ComparisonOperator::EQUALS->value,
            'value' => 'val1',
        ]);

        $modifier = new RuleModifier($this->rule);
        $modifier->addCondition('attr2', 'is_true', null)
            ->removeCondition('attr1');
        $modifier->apply();

        $this->rule->refresh();
        $this->assertCount(1, $this->rule->conditions);
        $this->assertEquals('attr2', $this->rule->conditions[0]->attribute);
    }

    public function test_it_can_clear_conditions()
    {
        $this->rule->conditions()->create(['attribute' => 'a', 'operator' => 'equals', 'value' => 'v']);

        $modifier = new RuleModifier($this->rule);
        $modifier->clearConditions();
        $modifier->apply();

        $this->assertCount(0, $this->rule->refresh()->conditions);
    }

    public function test_it_can_replace_rollout()
    {
        $modifier = new RuleModifier($this->rule);
        $modifier->replaceRollout(function ($rollout) {
            $rollout->fiftyFifty('a', 'b');
        });
        $modifier->apply();

        $this->rule->refresh();
        $this->assertTrue($this->rule->hasRolloutVariants());
        $this->assertCount(2, $this->rule->rolloutVariants);
        // Direct value should be gone
        $this->assertNull($this->rule->value);
    }

    public function test_it_can_convert_to_direct_value()
    {
        // First make it a rollout
        $this->rule->rolloutVariants()->create(['name' => 'v1', 'weight' => 1000]);

        $modifier = new RuleModifier($this->rule);
        $modifier->convertToDirectValue('static_val');
        $modifier->apply();

        $this->rule->refresh();
        $this->assertFalse($this->rule->hasRolloutVariants());
        $this->assertEquals('static_val', $this->rule->value->value);
    }

    public function test_it_can_modify_conditions_and_variants()
    {
        $this->rule->conditions()->create([
            'attribute' => 'attr1',
            'operator' => 'equals',
            'value' => 'val1',
        ]);
        $this->rule->rolloutVariants()->create(['name' => 'v1', 'weight' => 50000]);

        $modifier = new RuleModifier($this->rule);
        $modifier->modifyCondition('attr1', function ($c) {
            $c->updateValue('new_val');
        })->modifyVariant('v1', function ($v) {
            $v->updateWeight(75.0);
        })->regenerateSalt();

        $modifier->apply();

        $this->rule->refresh();
        $this->assertEquals('new_val', $this->rule->conditions[0]->value);
        $this->assertEquals(75000, $this->rule->rolloutVariants[0]->weight);
        $this->assertNotNull($this->rule->rollout_salt);
        $this->assertNotNull($modifier->getRule());
    }

    public function test_it_can_remove_variants()
    {
        $this->rule->rolloutVariants()->create(['name' => 'v1', 'weight' => 50000]);
        $this->rule->rolloutVariants()->create(['name' => 'v2', 'weight' => 50000]);

        $modifier = new RuleModifier($this->rule);
        $modifier->removeVariant('v1');
        $modifier->apply();

        $this->assertCount(1, $this->rule->refresh()->rolloutVariants);
        $this->assertEquals('v2', $this->rule->rolloutVariants[0]->name);
    }

    public function test_it_throws_when_updating_value_on_rollout_rule()
    {
        $this->rule->rolloutVariants()->create(['name' => 'v1', 'weight' => 1000]);

        $modifier = new RuleModifier($this->rule);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot set a direct value on a rule with rollout variants');

        $modifier->updateValue('new_val');
    }

    public function test_it_throws_when_replacing_rollout_with_value_queued()
    {
        $modifier = new RuleModifier($this->rule);
        $modifier->updateValue('new_val');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot add rollout variants when a direct value is set');

        $modifier->replaceRollout(function ($r) {
            $r->fiftyFifty('a', 'b');
        });
    }

    public function test_it_deletes_existing_direct_value_when_creating_new_rollout_in_modifier()
    {
        $modifier = new RuleModifier($this->rule);
        $modifier->replaceRollout(function ($rollout) {
            $rollout->variant('v1', 100, 'val');
        });
        $modifier->apply();

        $this->rule->refresh();
        $this->assertNull($this->rule->value);
        $this->assertCount(1, $this->rule->rolloutVariants);
    }

    public function test_it_updates_starts_at_and_ends_at_separately()
    {
        $start = now()->addDay()->startOfMinute();
        $end = now()->addDays(2)->startOfMinute();

        $modifier = new RuleModifier($this->rule);
        $modifier->updateStartsAt($start)->updateEndsAt($end);
        $modifier->apply();

        $this->rule->refresh();
        $this->assertEquals($start->toDateTimeString(), $this->rule->starts_at->toDateTimeString());
        $this->assertEquals($end->toDateTimeString(), $this->rule->ends_at->toDateTimeString());
    }

    public function test_it_handles_string_dates_and_null_in_time_bounds()
    {
        $modifier = new RuleModifier($this->rule);
        $modifier->updateStartsAt('2026-01-01 10:00:00')
            ->updateEndsAt('');
        $modifier->apply();

        $this->rule->refresh();
        $this->assertEquals('2026-01-01 10:00:00', $this->rule->starts_at->toDateTimeString());
        $this->assertNull($this->rule->ends_at);
    }

    public function test_it_handles_missing_condition_or_variant_gracefully()
    {
        $modifier = new RuleModifier($this->rule);
        // These should not throw even if they don't exist
        $modifier->modifyCondition('non_existent', function ($c) {})
            ->modifyVariant('non_existent', function ($v) {})
            ->removeVariant('non_existent');

        $modifier->apply();
        $this->assertTrue(true); // If no exception, it passed
    }

    public function test_it_creates_value_if_missing_when_updating_value()
    {
        $this->rule->value->delete();
        $this->rule->refresh();
        $this->assertNull($this->rule->value);

        $modifier = new RuleModifier($this->rule);
        $modifier->updateValue('fresh_val');
        $modifier->apply();

        $this->rule->refresh();
        $this->assertEquals('fresh_val', $this->rule->value->value);
    }

    public function test_it_handles_comparison_operator_enum_in_add_condition()
    {
        $modifier = new RuleModifier($this->rule);
        $modifier->addCondition('attr', ComparisonOperator::EQUALS, 10);
        $modifier->apply();

        $this->rule->refresh();
        $this->assertEquals('equals', $this->rule->conditions[0]->operator->value);
    }
}
