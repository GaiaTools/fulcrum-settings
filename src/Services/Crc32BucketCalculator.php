<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Services;

use GaiaTools\FulcrumSettings\Contracts\BucketCalculator;

/**
 * CRC32-based bucket calculator for percentage rollouts.
 *
 * Uses CRC32 hashing to deterministically assign identifiers to buckets.
 * CRC32 provides good distribution and is fast, making it suitable for
 * high-volume feature flag evaluation.
 */
class Crc32BucketCalculator implements BucketCalculator
{
    /**
     * Calculate a deterministic bucket for the given identifier.
     *
     * The calculation is:
     * 1. Concatenate identifier and salt with a separator
     * 2. Compute CRC32 hash
     * 3. Take absolute value (CRC32 can return negative on 32-bit systems)
     * 4. Modulo by bucket count
     *
     * @param  string  $identifier  The entity identifier (user ID, session ID, etc.)
     * @param  string  $salt  A salt value that can be changed to re-randomize assignments
     * @param  int  $buckets  Number of buckets (default 100000 for 0.001% precision)
     * @return int Bucket number from 0 to $buckets-1
     */
    public function calculate(string $identifier, string $salt, int $buckets = 100_000): int
    {
        $hash = crc32($identifier.':'.$salt);

        // CRC32 can return negative values on 32-bit PHP, use abs
        return abs($hash) % $buckets;
    }
}
