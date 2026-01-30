<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Unit\Database\Migrations;

use GaiaTools\FulcrumSettings\Database\Migrations\RolloutDefinition;
use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Models\SettingRule;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use GaiaTools\FulcrumSettings\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;

class RolloutDefinitionTest extends TestCase
{
    use RefreshDatabase;

    protected SettingRule $rule;

    protected function setUp(): void
    {
        parent::setUp();
        FulcrumContext::force(true);
        $setting = Setting::create(['key' => 'test', 'type' => SettingType::STRING]);
        $this->rule = $setting->rules()->create(['name' => 'rule', 'priority' => 1]);
    }

    public function test_it_adds_variants()
    {
        $rollout = new RolloutDefinition;
        $rollout->variant('v1', 25.5, 'val1')
            ->variant('v2', 25.5, 'val2');

        $this->assertCount(2, $rollout->getVariants());
        $this->assertEquals(51000, $rollout->getTotalWeight());
        $this->assertEquals(51.0, $rollout->getTotalPercentage());
    }

    public function test_it_supports_shorthands()
    {
        $rollout = new RolloutDefinition;
        $rollout->control(10, 'old')
            ->treatment(10, 'new')
            ->gradual(10, 'gradual');

        $variants = $rollout->getVariants();
        $this->assertCount(3, $variants);
        $this->assertEquals('control', $variants[0]['name']);
        $this->assertEquals('treatment', $variants[1]['name']);
        $this->assertEquals('enabled', $variants[2]['name']);
    }

    public function test_it_supports_fifty_fifty()
    {
        $rollout = new RolloutDefinition;
        $rollout->fiftyFifty('a', 'b');

        $this->assertEquals(100000, $rollout->getTotalWeight());
        $this->assertEquals(100.0, $rollout->getTotalPercentage());
    }

    public function test_it_validates_total_weight()
    {
        $rollout = new RolloutDefinition;
        $rollout->variant('v1', 60)->variant('v2', 50);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds 100%');
        $rollout->validate();
    }

    public function test_it_validates_empty_variants()
    {
        $rollout = new RolloutDefinition;
        $this->expectException(InvalidArgumentException::class);
        $rollout->validate();
    }

    public function test_it_validates_duplicate_names()
    {
        $rollout = new RolloutDefinition;
        $rollout->variant('v1', 10);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate variant name: v1');
        $rollout->variant('v1', 10);
    }

    public function test_it_validates_weight_range()
    {
        $rollout = new RolloutDefinition;

        try {
            $rollout->variant('v1', -1);
            $this->fail('Should have thrown an exception for negative weight');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('must be between 0 and 100', $e->getMessage());
        }

        try {
            $rollout->variant('v1', 101);
            $this->fail('Should have thrown an exception for weight > 100');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('must be between 0 and 100', $e->getMessage());
        }
    }

    public function test_it_creates_models_with_null_values()
    {
        $rollout = new RolloutDefinition;
        $rollout->variant('v1', 50, 'val1')
            ->variant('v2', 50, null);

        $rollout->createFor($this->rule);

        $this->assertCount(2, $this->rule->rolloutVariants);
        $this->assertNotNull($this->rule->rolloutVariants[0]->value);
        $this->assertNull($this->rule->rolloutVariants[1]->value);
    }

    public function test_it_creates_models()
    {
        $rollout = new RolloutDefinition;
        $rollout->fiftyFifty('a', 'b');
        $rollout->createFor($this->rule);

        $this->assertCount(2, $this->rule->rolloutVariants);
        $this->assertEquals('a', $this->rule->rolloutVariants[0]->value->value);
        $this->assertEquals('b', $this->rule->rolloutVariants[1]->value->value);
    }
}
