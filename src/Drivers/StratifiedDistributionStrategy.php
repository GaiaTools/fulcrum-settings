<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Drivers;

use GaiaTools\FulcrumSettings\Contracts\DistributionStrategy;
use GaiaTools\FulcrumSettings\Models\SettingRule;
use GaiaTools\FulcrumSettings\Models\SettingRuleRolloutVariant;

/**
 * Distributes variants based on a stratified approach that guarantees exact percentages
 * by pre-assigning all buckets in a shuffled but deterministic order.
 */
class StratifiedDistributionStrategy implements DistributionStrategy
{
    /**
     * Find the variant that corresponds to a given bucket value using a stratified approach.
     */
    public function findVariantForBucket(SettingRule $rule, int $bucket): ?SettingRuleRolloutVariant
    {
        $precision = (int) config('fulcrum.rollout.bucket_precision', 100_000);
        $variants = $rule->rolloutVariants->sortBy('id')->values();

        if ($variants->isEmpty()) {
            return null;
        }

        // We use the rule's effective salt to seed the shuffle for consistent bucket assignments
        $salt = $rule->getEffectiveSalt();
        $seed = crc32($salt);

        // Map buckets to variants based on their weight
        // To guarantee exact percentages, we should conceptually have an array of size $precision
        // where each variant occupies exactly $variant->weight slots.
        // Then we shuffle this array deterministically.

        // Since $precision can be large (100,000), we don't want to actually create the array.
        // Instead, we can use a deterministic shuffle algorithm on the bucket index.

        $shuffledBucket = $this->lowBiasShuffle($bucket, $precision, $seed);

        // Now use the same logic as WeightDistributionStrategy on the shuffled bucket
        $cumulative = 0;
        foreach ($variants as $variant) {
            $cumulative += $variant->weight;
            if ($shuffledBucket < $cumulative) {
                return $variant;
            }
        }

        return null;
    }

    /**
     * A simple, low-bias deterministic shuffle for a single index.
     * This is a simplified version of a Feistel cipher or similar format-preserving encryption.
     * For our purposes, a consistent pseudo-random mapping is enough as long as it's a bijection.
     */
    protected function lowBiasShuffle(int $index, int $max, int $seed): int
    {
        // Using a simple Linear Congruential Generator approach for the mapping
        // We need a mapping: f(index) -> shuffled_index where shuffled_index < max
        // and it's a bijection for index < max.

        // To keep it simple and truly stratified across the whole range:
        // we can use: (index * prime + seed) % max
        // This is a bijection if prime and max are coprime.

        // Since we don't know max (it's configurable), we can't easily find a guaranteed prime.
        // However, if we use a large prime and ensure it doesn't divide max, it's usually good.

        // Alternatively, we can use a simpler approach:
        // Use a hash of (seed + index) but that's not guaranteed to be a bijection.

        // Actually, the requirement "guarantees exact percentages" means if we have 100,000 buckets,
        // and weight is 10,000 (10%), then exactly 10,000 buckets MUST map to that variant.
        // My shuffledBucket approach does exactly this because it just reorders WHICH buckets
        // map to WHICH variants, but the total number of buckets mapping to each variant remains $variant->weight.

        // Let's use a very simple Feistel-like swap for bijections.
        // Or even simpler: use a large prime and (index * prime + seed) % max.
        // Common bucket precisions are 100, 1000, 10000, 100000.

        $prime = 1000003; // A prime larger than default precision

        return ($index * $prime + $seed) % $max;
    }
}
