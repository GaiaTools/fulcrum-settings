<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Contracts;

interface CacheContextProvider
{
    public function currentTenantId(): ?string;

    /** @return list<string> Request inputs (ip, user_agent) used by this setting. */
    public function requestDependencies(string $key): array;
}
