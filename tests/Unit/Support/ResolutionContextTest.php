<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Models\SettingRule;
use GaiaTools\FulcrumSettings\Models\SettingRuleRolloutVariant;
use GaiaTools\FulcrumSettings\Support\ResolutionContext;
use Mockery;
use PHPUnit\Framework\TestCase;

class ResolutionContextTest extends TestCase
{
    public function test_can_instantiate_via_constructor(): void
    {
        $setting = Mockery::mock(Setting::class);
        $rule = Mockery::mock(SettingRule::class);
        $variant = Mockery::mock(SettingRuleRolloutVariant::class);

        $context = new ResolutionContext(
            key: 'test.key',
            value: 'test.value',
            setting: $setting,
            matchedRule: $rule,
            rulesEvaluated: 2,
            source: 'rule',
            tenantId: 'tenant-1',
            scope: ['user_id' => 123],
            durationMs: 1.5,
            variant: $variant
        );

        $this->assertEquals('test.key', $context->key);
        $this->assertEquals('test.value', $context->value);
        $this->assertSame($setting, $context->setting);
        $this->assertSame($rule, $context->matchedRule);
        $this->assertEquals(2, $context->rulesEvaluated);
        $this->assertEquals('rule', $context->source);
        $this->assertEquals('tenant-1', $context->tenantId);
        $this->assertEquals(['user_id' => 123], $context->scope);
        $this->assertEquals(1.5, $context->durationMs);
        $this->assertSame($variant, $context->variant);
    }

    public function test_can_instantiate_via_not_found(): void
    {
        $startTime = microtime(true) - 0.005; // 5ms ago

        $context = ResolutionContext::notFound(
            key: 'missing.key',
            tenantId: 'tenant-2',
            scope: 'some-scope',
            startTime: $startTime
        );

        $this->assertEquals('missing.key', $context->key);
        $this->assertNull($context->value);
        $this->assertNull($context->setting);
        $this->assertNull($context->matchedRule);
        $this->assertEquals(0, $context->rulesEvaluated);
        $this->assertEquals('not_found', $context->source);
        $this->assertEquals('tenant-2', $context->tenantId);
        $this->assertEquals('some-scope', $context->scope);
        $this->assertGreaterThan(0, $context->durationMs);
        $this->assertNull($context->variant);
    }

    public function test_can_instantiate_via_from_resolution(): void
    {
        $setting = Mockery::mock(Setting::class);
        $rule = Mockery::mock(SettingRule::class);
        $variant = Mockery::mock(SettingRuleRolloutVariant::class);
        $startTime = microtime(true) - 0.010; // 10ms ago

        $context = ResolutionContext::fromResolution(
            key: 'resolved.key',
            value: 'resolved.value',
            setting: $setting,
            rule: $rule,
            rulesEvaluated: 3,
            source: 'rollout',
            tenantId: 'tenant-3',
            scope: ['id' => 456],
            startTime: $startTime,
            variant: $variant
        );

        $this->assertEquals('resolved.key', $context->key);
        $this->assertEquals('resolved.value', $context->value);
        $this->assertSame($setting, $context->setting);
        $this->assertSame($rule, $context->matchedRule);
        $this->assertEquals(3, $context->rulesEvaluated);
        $this->assertEquals('rollout', $context->source);
        $this->assertEquals('tenant-3', $context->tenantId);
        $this->assertEquals(['id' => 456], $context->scope);
        $this->assertGreaterThan(5, $context->durationMs);
        $this->assertSame($variant, $context->variant);
    }
}
