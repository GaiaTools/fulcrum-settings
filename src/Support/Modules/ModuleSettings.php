<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\Modules;

use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\Models\Scopes\TenantScope;
use GaiaTools\FulcrumSettings\Models\Setting;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

class ModuleSettings
{
    public function __construct(private readonly ModuleConfiguration $configuration) {}

    /** @return Builder<Setting> */
    private function query(): Builder
    {
        $model = new Setting;
        $query = $model->newQuery()->withoutGlobalScope(TenantScope::class);
        if ($model->getConnection()->getSchemaBuilder()->hasColumn($model->getTable(), 'tenant_id')) {
            $query->whereNull('tenant_id');
        }

        return $query;
    }

    /** @return array<string, bool> */
    public function statuses(): array
    {
        $mappings = $this->configuration->managed();
        if ($mappings === []) {
            return [];
        }
        $settings = $this->query()->whereIn('key', array_column($mappings, 'activation_setting'))
            ->with('defaultValue')->withCount(['rules' => fn (Builder $query) => $query->withoutGlobalScope(TenantScope::class)])->get()->keyBy('key');
        $statuses = [];
        foreach ($mappings as $name => $mapping) {
            $setting = $settings->get($mapping['activation_setting']);
            $statuses[$name] = $setting === null ? false : $this->value($setting);
        }

        return $statuses;
    }

    private function value(Setting $setting): bool
    {
        $rules = $setting->getAttribute('rules_count');
        if ($setting->type !== SettingType::BOOLEAN || $setting->masked || (is_numeric($rules) && $rules > 0)) {
            throw new RuntimeException("{$setting->key} must be an unmasked global boolean without targeting rules.");
        }
        $value = $setting->getDefaultValue();
        if (! is_bool($value)) {
            throw new RuntimeException("{$setting->key} must have a boolean default value.");
        }

        return $value;
    }

    public function set(string $module, bool $active): void
    {
        $key = $this->configuration->managed()[$module]['activation_setting'] ?? null;
        if ($key === null) {
            throw new RuntimeException("Module {$module} has no Fulcrum activation setting.");
        }
        (new Setting)->getConnection()->transaction(function () use ($key, $active): void {
            $setting = $this->query()->withCount(['rules' => fn (Builder $query) => $query->withoutGlobalScope(TenantScope::class)])->where('key', $key)->first();
            if ($setting !== null) {
                $this->value($setting);
            } else {
                $setting = new Setting(['key' => $key, 'type' => SettingType::BOOLEAN]);
                $setting->save();
            }
            $setting->defaultValue()->updateOrCreate([], ['value' => $active]);
        });
    }
}
