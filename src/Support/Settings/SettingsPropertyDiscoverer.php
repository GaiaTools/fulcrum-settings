<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\Settings;

use GaiaTools\FulcrumSettings\Attributes\SettingGroup;
use GaiaTools\FulcrumSettings\Attributes\SettingProperty;
use ReflectionClass;

class SettingsPropertyDiscoverer
{
    /**
     * @param  class-string  $className
     * @return array<string, SettingProperty>
     */
    public function discover(string $className): array
    {
        $reflection = new ReflectionClass($className);
        $group = $this->resolveGroup($reflection);
        $configs = [];

        foreach ($reflection->getProperties() as $property) {
            $attributes = $property->getAttributes(SettingProperty::class);

            if (! empty($attributes)) {
                $config = $attributes[0]->newInstance();
                if ($group !== null) {
                    $config = $this->applyGroup($config, $group);
                }
                $configs[$property->getName()] = $config;
            }
        }

        return $configs;
    }

    /**
     * @param  ReflectionClass<object>  $reflection
     */
    private function resolveGroup(ReflectionClass $reflection): ?string
    {
        $attributes = $reflection->getAttributes(SettingGroup::class);

        if (empty($attributes)) {
            return null;
        }

        $group = trim($attributes[0]->newInstance()->group);

        return $group !== '' ? $group : null;
    }

    private function applyGroup(SettingProperty $config, string $group): SettingProperty
    {
        if (str_contains($config->key, '.')) {
            return $config;
        }

        return new SettingProperty(
            key: $group.'.'.$config->key,
            default: $config->default,
            rules: $config->rules,
            readOnly: $config->readOnly,
            lazy: $config->lazy,
            cast: $config->cast,
            tenantScoped: $config->tenantScoped
        );
    }
}
