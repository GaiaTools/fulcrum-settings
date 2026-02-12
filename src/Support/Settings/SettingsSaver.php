<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\Settings;

use GaiaTools\FulcrumSettings\Attributes\SettingProperty;
use GaiaTools\FulcrumSettings\Events\SavingSettings;
use GaiaTools\FulcrumSettings\Events\SettingsSaved;

final class SettingsSaver
{
    public function __construct(
        private SettingsState $state,
        private SettingsPersister $persister
    ) {}

    public function save(object $instance): bool
    {
        $toSave = $this->collectSavableProperties();

        if (empty($toSave)) {
            return false;
        }

        $data = $this->collectPropertyValues($instance, $toSave);
        $this->persister->validateWithRules($data, $toSave);

        event(new SavingSettings($data));

        foreach ($toSave as $property => $config) {
            $this->persister->persist($config->key, $instance->{$property});
        }

        $this->state->clearDirty();
        event(new SettingsSaved($data));

        return true;
    }

    /**
     * @return array<string, SettingProperty>
     */
    private function collectSavableProperties(): array
    {
        $toSave = [];
        $configs = $this->state->propertyConfigs();

        foreach ($this->state->dirtyProperties() as $property) {
            $config = $configs[$property] ?? null;

            if ($config && ! $config->readOnly) {
                $toSave[$property] = $config;
            }
        }

        return $toSave;
    }

    /**
     * @param  array<string, SettingProperty>  $properties
     * @return array<string, mixed>
     */
    private function collectPropertyValues(object $instance, array $properties): array
    {
        $values = [];

        foreach ($properties as $property => $config) {
            $values[$config->key] = $instance->{$property};
        }

        return $values;
    }
}
