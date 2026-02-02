<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Exceptions;

use RuntimeException;

class MissingConditionTypeHandlerException extends RuntimeException
{
    public static function forType(string $type): self
    {
        return new self("Condition type handler for type [{$type}] is not registered.");
    }
}
