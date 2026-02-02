<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support;

use GaiaTools\FulcrumSettings\Contracts\ConditionTypeHandler;
use GaiaTools\FulcrumSettings\Exceptions\InvalidConditionTypeHandlerException;
use GaiaTools\FulcrumSettings\Exceptions\MissingConditionTypeHandlerException;

class ConditionTypeRegistry
{
    /** @var array<string, class-string<ConditionTypeHandler>> */
    protected array $handlers = [];

    public function __construct()
    {
        $types = config('fulcrum.condition_types', []);
        if (! is_array($types)) {
            $types = [];
        }

        foreach ($types as $type => $handlerClass) {
            if (! is_string($type) || ! is_string($handlerClass)) {
                continue;
            }
            $this->register($type, $handlerClass);
        }
    }

    public function register(string $type, string $handlerClass): void
    {
        if (! class_exists($handlerClass)) {
            throw InvalidConditionTypeHandlerException::classNotFound($type, $handlerClass);
        }

        if (! is_subclass_of($handlerClass, ConditionTypeHandler::class)) {
            throw InvalidConditionTypeHandlerException::invalidImplementation($type, $handlerClass);
        }

        $this->handlers[$type] = $handlerClass;
    }

    public function has(string $type): bool
    {
        return isset($this->handlers[$type]);
    }

    public function getHandler(string $type): ConditionTypeHandler
    {
        if (! $this->has($type)) {
            throw MissingConditionTypeHandlerException::forType($type);
        }

        return app($this->handlers[$type]);
    }

    /**
     * @return array<int, string>
     */
    public function registeredTypes(): array
    {
        return array_keys($this->handlers);
    }
}
