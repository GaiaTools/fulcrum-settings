<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Drivers;

use GaiaTools\FulcrumSettings\Contracts\DistributionStrategy;
use GaiaTools\FulcrumSettings\Models\SettingRule;
use GaiaTools\FulcrumSettings\Models\SettingRuleRolloutVariant;

/**
 * Distributes variants based on their cumulative weights.
 */
class WeightDistributionStrategy implements DistributionStrategy
{
    /**
     * Find the variant that corresponds to a given bucket value using cumulative weight.
     */
    public function findVariantForBucket(SettingRule $rule, int $bucket): ?SettingRuleRolloutVariant
    {
        $cumulative = 0;

        foreach ($rule->rolloutVariants->sortBy('id') as $variant) {
            $cumulative += $variant->weight;

            if ($bucket < $cumulative) {
                return $variant;
            }
        }

        return null;
    }
}
