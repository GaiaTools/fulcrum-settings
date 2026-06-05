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

    public function test_it_returns_null_when_no_variants_exist()
    {
        $strategy = new StratifiedDistributionStrategy;

        $setting = Setting::create([
            'key' => 'test.setting.empty',
            'type' => SettingType::STRING,
        ]);

        $rule = SettingRule::create([
            'setting_id' => $setting->id,
            'rollout_salt' => 'test-salt',
        ]);

        $this->assertNull($strategy->findVariantForBucket($rule, 5));
    }

    public function test_it_falls_back_to_default_precision_for_invalid_config()
    {
        config(['fulcrum.rollout.bucket_precision' => 0]); // invalid, must reset to default

        $strategy = new StratifiedDistributionStrategy;

        $setting = Setting::create([
            'key' => 'test.setting.invalid-precision',
            'type' => SettingType::STRING,
        ]);

        $rule = SettingRule::create([
            'setting_id' => $setting->id,
            'rollout_salt' => 'test-salt',
        ]);

        $variant = $rule->rolloutVariants()->create([
            'name' => 'Variant A',
            'weight' => 100_000,
        ]);

        $this->assertSame($variant->id, $strategy->findVariantForBucket($rule, 42)->id);
    }

    public function test_it_handles_single_bucket_precision()
    {
        config(['fulcrum.rollout.bucket_precision' => 1]); // exercises the $max <= 1 short-circuit

        $strategy = new StratifiedDistributionStrategy;

        $setting = Setting::create([
            'key' => 'test.setting.single-bucket',
            'type' => SettingType::STRING,
        ]);

        $rule = SettingRule::create([
            'setting_id' => $setting->id,
            'rollout_salt' => 'test-salt',
        ]);

        $variant = $rule->rolloutVariants()->create([
            'name' => 'Variant A',
            'weight' => 1,
        ]);

        $this->assertSame($variant->id, $strategy->findVariantForBucket($rule, 0)->id);
    }

    public function test_it_returns_null_when_variants_do_not_span_the_full_range()
    {
        // Default precision is 100 (set in setUp) but the only variant covers
        // 10 buckets, so the ~90 buckets that shuffle outside [0, 10) must
        // resolve to null instead of a variant.
        $strategy = new StratifiedDistributionStrategy;

        $setting = Setting::create([
            'key' => 'test.setting.partial-range',
            'type' => SettingType::STRING,
        ]);

        $rule = SettingRule::create([
            'setting_id' => $setting->id,
            'rollout_salt' => 'test-salt',
        ]);

        $rule->rolloutVariants()->create([
            'name' => 'Variant A',
            'weight' => 10,
        ]);

        $sawNull = false;
        for ($i = 0; $i < 100; $i++) {
            if ($strategy->findVariantForBucket($rule, $i) === null) {
                $sawNull = true;
                break;
            }
        }

        $this->assertTrue($sawNull);
    }

    public function test_it_adjusts_a_below_threshold_multiplier()
    {
        // precision 6 yields a base multiplier of 1 (1000003 % 6), which is
        // below the usable threshold and must be lifted by the range so the
        // affine map stays a bijection.
        config(['fulcrum.rollout.bucket_precision' => 6]);

        $strategy = new StratifiedDistributionStrategy;

        $setting = Setting::create([
            'key' => 'test.setting.low-multiplier',
            'type' => SettingType::STRING,
        ]);

        $rule = SettingRule::create([
            'setting_id' => $setting->id,
            'rollout_salt' => 'test-salt',
        ]);

        $variantA = $rule->rolloutVariants()->create(['name' => 'Variant A', 'weight' => 3]);
        $variantB = $rule->rolloutVariants()->create(['name' => 'Variant B', 'weight' => 3]);

        $counts = [$variantA->id => 0, $variantB->id => 0];
        for ($i = 0; $i < 6; $i++) {
            $counts[$strategy->findVariantForBucket($rule, $i)->id]++;
        }

        // Bijection over 6 buckets with 3/3 weights => exactly 3 each.
        $this->assertSame(3, $counts[$variantA->id]);
        $this->assertSame(3, $counts[$variantB->id]);
    }

    public function test_it_remains_a_bijection_when_precision_shares_a_factor_with_the_prime()
    {
        // 2000006 = 2 * 1000003, so the base multiplier is NOT coprime with the
        // range. This forces coprimeMultiplier()'s adjustment loop to run and
        // proves the permutation still works for hostile custom precisions.
        config(['fulcrum.rollout.bucket_precision' => 2000006]);

        $strategy = new StratifiedDistributionStrategy;

        $setting = Setting::create([
            'key' => 'test.setting.coprime-loop',
            'type' => SettingType::STRING,
        ]);

        $rule = SettingRule::create([
            'setting_id' => $setting->id,
            'rollout_salt' => 'test-salt',
        ]);

        $variantA = $rule->rolloutVariants()->create(['name' => 'Variant A', 'weight' => 1000003]);
        $variantB = $rule->rolloutVariants()->create(['name' => 'Variant B', 'weight' => 1000003]);

        $ids = [$variantA->id, $variantB->id];

        // Every sampled bucket must resolve to one of the two variants.
        foreach ([0, 1, 999, 1000003, 1500000, 2000005] as $bucket) {
            $this->assertContains($strategy->findVariantForBucket($rule, $bucket)->id, $ids);
        }
    }
}
