<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Contracts;

use GaiaTools\FulcrumSettings\Models\SettingRule;
use GaiaTools\FulcrumSettings\Models\SettingRuleRolloutVariant;

/**
 * Strategy for distributing bucketed users to rollout variants.
 */
interface DistributionStrategy
{
    /**
     * Find the variant that corresponds to a given bucket value for a rule.
     *
     * @param  int  $bucket  The calculated bucket value (e.g., 0 to precision-1)
     */
    public function findVariantForBucket(SettingRule $rule, int $bucket): ?SettingRuleRolloutVariant;
}
