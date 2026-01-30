<?php

namespace GaiaTools\FulcrumSettings\Exceptions;

class MissingTypeHandlerException extends \RuntimeException
{
    public static function forType(string $type): self
    {
        return new self(
            "No type handler registered for [{$type}]. Register it in config/fulcrum.php under 'types'."
        );
    }

    public static function forProperty(string $class, string $property, string $type): self
    {
        return new self(
            "No type handler registered for property [{$class}::\${$property}] of type [{$type}]. Register it in config/fulcrum.php under 'types'."
        );
    }
}
