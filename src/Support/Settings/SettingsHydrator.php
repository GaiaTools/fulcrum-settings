<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\Settings;

use GaiaTools\FulcrumSettings\Attributes\SettingProperty;
use GaiaTools\FulcrumSettings\Contracts\SettingResolver;
use GaiaTools\FulcrumSettings\Support\MaskedValue;
use GaiaTools\FulcrumSettings\Support\TypeRegistry;
use ReflectionNamedType;
use ReflectionProperty;

class SettingsHydrator
{
    public function __construct(
        protected TypeRegistry $typeRegistry
    ) {}

    public function hydrate(
        object $instance,
        string $property,
        SettingProperty $config,
        SettingResolver $resolver,
        mixed $context
    ): mixed {
        $value = $resolver->resolve($config->key, $context) ?? $config->default;

        return $this->castValue($instance, $property, $config, $value);
    }

    public function castValue(
        object $instance,
        string $property,
        SettingProperty $config,
        mixed $value
    ): mixed {
        $typeName = $this->resolveTypeName($instance, $property, $config);

        if ($value instanceof MaskedValue || $typeName === null) {
            return $value;
        }

        return $this->typeRegistry->getHandler($typeName)->get($value);
    }

    protected function resolveTypeName(object $instance, string $property, SettingProperty $config): ?string
    {
        if ($config->cast) {
            return $config->cast;
        }

        $reflection = new ReflectionProperty($instance, $property);
        $type = $reflection->getType();

        if (! $type instanceof ReflectionNamedType) {
            return null;
        }

        return $type->getName();
    }
}
