<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Drivers;

use GaiaTools\FulcrumSettings\Contracts\DistributionStrategy;
use GaiaTools\FulcrumSettings\Models\SettingRule;
use GaiaTools\FulcrumSettings\Models\SettingRuleRolloutVariant;

/**
 * Distributes variants across the bucket range so configured percentages stay
 * exact. Buckets are reassigned via a deterministic permutation that preserves
 * the bucket count of every variant; only *which* buckets land on a variant
 * changes, never *how many*.
 */
class StratifiedDistributionStrategy implements DistributionStrategy
{
    /**
     * Forced coprime with the bucket range so the permutation is a bijection
     * for any (including custom) precision.
     */
    private const SHUFFLE_PRIME = 1000003;

    /**
     * Find the variant that corresponds to a given bucket value.
     */
    public function findVariantForBucket(SettingRule $rule, int $bucket): ?SettingRuleRolloutVariant
    {
        $precisionConfig = config('fulcrum.rollout.bucket_precision', 100_000);
        $precision = is_numeric($precisionConfig) ? (int) $precisionConfig : 100_000;

        if ($precision < 1) {
            $precision = 100_000;
        }

        $variants = $rule->rolloutVariants()->orderBy('id')->get();

        if ($variants->isEmpty()) {
            return null;
        }

        // Seed the permutation from the rule's effective salt for stable,
        // reproducible bucket assignments across requests.
        $seed = crc32($rule->getEffectiveSalt());
        $shuffledBucket = $this->shuffleBucket($bucket, $precision, $seed);

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
     * Coprimality is the exact condition for this map to be a bijection, which
     * keeps each variant's bucket count — and therefore its percentage — exact.
     */
    protected function shuffleBucket(int $bucket, int $max, int $seed): int
    {
        if ($max <= 1) {
            return 0;
        }

        $multiplier = $this->coprimeMultiplier($max);

        // Normalise both operands into [0, $max). crc32() and arbitrary bucket
        // inputs may be negative on 32-bit builds, and PHP's modulo keeps the
        // dividend's sign, so wrap explicitly to avoid negative results.
        $index = (($bucket % $max) + $max) % $max;
        $offset = (($seed % $max) + $max) % $max;

        return (int) ((($multiplier * $index) + $offset) % $max);
    }

    /**
     * Coprime integers are dense, so this terminates after very few steps.
     */
    protected function coprimeMultiplier(int $max): int
    {
        $multiplier = self::SHUFFLE_PRIME % $max;

        if ($multiplier < 2) {
            $multiplier += $max;
        }

        while ($this->gcd($multiplier, $max) !== 1) {
            $multiplier++;
        }

        return $multiplier;
    }

    protected function gcd(int $a, int $b): int
    {
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }

        return abs($a);
    }
}
