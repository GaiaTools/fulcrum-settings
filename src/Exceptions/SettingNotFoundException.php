<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Exceptions;

use RuntimeException;

class SettingNotFoundException extends RuntimeException
{
    public function __construct(string $key, ?string $tenantId = null)
    {
        $context = $tenantId ? " for tenant [{$tenantId}]" : '';

        parent::__construct("Setting [{$key}]{$context} not found.");
    }
}
