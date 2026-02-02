<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Unit\Drivers;

use GaiaTools\FulcrumSettings\Drivers\StratifiedDistributionStrategy;
use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Models\SettingRule;
use GaiaTools\FulcrumSettings\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class StratifiedDistributionStrategyTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['fulcrum.rollout.bucket_precision' => 100]); // Use small precision for testing
    }

    public function test_it_distributes_variants_exactly()
    {
        $strategy = new StratifiedDistributionStrategy;

        $setting = Setting::create([
            'key' => 'test.setting.distribution',
            'type' => SettingType::STRING,
        ]);

        $rule = SettingRule::create([
            'setting_id' => $setting->id,
            'rollout_salt' => 'test-salt',
        ]);

        $variant1 = $rule->rolloutVariants()->create([
            'name' => 'Variant A',
            'weight' => 30, // 30%
        ]);
        $variant2 = $rule->rolloutVariants()->create([
            'name' => 'Variant B',
            'weight' => 70, // 70%
        ]);

        $results = [];
        for ($i = 0; $i < 100; $i++) {
            $variant = $strategy->findVariantForBucket($rule, $i);
            $results[$variant->id] = ($results[$variant->id] ?? 0) + 1;
        }

        $this->assertEquals(30, $results[$variant1->id]);
        $this->assertEquals(70, $results[$variant2->id]);
    }

    public function test_it_is_deterministic()
    {
        $strategy = new StratifiedDistributionStrategy;

        $setting = Setting::create([
            'key' => 'test.setting.deterministic',
            'type' => SettingType::STRING,
        ]);

        $rule = SettingRule::create([
            'setting_id' => $setting->id,
            'rollout_salt' => 'test-salt',
        ]);

        $rule->rolloutVariants()->create([
            'name' => 'Variant A',
            'weight' => 50,
        ]);
        $rule->rolloutVariants()->create([
            'name' => 'Variant B',
            'weight' => 50,
        ]);

        $firstRun = [];
        for ($i = 0; $i < 100; $i++) {
            $firstRun[] = $strategy->findVariantForBucket($rule, $i)->id;
        }

        $secondRun = [];
        for ($i = 0; $i < 100; $i++) {
            $secondRun[] = $strategy->findVariantForBucket($rule, $i)->id;
        }

        $this->assertEquals($firstRun, $secondRun);
    }

    public function test_it_changes_with_salt()
    {
        $strategy = new StratifiedDistributionStrategy;

        $setting = Setting::create([
            'key' => 'test.setting.salt',
            'type' => SettingType::STRING,
        ]);

        $rule1 = SettingRule::create([
            'setting_id' => $setting->id,
            'rollout_salt' => 'salt-1',
        ]);

        $rule2 = SettingRule::create([
            'setting_id' => $setting->id,
            'rollout_salt' => 'salt-2',
        ]);

        $rule1->rolloutVariants()->create([
            'name' => 'Variant A',
            'weight' => 50,
        ]);
        $rule1->rolloutVariants()->create([
            'name' => 'Variant B',
            'weight' => 50,
        ]);

        $rule2->rolloutVariants()->create([
            'name' => 'Variant A',
            'weight' => 50,
        ]);
        $rule2->rolloutVariants()->create([
            'name' => 'Variant B',
            'weight' => 50,
        ]);

        $run1 = [];
        for ($i = 0; $i < 100; $i++) {
            $run1[] = $strategy->findVariantForBucket($rule1, $i)->id;
        }

        $run2 = [];
        for ($i = 0; $i < 100; $i++) {
            $run2[] = $strategy->findVariantForBucket($rule2, $i)->id;
        }

        // It is theoretically possible they are the same, but with 50/50 and different salts,
        // they should differ in most positions.
        $this->assertNotEquals($run1, $run2);
    }
}
