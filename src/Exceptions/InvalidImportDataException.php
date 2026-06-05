<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Exceptions;

use RuntimeException;

class InvalidImportDataException extends RuntimeException
{
    public function __construct(string $reason)
    {
        parent::__construct("Invalid import data: {$reason}");
    }
}
