<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Exceptions;

use InvalidArgumentException;

class InvalidConditionTypeHandlerException extends InvalidArgumentException
{
    public static function classNotFound(string $type, string $handlerClass): self
    {
        return new self(
            "Condition type handler class [{$handlerClass}] for type [{$type}] does not exist."
        );
    }

    public static function invalidImplementation(string $type, string $handlerClass): self
    {
        return new self(
            "Condition type handler [{$handlerClass}] for type [{$type}] must implement ConditionTypeHandler interface."
        );
    }
}
