<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface SegmentDriver
{
    /**
     * Check if the given user belongs to the specified segment.
     */
    public function isInSegment(Authenticatable $user, string $segment): bool;

    /**
     * Get all segments that the user belongs to.
     *
     * @return array<int, string>
     */
    public function getUserSegments(Authenticatable $user): array;
}
