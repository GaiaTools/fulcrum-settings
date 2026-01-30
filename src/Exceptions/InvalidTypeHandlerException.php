<?php

namespace GaiaTools\FulcrumSettings\Exceptions;

use InvalidArgumentException;

class InvalidTypeHandlerException extends InvalidArgumentException
{
    public static function classNotFound(string $type, string $handlerClass): self
    {
        return new self(
            "Type handler class [{$handlerClass}] for type [{$type}] does not exist."
        );
    }

    public static function invalidImplementation(string $type, string $handlerClass): self
    {
        return new self(
            "Type handler [{$handlerClass}] for type [{$type}] must implement SettingTypeHandler interface."
        );
    }

    public static function notRegistered(string $type): self
    {
        return new self("Type handler for type [{$type}] is not registered.");
    }
}
