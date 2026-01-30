<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Unit\Services;

use GaiaTools\FulcrumSettings\Contracts\GeoResolver;
use GaiaTools\FulcrumSettings\Contracts\HolidayResolver;
use GaiaTools\FulcrumSettings\Contracts\SegmentDriver;
use GaiaTools\FulcrumSettings\Contracts\UserAgentResolver;
use GaiaTools\FulcrumSettings\Enums\ComparisonOperator;
use GaiaTools\FulcrumSettings\Enums\ConditionType;
use GaiaTools\FulcrumSettings\Models\SettingRuleCondition;
use GaiaTools\FulcrumSettings\Services\RuleEvaluator;
use GaiaTools\FulcrumSettings\Tests\TestCase;
use Mockery;

class UserAgentTargetingTest extends TestCase
{
    protected RuleEvaluator $evaluator;

    protected $segmentDriver;

    protected $geoResolver;

    protected $uaResolver;

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

    public function test_it_resolves_browser_attributes()
    {
        $this->uaResolver->shouldReceive('resolve')->once()->andReturn([
            'browser' => 'Chrome',
            'browser_version' => '120.0.0',
            'device' => 'Desktop',
            'os' => 'macOS',
        ]);

        $condition = new SettingRuleCondition([
            'type' => ConditionType::USERAGENT->value,
            'attribute' => 'browser',
            'operator' => ComparisonOperator::EQUALS,
            'value' => 'Chrome',
        ]);

        $this->assertTrue($this->evaluator->evaluateCondition($condition));
    }

    public function test_it_resolves_device_attributes()
    {
        $this->uaResolver->shouldReceive('resolve')->once()->andReturn([
            'device' => 'Mobile',
            'is_mobile' => true,
        ]);

        $condition = new SettingRuleCondition([
            'type' => ConditionType::USERAGENT->value,
            'attribute' => 'device',
            'operator' => ComparisonOperator::EQUALS,
            'value' => 'Mobile',
        ]);

        $this->assertTrue($this->evaluator->evaluateCondition($condition));

        $conditionBoolean = new SettingRuleCondition([
            'type' => ConditionType::USERAGENT->value,
            'attribute' => 'is_mobile',
            'operator' => ComparisonOperator::IS_TRUE,
        ]);

        $this->assertTrue($this->evaluator->evaluateCondition($conditionBoolean));
    }

    public function test_it_resolves_os_attributes()
    {
        $this->uaResolver->shouldReceive('resolve')->once()->andReturn([
            'os' => 'iOS',
            'os_version' => '17.2',
        ]);

        $condition = new SettingRuleCondition([
            'type' => ConditionType::USERAGENT->value,
            'attribute' => 'os',
            'operator' => ComparisonOperator::EQUALS,
            'value' => 'iOS',
        ]);

        $this->assertTrue($this->evaluator->evaluateCondition($condition));
    }

    public function test_it_caches_ua_data_during_evaluation()
    {
        $this->uaResolver->shouldReceive('resolve')->once()->andReturn([
            'browser' => 'Firefox',
            'device' => 'Desktop',
        ]);

        $condition1 = new SettingRuleCondition([
            'type' => ConditionType::USERAGENT->value,
            'attribute' => 'browser',
            'operator' => ComparisonOperator::EQUALS,
            'value' => 'Firefox',
        ]);

        $condition2 = new SettingRuleCondition([
            'type' => ConditionType::USERAGENT->value,
            'attribute' => 'device',
            'operator' => ComparisonOperator::EQUALS,
            'value' => 'Desktop',
        ]);

        $this->assertTrue($this->evaluator->evaluateCondition($condition1));
        $this->assertTrue($this->evaluator->evaluateCondition($condition2));
    }
}
