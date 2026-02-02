<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use GaiaTools\FulcrumSettings\Contracts\GeoResolver;
use GaiaTools\FulcrumSettings\Contracts\HolidayResolver;
use GaiaTools\FulcrumSettings\Contracts\SegmentDriver;
use GaiaTools\FulcrumSettings\Contracts\UserAgentResolver;
use GaiaTools\FulcrumSettings\Enums\ComparisonOperator;
use GaiaTools\FulcrumSettings\Enums\ConditionType;
use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Models\SettingRule;
use GaiaTools\FulcrumSettings\Models\SettingRuleCondition;
use GaiaTools\FulcrumSettings\Services\RuleEvaluator;
use GaiaTools\FulcrumSettings\Tests\TestCase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class GeoTargetingTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected SegmentDriver $segmentDriver;

    protected GeoResolver $geoResolver;

    protected UserAgentResolver $uaResolver;

    protected RuleEvaluator $evaluator;

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

    public function test_it_resolves_geo_attributes_using_geo_resolver(): void
    {
        $this->geoResolver->shouldReceive('resolve')
            ->once()
            ->andReturn([
                'country' => 'US',
                'city' => 'New York',
                'ip' => '1.2.3.4',
            ]);

        $setting = Setting::create([
            'key' => 'geo.test',
            'type' => SettingType::BOOLEAN,
        ]);

        $rule = SettingRule::create([
            'setting_id' => $setting->id,
            'priority' => 1,
        ]);

        SettingRuleCondition::create([
            'setting_rule_id' => $rule->id,
            'type' => ConditionType::GEOCODING->value,
            'attribute' => 'country',
            'operator' => ComparisonOperator::EQUALS,
            'value' => 'US',
        ]);

        $result = $this->evaluator->evaluateRule($rule);

        $this->assertTrue($result);
    }

    public function test_it_caches_geo_data_during_evaluation(): void
    {
        $this->geoResolver->shouldReceive('resolve')
            ->once() // Should only be called once
            ->andReturn([
                'country' => 'US',
                'city' => 'New York',
            ]);

        $setting = Setting::create([
            'key' => 'geo.test.cache',
            'type' => SettingType::BOOLEAN,
        ]);

        $rule = SettingRule::create([
            'setting_id' => $setting->id,
            'priority' => 1,
        ]);

        SettingRuleCondition::create([
            'setting_rule_id' => $rule->id,
            'type' => ConditionType::GEOCODING->value,
            'attribute' => 'country',
            'operator' => ComparisonOperator::EQUALS,
            'value' => 'US',
        ]);

        SettingRuleCondition::create([
            'setting_rule_id' => $rule->id,
            'type' => ConditionType::GEOCODING->value,
            'attribute' => 'city',
            'operator' => ComparisonOperator::EQUALS,
            'value' => 'New York',
        ]);

        $result = $this->evaluator->evaluateRule($rule);

        $this->assertTrue($result);
    }

    public function test_it_passes_scope_to_geo_resolver(): void
    {
        $customScope = ['ip' => '8.8.8.8'];

        $this->geoResolver->shouldReceive('resolve')
            ->with($customScope)
            ->once()
            ->andReturn([
                'country' => 'US',
            ]);

        $setting = Setting::create([
            'key' => 'geo.test.scope',
            'type' => SettingType::BOOLEAN,
        ]);

        $rule = SettingRule::create([
            'setting_id' => $setting->id,
            'priority' => 1,
        ]);

        SettingRuleCondition::create([
            'setting_rule_id' => $rule->id,
            'type' => ConditionType::GEOCODING->value,
            'attribute' => 'country',
            'operator' => ComparisonOperator::EQUALS,
            'value' => 'US',
        ]);

        $result = $this->evaluator->evaluateRule($rule, $customScope);

        $this->assertTrue($result);
    }
}
