<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Database\Migrations;

use BackedEnum;
use Closure;
use GaiaTools\FulcrumSettings\Exceptions\InvalidSettingValueException;
use GaiaTools\FulcrumSettings\Exceptions\InvalidTypeHandlerException;
use GaiaTools\FulcrumSettings\Facades\Fulcrum;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Support\TypeRegistry;

/**
 * Fluent builder for modifying an existing setting in a migration.
 *
 * @example
 * ```php
 * $this->modify('feature.dark_mode', function (SettingModifier $setting) {
 *     $setting->updateDescription('Enable dark mode theme')
 *         ->updateDefault(true)
 *         ->makeImmutable()
 *         ->addRule(function (RuleDefinition $rule) {
 *             $rule->name('beta_users')
 *                 ->when('user.is_beta', 'is_true')
 *                 ->then(true);
 *         })
 *         ->removeRule('old_rule');
 * });
 * ```
 */
class SettingModifier
{
    /** @var array<string, mixed> */
    protected array $updates = [];

    /** @var array<int, RuleDefinition> */
    protected array $rulesToAdd = [];

    /** @var array<int, string> */
    protected array $rulesToRemove = [];

    /** @var array<string, Closure> */
    protected array $rulesToModify = [];

    protected mixed $newDefaultValue = null;

    protected bool $shouldUpdateDefault = false;

    public function __construct(
        protected readonly Setting $setting
    ) {}

    /**
     * Update the setting type.
     * WARNING: This may invalidate existing values if they don't match the new type.
     */
    public function updateType(string|BackedEnum $type): self
    {
        $typeString = $type instanceof BackedEnum ? $type->value : $type;
        $typeRegistry = app(TypeRegistry::class);

        if (! $typeRegistry->has($typeString)) {
            throw InvalidTypeHandlerException::notRegistered($typeString);
        }

        $this->updates['type'] = $typeString;

        return $this;
    }

    /**
     * Update the setting description.
     */
    public function updateDescription(string $description): self
    {
        $this->updates['description'] = $description;

        return $this;
    }

    /**
     * Update the default value.
     */
    public function updateDefault(mixed $value): self
    {
        $this->newDefaultValue = $value;
        $this->shouldUpdateDefault = true;

        return $this;
    }

    /**
     * Mark the setting as masked (encrypted at rest).
     */
    public function makeMasked(): self
    {
        $this->updates['masked'] = true;

        return $this;
    }

    /**
     * Remove masking from the setting.
     */
    public function removeMasking(): self
    {
        $this->updates['masked'] = false;

        return $this;
    }

    /**
     * Mark the setting as immutable.
     */
    public function makeImmutable(): self
    {
        $this->updates['immutable'] = true;

        return $this;
    }

    /**
     * Remove immutability from the setting.
     */
    public function makeMutable(): self
    {
        $this->updates['immutable'] = false;

        return $this;
    }

    /**
     * Add a new rule to this setting.
     */
    public function addRule(Closure $callback): self
    {
        $ruleDefinition = new RuleDefinition;
        $callback($ruleDefinition);
        $this->rulesToAdd[] = $ruleDefinition;

        return $this;
    }

    /**
     * Remove a rule by name.
     */
    public function removeRule(string $name): self
    {
        $this->rulesToRemove[] = $name;

        return $this;
    }

    /**
     * Modify an existing rule by name.
     */
    public function modifyRule(string $name, Closure $callback): self
    {
        $this->rulesToModify[$name] = $callback;

        return $this;
    }

    /**
     * Remove all rules from the setting.
     */
    public function clearRules(): self
    {
        foreach ($this->setting->rules as $rule) {
            $this->rulesToRemove[] = $rule->name;
        }

        return $this;
    }

    /**
     * Update the tenant ID.
     */
    public function updateTenant(?string $tenantId): self
    {
        if (Fulcrum::isMultiTenancyEnabled()) {
            $this->updates['tenant_id'] = $tenantId;
        }

        return $this;
    }

    /**
     * Apply all modifications to the setting.
     */
    public function apply(): Setting
    {
        // Apply direct updates to the setting
        if (! empty($this->updates)) {
            $this->setting->update($this->updates);
        }

        // Update default value
        if ($this->shouldUpdateDefault || isset($this->updates['type'])) {
            $this->applyDefaultValueUpdate();
        }

        // Remove rules
        foreach ($this->rulesToRemove as $ruleName) {
            $this->setting->rules()
                ->where('name', $ruleName)
                ->delete();
        }

        // Modify existing rules
        foreach ($this->rulesToModify as $ruleName => $callback) {
            $rule = $this->setting->rules()
                ->where('name', $ruleName)
                ->first();

            if ($rule) {
                $ruleModifier = new RuleModifier($rule);
                $callback($ruleModifier);
                $ruleModifier->apply();
            }
        }

        // Add new rules
        foreach ($this->rulesToAdd as $ruleDefinition) {
            $ruleDefinition->createFor($this->setting);
        }

        return $this->setting->fresh();
    }

    /**
     * Alias for apply().
     */
    public function save(): Setting
    {
        return $this->apply();
    }

    /**
     * Apply the default value update.
     */
    protected function applyDefaultValueUpdate(): void
    {
        $type = $this->updates['type'] ?? $this->setting->type->value;
        $typeRegistry = app(TypeRegistry::class);
        $handler = $typeRegistry->getHandler($type);

        $valueToValidate = $this->shouldUpdateDefault
            ? $this->newDefaultValue
            : $this->setting->defaultValue?->getRawOriginal('value');

        // Validate the value
        if ($valueToValidate !== null && ! $handler->validate($valueToValidate)) {
            throw InvalidSettingValueException::forSetting(
                $this->setting->key,
                $type,
                $valueToValidate
            );
        }

        if (! $this->shouldUpdateDefault) {
            return;
        }

        $defaultValueModel = $this->setting->defaultValue;

        if ($defaultValueModel) {
            $defaultValueModel->update([
                'value' => $this->newDefaultValue,
            ]);
        } else {
            $this->setting->defaultValue()->create([
                'valuable_type' => $this->setting->getMorphClass(),
                'valuable_id' => $this->setting->id,
                'value' => $this->newDefaultValue,
            ]);
        }
    }

    /**
     * Get the underlying setting model.
     */
    public function getSetting(): Setting
    {
        return $this->setting;
    }
}
