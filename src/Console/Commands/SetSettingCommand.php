<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Console\Commands;

use GaiaTools\FulcrumSettings\Console\Commands\Concerns\InteractsWithCommandOptions;
use GaiaTools\FulcrumSettings\Enums\ComparisonOperator;
use GaiaTools\FulcrumSettings\Enums\ConditionType;
use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\Facades\Fulcrum;
use GaiaTools\FulcrumSettings\Models\Scopes\TenantScope;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Models\SettingRule;
use GaiaTools\FulcrumSettings\Models\SettingRuleRolloutVariant;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use GaiaTools\FulcrumSettings\Support\TypeRegistry;
use Illuminate\Console\Command;

class SetSettingCommand extends Command
{
    use InteractsWithCommandOptions;

    protected $signature = 'fulcrum:set
                            {key? : The setting key to create or update}
                            {value? : The value to set}
                            {--type=string : The type of the setting (string, integer, float, boolean, json, carbon)}
                            {--description= : A description of the setting}
                            {--masked : Mark the setting as sensitive/encrypted}
                            {--immutable : Mark the setting as immutable}
                            {--tenant= : The tenant ID for scoped settings}
                            {--force : Force the update even if the setting is immutable}';

    protected $description = 'Set a setting value or enter interactive mode';

    public function handle(TypeRegistry $typeRegistry): int
    {
        $key = $this->getStringArgument('key');
        $value = $this->getStringArgument('value');

        if ($key === null) {
            return $this->runInteractive($typeRegistry);
        }

        // If key is provided but value is not, ask for it unless we want to force interactive
        if ($value === null) {
            return $this->runInteractive($typeRegistry, $key);
        }
        $type = $this->getStringOption('type') ?? 'string';
        $description = $this->getStringOption('description');
        $masked = $this->getBoolOption('masked');
        $immutable = $this->getBoolOption('immutable');
        $tenantId = $this->getStringOption('tenant');
        $force = $this->getBoolOption('force');

        if ($force) {
            FulcrumContext::force(true);
        }

        try {
            // Find existing setting or create new one
            $query = Setting::withoutGlobalScope(TenantScope::class)
                ->where('key', $key);

            if (Fulcrum::isMultiTenancyEnabled()) {
                $query->where('tenant_id', $tenantId);
            }

            $setting = $query->first();

            if ($setting) {
                if ($this->hasOption('type') && $type !== 'string') {
                    // Only update type if explicitly provided and different from default
                    $setting->type = SettingType::from($type);
                }

                if ($description !== null) {
                    $setting->description = $description;
                }

                if ($masked) {
                    $setting->masked = true;
                }

                if ($immutable) {
                    $setting->immutable = true;
                }

                $setting->save();
            } else {
                $attributes = [
                    'key' => $key,
                    'type' => SettingType::from($type),
                    'description' => $description,
                    'masked' => $masked,
                    'immutable' => $immutable,
                ];

                if (Fulcrum::isMultiTenancyEnabled()) {
                    $attributes['tenant_id'] = $tenantId;
                }

                $setting = Setting::create($attributes);
            }

            // Handle type-specific conversion for the input value
            $convertedValue = $this->convertValue($value, $setting->type);

            // Use the setting model's logic to save the value
            $handler = $typeRegistry->getHandler($setting->type);

            if (! $handler->validate($convertedValue)) {
                $this->error("Invalid value for type [{$setting->type->value}].");

                return 1;
            }

            $storageValue = $handler->set($convertedValue);

            $match = [
                'valuable_type' => $setting->getMorphClass(),
                'valuable_id' => $setting->getKey(),
            ];

            if (Fulcrum::isMultiTenancyEnabled()) {
                $match['tenant_id'] = $tenantId;
            }

            $setting->defaultValue()->updateOrCreate($match, ['value' => $storageValue]);

            $this->info("Setting [{$key}] updated successfully.");

            return 0;
        } catch (\Throwable $e) {
            $this->error("Failed to set setting: {$e->getMessage()}");

            return 1;
        } finally {
            if ($force) {
                FulcrumContext::force(false);
            }
        }
    }

    protected function convertValue(mixed $value, SettingType $type): mixed
    {
        return match ($type) {
            SettingType::BOOLEAN => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            SettingType::INTEGER => is_numeric($value) ? (int) $value : 0,
            SettingType::FLOAT => is_numeric($value) ? (float) $value : 0.0,
            SettingType::JSON => is_array($value) ? $value : (is_string($value) ? json_decode($value, true) : null),
            default => $value,
        };
    }

    protected function runInteractive(TypeRegistry $typeRegistry, ?string $key = null): int
    {
        $this->info('--- Fulcrum Setting Wizard ---');

        $force = $this->getBoolOption('force');

        if ($key === null) {
            $keyInput = $this->ask('Enter the setting key');
            if (! is_string($keyInput) || $keyInput === '') {
                $this->error('Key is required.');

                return 1;
            }
            $key = $keyInput;
        } else {
            $this->line("Setting key: <info>{$key}</info>");
        }

        $tenantId = $this->getStringOption('tenant');
        if ($tenantId === null && Fulcrum::isMultiTenancyEnabled()) {
            $tenantInput = $this->ask('Enter tenant ID (optional, leave empty for global)');
            $tenantId = is_string($tenantInput) ? $tenantInput : null;
        }

        if ($force) {
            FulcrumContext::force(true);
        }

        try {
            // Find existing setting or create new one
            $query = Setting::withoutGlobalScope(TenantScope::class)
                ->where('key', $key);

            if (Fulcrum::isMultiTenancyEnabled()) {
                $query->where('tenant_id', $tenantId);
            }

            $setting = $query->first();

            if ($setting) {
                $this->info("Updating existing setting [{$key}] for tenant [".($tenantId ?: 'global').']');
                $type = $setting->type;
            } else {
                $this->info("Creating new setting [{$key}] for tenant [".($tenantId ?: 'global').']');
                $typeStr = $this->choice('Select setting type', array_column(SettingType::cases(), 'value'), 'string');
                $type = SettingType::from(is_string($typeStr) ? $typeStr : 'string');
            }

            $descriptionDefault = $setting ? $setting->description : null;
            $description = $this->ask('Enter description', $descriptionDefault);
            $masked = $this->confirm('Is this setting sensitive/masked?', $setting ? $setting->masked : false);
            $immutable = $this->confirm('Is this setting immutable?', $setting ? $setting->immutable : false);

            if ($immutable && ! FulcrumContext::shouldForce()) {
                if (! $this->confirm('Setting is/will be immutable. Continue?')) {
                    return 0;
                }
            }

            if ($setting) {
                $setting->update([
                    'type' => $type,
                    'description' => $description,
                    'masked' => $masked,
                    'immutable' => $immutable,
                ]);
            } else {
                $attributes = [
                    'key' => $key,
                    'type' => $type,
                    'description' => $description,
                    'masked' => $masked,
                    'immutable' => $immutable,
                ];

                if (Fulcrum::isMultiTenancyEnabled()) {
                    $attributes['tenant_id'] = $tenantId;
                }

                $setting = Setting::create($attributes);
            }

            // Set default value
            $defaultValue = $this->ask("Enter default value ({$type->value})");
            $convertedDefault = $this->convertValue($defaultValue, $type);
            $this->saveValue($setting, $convertedDefault, $typeRegistry, $tenantId);

            // Rules management
            if ($this->confirm('Do you want to manage targeting rules for this setting?')) {
                $this->manageRules($setting, $typeRegistry, $tenantId);
            }

            $this->info("Setting [{$key}] saved successfully.");

            return 0;
        } catch (\Throwable $e) {
            $this->error("Failed to save setting: {$e->getMessage()}");

            return 1;
        } finally {
            if ($force) {
                FulcrumContext::force(false);
            }
        }
    }

    protected function saveValue(Setting|SettingRule|SettingRuleRolloutVariant $model, mixed $value, TypeRegistry $typeRegistry, ?string $tenantId): void
    {
        $setting = ($model instanceof Setting) ? $model : $model->resolveSetting();
        if (! $setting) {
            throw new \RuntimeException('Could not resolve parent setting for '.get_class($model));
        }
        $handler = $typeRegistry->getHandler($setting->type);

        if (! $handler->validate($value)) {
            $valueDescription = json_encode($value);
            $valueDescription = $valueDescription === false ? 'null' : $valueDescription;
            throw new \InvalidArgumentException('Invalid value ['.$valueDescription."] for type [{$setting->type->value}].");
        }

        $storageValue = $handler->set($value);

        $relation = ($model instanceof Setting) ? $model->defaultValue() : $model->value();

        $match = [
            'valuable_type' => $model->getMorphClass(),
            'valuable_id' => $model->getKey(),
        ];

        if (Fulcrum::isMultiTenancyEnabled()) {
            $match['tenant_id'] = $tenantId;
        }

        $relation->updateOrCreate($match, ['value' => $storageValue]);
    }

    protected function manageRules(Setting $setting, TypeRegistry $typeRegistry, ?string $tenantId): void
    {
        while (true) {
            $rules = $setting->rules()->orderBy('priority')->get();
            if ($rules->isNotEmpty()) {
                $this->table(['ID', 'Name', 'Priority', 'Conditions', 'Value/Rollout'], $rules->map(fn ($r) => [
                    $r->id,
                    $r->name,
                    $r->priority,
                    $r->conditions()->count(),
                    $r->hasRolloutVariants() ? 'Rollout' : 'Direct Value',
                ]));
            } else {
                $this->line('No rules defined yet.');
            }

            $action = $this->choice('Action', ['Add Rule', 'Edit Rule', 'Delete Rule', 'Done'], 'Done');

            if ($action === 'Done') {
                break;
            }

            if ($action === 'Add Rule') {
                $this->addOrEditRule($setting, null, $typeRegistry, $tenantId);
            } elseif ($action === 'Edit Rule') {
                $ruleIdInput = $this->ask('Enter Rule ID to edit');
                $ruleId = is_numeric($ruleIdInput) ? (int) $ruleIdInput : null;
                $rule = $ruleId !== null ? $setting->rules()->find($ruleId) : null;
                if ($rule) {
                    $this->addOrEditRule($setting, $rule, $typeRegistry, $tenantId);
                } else {
                    $this->error('Rule not found.');
                }
            } elseif ($action === 'Delete Rule') {
                $ruleIdInput = $this->ask('Enter Rule ID to delete');
                $ruleId = is_numeric($ruleIdInput) ? (int) $ruleIdInput : null;
                $rule = $ruleId !== null ? $setting->rules()->find($ruleId) : null;
                if ($rule && $this->confirm("Are you sure you want to delete rule [{$rule->name}]?")) {
                    $rule->delete();
                    $this->info('Rule deleted.');
                }
            }
        }
    }

    protected function addOrEditRule(Setting $setting, ?SettingRule $rule, TypeRegistry $typeRegistry, ?string $tenantId): void
    {
        $name = $this->ask('Rule name', $rule?->name);
        $priorityDefault = $rule ? $rule->priority : 100;
        $priorityInput = $this->ask('Priority (lower runs first)', (string) $priorityDefault);
        $priority = is_numeric($priorityInput) ? (int) $priorityInput : (int) $priorityDefault;

        if ($rule) {
            $rule->update(['name' => $name, 'priority' => $priority]);
        } else {
            $attributes = [
                'name' => $name,
                'priority' => $priority,
            ];

            if (Fulcrum::isMultiTenancyEnabled()) {
                $attributes['tenant_id'] = $tenantId;
            }

            $rule = $setting->rules()->create($attributes);
            // Refresh relation to avoid issues in saveValue
            $rule->setRelation('setting', $setting);
        }

        // Manage Conditions
        $this->manageConditions($rule);

        // Manage Value or Rollout
        $isRollout = $this->confirm('Is this a percentage rollout?', $rule->hasRolloutVariants());

        if ($isRollout) {
            $this->manageRollouts($rule, $typeRegistry, $tenantId);
        } else {
            $defaultValue = $rule->getValue();
            $value = $this->ask(
                "Enter rule value ({$setting->type->value})",
                is_string($defaultValue) ? $defaultValue : null
            );
            $convertedValue = $this->convertValue($value, $setting->type);
            $this->saveValue($rule, $convertedValue, $typeRegistry, $tenantId);
        }
    }

    protected function manageConditions(SettingRule $rule): void
    {
        while (true) {
            $conditions = $rule->conditions;
            if ($conditions->isNotEmpty()) {
                $this->table(['ID', 'Attribute', 'Operator', 'Value'], $conditions->map(fn ($c) => [
                    $c->id,
                    $c->attribute,
                    $c->operator->value,
                    is_array($c->value) ? json_encode($c->value) : $c->value,
                ]));
            }

            $action = $this->choice('Conditions Action', ['Add Condition', 'Delete Condition', 'Done'], 'Done');

            if ($action === 'Done') {
                break;
            }

            if ($action === 'Add Condition') {
                $attribute = $this->ask('Attribute (e.g., user_id, email, segment)');
                if (! is_string($attribute) || $attribute === '') {
                    $this->error('Attribute is required.');

                    continue;
                }
                $operatorStr = $this->choice('Operator', array_column(ComparisonOperator::cases(), 'value'), 'equals');
                $operator = ComparisonOperator::from(is_string($operatorStr) ? $operatorStr : 'equals');

                $value = null;
                if ($operator->requiresValue()) {
                    if ($operator->requiresArrayValue()) {
                        $valuesInput = $this->ask('Enter values (comma separated)');
                        $valuesString = is_string($valuesInput) ? $valuesInput : '';
                        $value = array_filter(array_map('trim', explode(',', $valuesString)), static fn ($item) => $item !== '');
                    } else {
                        $value = $this->ask('Enter value');

                        if ($operator->isStringOperator() && is_string($value)) {
                            $value = preg_replace('/^["\'](.*)["\']$/', '$1', $value);
                        }
                    }
                }

                $attributes = [
                    'type' => ConditionType::default(),
                    'attribute' => $attribute,
                    'operator' => $operator,
                    'value' => $value,
                ];

                if (Fulcrum::isMultiTenancyEnabled()) {
                    $attributes['tenant_id'] = $rule->tenant_id;
                }

                $rule->conditions()->create($attributes);
            } elseif ($action === 'Delete Condition') {
                $condIdInput = $this->ask('Enter Condition ID to delete');
                $condId = is_numeric($condIdInput) ? (int) $condIdInput : null;
                if ($condId !== null) {
                    $rule->conditions()->find($condId)?->delete();
                }
            }
        }
    }

    protected function manageRollouts(SettingRule $rule, TypeRegistry $typeRegistry, ?string $tenantId): void
    {
        while (true) {
            /** @var \Illuminate\Database\Eloquent\Collection<int, SettingRuleRolloutVariant> $variants */
            $variants = $rule->rolloutVariants;
            if ($variants->isNotEmpty()) {
                $this->table(['ID', 'Name', 'Weight (%)', 'Value'], $variants->map(fn (SettingRuleRolloutVariant $v) => [
                    $v->id,
                    $v->name,
                    $v->weight_percentage,
                    $v->getValue(),
                ]));
                $this->line('Total weight: '.$rule->total_rollout_percentage.'%');
            }

            $action = $this->choice('Rollout Action', ['Add Variant', 'Delete Variant', 'Done'], 'Done');

            if ($action === 'Done') {
                if ($rule->rolloutVariants()->sum('weight') > 100000) {
                    $this->error('Total weight exceeds 100%! Please adjust.');

                    continue;
                }
                break;
            }

            if ($action === 'Add Variant') {
                $name = $this->ask('Variant name');
                $weightInput = $this->ask('Weight percentage (0-100)');
                $weightPct = is_numeric($weightInput) ? (float) $weightInput : 0.0;
                $value = $this->ask("Variant value ({$rule->setting->type->value})");
                $convertedValue = $this->convertValue($value, $rule->setting->type);

                $attributes = [
                    'name' => $name,
                    'weight' => (int) ($weightPct * 1000),
                ];

                if (Fulcrum::isMultiTenancyEnabled()) {
                    $attributes['tenant_id'] = $tenantId;
                }

                $variant = $rule->rolloutVariants()->create($attributes);
                $variant->setRelation('rule', $rule);

                $this->saveValue($variant, $convertedValue, $typeRegistry, $tenantId);
            } elseif ($action === 'Delete Variant') {
                $varIdInput = $this->ask('Enter Variant ID to delete');
                $varId = is_numeric($varIdInput) ? (int) $varIdInput : null;
                if ($varId !== null) {
                    $rule->rolloutVariants()->find($varId)?->delete();
                }
            }
        }
    }
}
