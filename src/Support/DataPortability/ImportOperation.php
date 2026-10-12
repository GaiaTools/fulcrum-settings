<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\DataPortability;

/** @internal State limited to one import invocation. */
final class ImportOperation
{
    public int $count = 0;

    public function __construct(public ?string $connection) {}
}
