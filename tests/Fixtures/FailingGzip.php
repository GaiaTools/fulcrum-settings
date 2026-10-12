<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\DataPortability;

// Inject compression failure without changing production behavior or exhausting memory.
function gzencode(string $data): string|false
{
    return app()->bound('testing.fail-gzip') ? false : \gzencode($data);
}
