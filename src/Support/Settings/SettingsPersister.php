<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\Settings;

use GaiaTools\FulcrumSettings\Attributes\SettingProperty;
use GaiaTools\FulcrumSettings\Exceptions\InvalidSettingValueException;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Support\TypeRegistry;
use Illuminate\Support\Facades\Validator;

class SettingsPersister
{
    public function __construct(
        protected TypeRegistry $typeRegistry
    ) {}

    public function persist(string $key, mixed $value): void
    {
        $setting = Setting::where('key', $key)
            ->firstOrFail();

        $handler = $this->typeRegistry->getHandler($setting->type);

        if (! $handler->validate($value)) {
            throw InvalidSettingValueException::forSetting($key, $setting->type, $value);
        }

        $setting
            ->defaultValue()
            ->updateOrCreate([
                'valuable_type' => $setting->getMorphClass(),
                'valuable_id' => $setting->getKey(),
            ], ['value' => $value]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, SettingProperty>  $properties
     */
    public function validateWithRules(array $data, array $properties): void
    {
        $rules = [];

        foreach ($properties as $property => $config) {
            if (! empty($config->rules)) {
                $rules[$property] = $config->rules;
            }
        }

        if (! empty($rules)) {
            Validator::make($data, $rules)
                ->validate();
        }
    }
}
