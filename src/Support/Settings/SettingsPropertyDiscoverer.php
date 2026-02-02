<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\Settings;

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
        $configs = [];

        foreach ($reflection->getProperties() as $property) {
            $attributes = $property->getAttributes(SettingProperty::class);

            if (! empty($attributes)) {
                $configs[$property->getName()] = $attributes[0]->newInstance();
            }
        }

        return $configs;
    }
}
