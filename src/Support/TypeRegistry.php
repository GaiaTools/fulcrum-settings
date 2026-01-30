<?php

namespace GaiaTools\FulcrumSettings\Support;

use GaiaTools\FulcrumSettings\Contracts\SettingTypeHandler;
use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\Exceptions\InvalidTypeHandlerException;
use GaiaTools\FulcrumSettings\Exceptions\MissingTypeHandlerException;

class TypeRegistry
{
    protected array $handlers = [];

    public function __construct()
    {
        foreach (config('fulcrum.types', []) as $type => $handlerClass) {
            $this->register($type, $handlerClass);
        }
    }

    /**
     * Register a custom type handler.
     */
    public function register(string $type, string $handlerClass): void
    {
        if (! class_exists($handlerClass)) {
            throw InvalidTypeHandlerException::classNotFound($type, $handlerClass);
        }

        if (! is_subclass_of($handlerClass, SettingTypeHandler::class)) {
            throw InvalidTypeHandlerException::invalidImplementation($type, $handlerClass);
        }

        $this->handlers[$type] = $handlerClass;
    }

    /**
     * Check if a type is registered (built-in or custom).
     */
    public function has(string $type): bool
    {
        return isset($this->handlers[$type]);
    }

    /**
     * Get handler for a type.
     */
    public function getHandler(string|SettingType $type): ?SettingTypeHandler
    {
        $typeStr = $type instanceof SettingType ? $type->value : (string) $type;

        if (! $this->has($typeStr)) {
            throw MissingTypeHandlerException::forType($typeStr);
        }

        return app($this->handlers[$typeStr]);
    }
}
