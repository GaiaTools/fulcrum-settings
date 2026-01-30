<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Contracts;

/**
 * Calculates deterministic bucket assignments for percentage-based rollouts.
 *
 * The bucket calculator is responsible for consistently assigning entities
 * (users, sessions, etc.) to buckets for A/B testing and gradual rollouts.
 * The same identifier + salt combination must always produce the same bucket.
 */
interface BucketCalculator
{
    /**
     * Calculate a deterministic bucket for the given identifier.
     *
     * @param  string  $identifier  The entity identifier (user ID, session ID, etc.)
     * @param  string  $salt  A salt value that can be changed to re-randomize assignments
     * @param  int  $buckets  Number of buckets (default 100000 for 0.001% precision)
     * @return int Bucket number from 0 to $buckets-1
     */
    public function calculate(string $identifier, string $salt, int $buckets = 100_000): int;
}
