<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Unit\Database\Migrations;

use GaiaTools\FulcrumSettings\Database\Migrations\RuleDefinition;
use GaiaTools\FulcrumSettings\Enums\ComparisonOperator;
use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use GaiaTools\FulcrumSettings\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RuleDefinitionTest extends TestCase
{
    use RefreshDatabase;

    protected Setting $setting;

    protected function setUp(): void
    {
        parent::setUp();
        FulcrumContext::force(true);
        $this->setting = Setting::create([
            'key' => 'test.key',
            'type' => SettingType::STRING,
        ]);
    }

    public function test_it_sets_basic_properties()
    {
        $definition = new RuleDefinition;
        $definition->name('beta_users')
            ->priority(10)
            ->then('beta_value');

        $rule = $definition->createFor($this->setting);

        $this->assertEquals('beta_users', $rule->name);
        $this->assertEquals(10, $rule->priority);
        $this->assertEquals('beta_value', $rule->value->value);
    }

    public function test_it_sets_rollout_salt()
    {
        $definition = new RuleDefinition;
        $definition->name('rollout_rule')
            ->salt('custom_salt')
            ->rollout(function ($rollout) {
                $rollout->variant('v1', 100, 'val');
            });

        $rule = $definition->createFor($this->setting);

        $this->assertEquals('custom_salt', $rule->rollout_salt);
    }

    public function test_it_adds_conditions()
    {
        $definition = new RuleDefinition;
        $definition->when('user.plan', 'equals', 'premium')
            ->whereTrue('user.is_active')
            ->whereEquals('user.type', 'beta');

        $rule = $definition->createFor($this->setting);

        $this->assertCount(3, $rule->conditions);
        $this->assertEquals('user.plan', $rule->conditions[0]->attribute);
        $this->assertEquals(ComparisonOperator::EQUALS, $rule->conditions[0]->operator);
        $this->assertEquals('premium', $rule->conditions[0]->value);

        $this->assertEquals('user.is_active', $rule->conditions[1]->attribute);
        $this->assertEquals(ComparisonOperator::IS_TRUE, $rule->conditions[1]->operator);

        $this->assertEquals('user.type', $rule->conditions[2]->attribute);
        $this->assertEquals(ComparisonOperator::EQUALS, $rule->conditions[2]->operator);
        $this->assertEquals('beta', $rule->conditions[2]->value);
    }

    public function test_it_supports_time_bounds()
    {
        $start = now()->startOfMinute();
        $end = now()->addDays(7)->startOfMinute();

        $definition = new RuleDefinition;
        $definition->between($start, $end);

        $rule = $definition->createFor($this->setting);

        $this->assertEquals($start->toDateTimeString(), $rule->starts_at->toDateTimeString());
        $this->assertEquals($end->toDateTimeString(), $rule->ends_at->toDateTimeString());
    }

    public function test_it_can_define_rollout()
    {
        $definition = new RuleDefinition;
        $definition->rollout(function ($rollout) {
            $rollout->fiftyFifty('control_val', 'treatment_val');
        });

        $rule = $definition->createFor($this->setting);

        $this->assertTrue($rule->hasRolloutVariants());
        $this->assertCount(2, $rule->rolloutVariants);
        $this->assertEquals('control_val', $rule->rolloutVariants[0]->value->value);
        $this->assertEquals('treatment_val', $rule->rolloutVariants[1]->value->value);
    }

    public function test_it_throws_when_combining_then_and_rollout()
    {
        $definition = new RuleDefinition;
        $definition->then('val');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot add rollout variants to a rule with a direct value');

        $definition->rollout(function ($rollout) {
            $rollout->variant('v1', 100);
        });
    }

    public function test_it_throws_exception_when_setting_both_rollout_and_then_on_rule_definition()
    {
        $rule = new RuleDefinition;

        $rule->rollout(function ($rollout) {
            $rollout->variant('v1', 100, 'val');
        });

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot set a direct value on a rule with rollout variants');

        $rule->then('direct-value');
    }

    public function test_it_adds_various_conditions()
    {
        $definition = new RuleDefinition;
        $definition->whereNotEquals('a', 'v1')
            ->whereInSegment('b', 's1')
            ->whereNotInSegment('c', 's2')
            ->whereFalse('d')
            ->whereNull('e')
            ->whereNotNull('f')
            ->whereContainsAny('g', [1, 2])
            ->whereNotContainsAny('h', [3, 4])
            ->whereStartsWithAny('i', ['pre'])
            ->whereEndsWithAny('j', ['suf'])
            ->whereMatchesRegex('k', '/^foo/')
            ->whereNumberEquals('l', 10)
            ->whereNumberGreaterThan('m', 10)
            ->whereNumberGreaterThanOrEqual('n', 10)
            ->whereNumberLessThan('o', 10)
            ->whereNumberLessThanOrEqual('p', 10)
            ->whereNumberBetween('q', 1, 10)
            ->whereDateEquals('r', '2025-01-01')
            ->whereDateAfter('s', '2025-01-01')
            ->whereDateBefore('t', '2025-01-01')
            ->whereDateBetween('u', '2025-01-01', '2025-01-02')
            ->whereVersionEquals('v', '1.0.0')
            ->whereVersionGreaterThan('w', '1.0.0')
            ->whereVersionGreaterThanOrEqual('x', '1.0.0')
            ->whereVersionLessThan('y', '1.0.0')
            ->whereTimeBetween('09:00', '17:00')
            ->whereDayOfWeek(['Monday', 'Tuesday'])
            ->whereBusinessDay()
            ->whereHoliday()
            ->whereCron('* * * * *')
            ->startsAt(now()->subDay())
            ->endsAt(now()->addDay());

        $rule = $definition->createFor($this->setting);
        $this->assertCount(30, $rule->conditions);
        $this->assertNotNull($rule->starts_at);
        $this->assertNotNull($rule->ends_at);
        $this->assertTrue($definition->getConditions() !== []);
        $this->assertFalse($definition->isRollout());

        $this->assertEquals(ComparisonOperator::TIME_BETWEEN, $rule->conditions[25]->operator);
        $this->assertEquals(['09:00', '17:00'], $rule->conditions[25]->value);

        $this->assertEquals(ComparisonOperator::DAY_OF_WEEK, $rule->conditions[26]->operator);
        $this->assertEquals(['Monday', 'Tuesday'], $rule->conditions[26]->value);

        $this->assertEquals(ComparisonOperator::IS_BUSINESS_DAY, $rule->conditions[27]->operator);

        $this->assertEquals(ComparisonOperator::IS_HOLIDAY, $rule->conditions[28]->operator);

        $this->assertEquals(ComparisonOperator::SCHEDULE_CRON, $rule->conditions[29]->operator);
        $this->assertEquals('* * * * *', $rule->conditions[29]->value);
    }
}
