<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Unit\Drivers;

use GaiaTools\FulcrumSettings\Drivers\StratifiedDistributionStrategy;
use GaiaTools\FulcrumSettings\Models\SettingRule;
use GaiaTools\FulcrumSettings\Models\SettingRuleRolloutVariant;
use GaiaTools\FulcrumSettings\Tests\TestCase;
use Illuminate\Support\Collection;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class StratifiedDistributionStrategyTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();
        config(['fulcrum.rollout.bucket_precision' => 100]); // Use small precision for testing
    }

    public function test_it_distributes_variants_exactly()
    {
        $strategy = new StratifiedDistributionStrategy;

        $rule = new SettingRule;
        $rule->rollout_salt = 'test-salt';

        $variant1 = new SettingRuleRolloutVariant;
        $variant1->id = 1;
        $variant1->weight = 30; // 30%

        $variant2 = new SettingRuleRolloutVariant;
        $variant2->id = 2;
        $variant2->weight = 70; // 70%

        $variants = new Collection([$variant1, $variant2]);
        $rule->setRelation('rolloutVariants', $variants);

        $results = [];
        for ($i = 0; $i < 100; $i++) {
            $variant = $strategy->findVariantForBucket($rule, $i);
            $results[$variant->id] = ($results[$variant->id] ?? 0) + 1;
        }

        $this->assertEquals(30, $results[1]);
        $this->assertEquals(70, $results[2]);
    }

    public function test_it_is_deterministic()
    {
        $strategy = new StratifiedDistributionStrategy;

        $rule = new SettingRule;
        $rule->rollout_salt = 'test-salt';

        $variant1 = new SettingRuleRolloutVariant;
        $variant1->id = 1;
        $variant1->weight = 50;

        $variant2 = new SettingRuleRolloutVariant;
        $variant2->id = 2;
        $variant2->weight = 50;

        $rule->setRelation('rolloutVariants', new Collection([$variant1, $variant2]));

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

        $rule1 = new SettingRule;
        $rule1->rollout_salt = 'salt-1';

        $rule2 = new SettingRule;
        $rule2->rollout_salt = 'salt-2';

        $variant1 = new SettingRuleRolloutVariant;
        $variant1->id = 1;
        $variant1->weight = 50;

        $variant2 = new SettingRuleRolloutVariant;
        $variant2->id = 2;
        $variant2->weight = 50;

        $rule1->setRelation('rolloutVariants', new Collection([$variant1, $variant2]));
        $rule2->setRelation('rolloutVariants', new Collection([$variant1, $variant2]));

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
