<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Contracts;

interface TenantResolver
{
    /**
     * Resolve the current tenant identifier.
     *
     * Returns null if no tenant context is available (e.g., guest user,
     * global/shared context, or tenant-agnostic request).
     */
    public function resolve(): ?string;
}
