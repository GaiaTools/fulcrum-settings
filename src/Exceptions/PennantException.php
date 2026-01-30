<?php

namespace GaiaTools\FulcrumSettings\Exceptions;

class PennantException extends FulcrumException
{
    public static function unsupportedOperation(string $operation): self
    {
        return new self("The [{$operation}] operation is not supported by the Fulcrum driver.");
    }

    public static function featureNotFound(string $feature): self
    {
        return new self("The feature [{$feature}] was not found in the Fulcrum database.");
    }

    public static function invalidScope(mixed $scope): self
    {
        $type = gettype($scope);

        return new self("The provided scope of type [{$type}] is invalid for the Fulcrum driver.");
    }
}
