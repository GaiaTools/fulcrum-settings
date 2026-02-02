<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Unit\Services;

use GaiaTools\FulcrumSettings\Contracts\GeoResolver;
use GaiaTools\FulcrumSettings\Contracts\HolidayResolver;
use GaiaTools\FulcrumSettings\Contracts\SegmentDriver;
use GaiaTools\FulcrumSettings\Contracts\UserAgentResolver;
use GaiaTools\FulcrumSettings\Enums\ComparisonOperator;
use GaiaTools\FulcrumSettings\Models\SettingRuleCondition;
use GaiaTools\FulcrumSettings\Services\RuleEvaluator;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use GaiaTools\FulcrumSettings\Tests\TestCase;
use Mockery;

class CustomAttributeTargetingTest extends TestCase
{
    protected RuleEvaluator $evaluator;

    protected $segmentDriver;

    protected $geoResolver;

    protected $uaResolver;

    protected $holidayResolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->segmentDriver = Mockery::mock(SegmentDriver::class);
        $this->geoResolver = Mockery::mock(GeoResolver::class);
        $this->uaResolver = Mockery::mock(UserAgentResolver::class);
        $this->holidayResolver = Mockery::mock(HolidayResolver::class);
        $this->app->instance(GeoResolver::class, $this->geoResolver);
        $this->app->instance(UserAgentResolver::class, $this->uaResolver);
        $this->evaluator = new RuleEvaluator(
            $this->segmentDriver,
            $this->holidayResolver
        );
    }

    public function test_it_targets_custom_attributes_in_array_scope(): void
    {
        $condition = new SettingRuleCondition([
            'attribute' => 'custom_attr',
            'operator' => ComparisonOperator::EQUALS,
            'value' => 'active',
        ]);

        $this->assertTrue($this->evaluator->evaluateCondition($condition, ['custom_attr' => 'active']));
        $this->assertFalse($this->evaluator->evaluateCondition($condition, ['custom_attr' => 'inactive']));
    }

    public function test_it_targets_nested_custom_attributes_in_array_scope(): void
    {
        $condition = new SettingRuleCondition([
            'attribute' => 'user.metadata.plan',
            'operator' => ComparisonOperator::EQUALS,
            'value' => 'premium',
        ]);

        $scope = [
            'user' => [
                'metadata' => [
                    'plan' => 'premium',
                ],
            ],
        ];

        $this->assertTrue($this->evaluator->evaluateCondition($condition, $scope));
    }

    public function test_it_targets_custom_attributes_in_dto_object_scope(): void
    {
        $dto = new class
        {
            public string $tier = 'gold';

            public int $score = 100;
        };

        $conditionTier = new SettingRuleCondition([
            'attribute' => 'tier',
            'operator' => ComparisonOperator::EQUALS,
            'value' => 'gold',
        ]);

        $conditionScore = new SettingRuleCondition([
            'attribute' => 'score',
            'operator' => ComparisonOperator::NUMBER_EQUALS,
            'value' => 100,
        ]);

        $this->assertTrue($this->evaluator->evaluateCondition($conditionTier, $dto));
        $this->assertTrue($this->evaluator->evaluateCondition($conditionScore, $dto));
    }

    public function test_it_targets_custom_attributes_from_fulcrum_context(): void
    {
        $condition = new SettingRuleCondition([
            'attribute' => 'context_attr',
            'operator' => ComparisonOperator::EQUALS,
            'value' => 'on',
        ]);

        FulcrumContext::set('context_attr', 'on');

        $this->assertTrue($this->evaluator->evaluateCondition($condition));

        FulcrumContext::set('context_attr', 'off');
        $this->assertFalse($this->evaluator->evaluateCondition($condition));
    }
}
