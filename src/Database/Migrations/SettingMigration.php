<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Database\Migrations;

use Closure;
use GaiaTools\FulcrumSettings\Enums\ComparisonOperator;
use GaiaTools\FulcrumSettings\Enums\ConditionType;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Models\SettingRule;
use GaiaTools\FulcrumSettings\Models\SettingRuleCondition;
use GaiaTools\FulcrumSettings\Models\SettingRuleRolloutVariant;
use GaiaTools\FulcrumSettings\Models\SettingValue;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Base class for settings migrations.
 *
 * Provides a fluent API for creating, modifying, and deleting settings
 * in a migration-friendly way, similar to Laravel's Schema migrations.
 *
 * ## Creating Settings
 * ```php
 * $this->create('feature.new_checkout', function (SettingDefinition $setting) {
 *     $setting->boolean()
 *         ->default(false)
 *         ->description('Enable new checkout flow');
 * });
 * ```
 *
 * ## Modifying Settings
 * ```php
 * $this->modify('feature.dark_mode', function (SettingModifier $setting) {
 *     $setting->updateDescription('Enable dark mode theme')
 *         ->addRule(function (RuleDefinition $rule) {
 *             $rule->name('beta_users')
 *                 ->when('user.is_beta', 'is_true')
 *                 ->then(true);
 *         });
 * });
 * ```
 *
 * ## Direct Operations (no callbacks needed)
 * ```php
 * // Update just the default value
 * $this->updateDefault('feature.limit', 100);
 *
 * // Rename a setting
 * $this->rename('old.key', 'new.key');
 *
 * // Delete a rule directly
 * $this->deleteRule('feature.enabled', 'beta_rule');
 * ```
 */
abstract class SettingMigration extends Migration
{
    // =========================================================================
    // SETTING OPERATIONS
    // =========================================================================

    /**
     * Create a new setting.
     *
     * @example
     * ```php
     * $this->create('app.feature', function (SettingDefinition $setting) {
     *     $setting->boolean()->default(false)->description('A feature flag');
     * });
     * ```
     */
    protected function create(string $key, Closure $callback): Setting
    {
        $definition = $this->createSetting($key);
        $callback($definition);

        return $this->withinContext(fn () => $definition->save());
    }

    /**
     * Create a new setting definition.
     *
     * @example
     * ```php
     * $this->createSetting('beta_dashboard')
     *     ->type('boolean')
     *     ->default(false)
     *     ->save();
     * ```
     */
    protected function createSetting(string $key): SettingDefinition
    {
        return new SettingDefinition($key);
    }

    /**
     * Create a setting if it doesn't exist.
     */
    protected function createIfNotExists(string $key, Closure $callback): ?Setting
    {
        if ($this->exists($key)) {
            return null;
        }

        return $this->create($key, $callback);
    }

    /**
     * Modify an existing setting.
     *
     * @example
     * ```php
     * $this->modify('app.feature', function (SettingModifier $setting) {
     *     $setting->updateDescription('Updated description');
     * });
     * ```
     */
    protected function modify(string $key, Closure $callback): Setting
    {
        $modifier = $this->modifySetting($key);
        $callback($modifier);

        return $this->withinContext(fn () => $modifier->apply());
    }

    /**
     * Get a setting modifier for an existing setting.
     *
     * @example
     * ```php
     * $this->modifySetting('app.feature')
     *     ->updateDescription('Updated description')
     *     ->apply();
     * ```
     */
    protected function modifySetting(string $key): SettingModifier
    {
        $setting = Setting::withoutGlobalScopes()->where('key', $key)->firstOrFail();

        return new SettingModifier($setting);
    }

    /**
     * Create or modify a setting (upsert pattern).
     *
     * @example
     * ```php
     * $this->upsert(
     *     'app.feature',
     *     function (SettingDefinition $def) {
     *         $def->boolean()->default(false)->description('Feature flag');
     *     },
     *     function (SettingModifier $mod) {
     *         $mod->updateDescription('Updated description');
     *     }
     * );
     * ```
     */
    protected function upsert(string $key, Closure $createCallback, ?Closure $modifyCallback = null): Setting
    {
        if ($this->exists($key)) {
            return $this->modify($key, $modifyCallback ?? $createCallback);
        }

        return $this->create($key, $createCallback);
    }

    /**
     * Get a setting definition for upserting.
     *
     * @example
     * ```php
     * $this->upsertSetting('app.feature')
     *     ->type('boolean')
     *     ->default(false)
     *     ->save();
     * ```
     */
    protected function upsertSetting(string $key): SettingDefinition|SettingModifier
    {
        if ($this->exists($key)) {
            return $this->modifySetting($key);
        }

        return $this->createSetting($key);
    }

    /**
     * Delete a setting and all its related data.
     */
    protected function delete(string $key): void
    {
        $this->withinContext(function () use ($key) {
            $setting = Setting::withoutGlobalScopes()->where('key', $key)->first();
            $setting?->delete();
        });
    }

    /**
     * Alias for delete().
     */
    protected function deleteSetting(string $key): void
    {
        $this->delete($key);
    }

    /**
     * Delete a setting if it exists.
     */
    protected function deleteIfExists(string $key): void
    {
        $this->delete($key);
    }

    /**
     * Rename a setting key.
     */
    protected function rename(string $from, string $to): Setting
    {
        return $this->withinContext(function () use ($from, $to) {
            $setting = Setting::withoutGlobalScopes()->where('key', $from)->firstOrFail();
            $setting->update(['key' => $to]);

            return $setting->fresh();
        });
    }

    /**
     * Check if a setting exists.
     */
    protected function exists(string $key): bool
    {
        return Setting::withoutGlobalScopes()->where('key', $key)->exists();
    }

    /**
     * Get a setting by key.
     */
    protected function get(string $key): ?Setting
    {
        return Setting::withoutGlobalScopes()->where('key', $key)->first();
    }

    // =========================================================================
    // DIRECT SETTING PROPERTY UPDATES (No callbacks needed)
    // =========================================================================

    /**
     * Update the default value of a setting directly.
     *
     * @example
     * ```php
     * $this->updateDefault('app.max_items', 100);
     * ```
     */
    protected function updateDefault(string $key, mixed $value): Setting
    {
        return $this->modify($key, function (SettingModifier $setting) use ($value) {
            $setting->updateDefault($value);
        });
    }

    /**
     * Update the description of a setting directly.
     */
    protected function updateDescription(string $key, string $description): Setting
    {
        return $this->modify($key, function (SettingModifier $setting) use ($description) {
            $setting->updateDescription($description);
        });
    }

    /**
     * Update the type of a setting directly.
     */
    protected function updateType(string $key, string $type): Setting
    {
        return $this->modify($key, function (SettingModifier $setting) use ($type) {
            $setting->updateType($type);
        });
    }

    /**
     * Make a setting immutable.
     */
    protected function makeImmutable(string $key): Setting
    {
        return $this->modify($key, function (SettingModifier $setting) {
            $setting->makeImmutable();
        });
    }

    /**
     * Make a setting mutable.
     */
    protected function makeMutable(string $key): Setting
    {
        return $this->modify($key, function (SettingModifier $setting) {
            $setting->makeMutable();
        });
    }

    /**
     * Make a setting masked (encrypted at rest).
     */
    protected function makeMasked(string $key): Setting
    {
        return $this->modify($key, function (SettingModifier $setting) {
            $setting->makeMasked();
        });
    }

    /**
     * Remove masking from a setting.
     */
    protected function removeMasking(string $key): Setting
    {
        return $this->modify($key, function (SettingModifier $setting) {
            $setting->removeMasking();
        });
    }

    // =========================================================================
    // RULE OPERATIONS
    // =========================================================================

    /**
     * Add a rule to a setting using a callback.
     *
     * @example
     * ```php
     * $this->addRule('feature.checkout', function (RuleDefinition $rule) {
     *     $rule->name('premium_users')
     *         ->priority(10)
     *         ->when('user.plan', 'equals', 'premium')
     *         ->then(true);
     * });
     * ```
     */
    protected function addRule(string $settingKey, Closure $callback): SettingRule
    {
        return $this->withinContext(function () use ($settingKey, $callback) {
            $setting = Setting::withoutGlobalScopes()->where('key', $settingKey)->firstOrFail();
            $ruleDefinition = new RuleDefinition;
            $callback($ruleDefinition);

            return $ruleDefinition->createFor($setting);
        });
    }

    /**
     * Add a simple rule with a direct value (no conditions).
     *
     * @example
     * ```php
     * $this->addSimpleRule('feature.enabled', 'default', true, priority: 100);
     * ```
     */
    protected function addSimpleRule(
        string $settingKey,
        string $ruleName,
        mixed $value,
        int $priority = 0
    ): SettingRule {
        return $this->addRule($settingKey, function (RuleDefinition $rule) use ($ruleName, $value, $priority) {
            $rule->name($ruleName)
                ->priority($priority)
                ->then($value);
        });
    }

    /**
     * Add a conditional rule.
     *
     * @example
     * ```php
     * $this->addConditionalRule(
     *     'feature.checkout',
     *     'premium',
     *     value: true,
     *     attribute: 'user.plan',
     *     operator: 'equals',
     *     conditionValue: 'premium'
     * );
     * ```
     */
    protected function addConditionalRule(
        string $settingKey,
        string $ruleName,
        mixed $value,
        string $attribute,
        string|ComparisonOperator $operator,
        mixed $conditionValue = null,
        int $priority = 0,
        ?string $type = null
    ): SettingRule {
        return $this->addRule($settingKey, function (RuleDefinition $rule) use ($ruleName, $value, $attribute, $operator, $conditionValue, $priority, $type) {
            $rule->name($ruleName)
                ->priority($priority)
                ->when($attribute, $operator, $conditionValue, $type)
                ->then($value);
        });
    }

    /**
     * Modify a specific rule on a setting.
     *
     * @example
     * ```php
     * $this->modifyRule('feature.checkout', 'premium_users', function (RuleModifier $rule) {
     *     $rule->updatePriority(5)
     *         ->addCondition('user.active', 'is_true');
     * });
     * ```
     */
    protected function modifyRule(string $settingKey, string $ruleName, Closure $callback): SettingRule
    {
        return $this->withinContext(function () use ($settingKey, $ruleName, $callback) {
            $setting = Setting::withoutGlobalScopes()->where('key', $settingKey)->firstOrFail();
            $rule = $setting->rules()->where('name', $ruleName)->firstOrFail();

            $ruleModifier = new RuleModifier($rule);
            $callback($ruleModifier);

            return $ruleModifier->apply();
        });
    }

    /**
     * Delete a specific rule from a setting.
     */
    protected function deleteRule(string $settingKey, string $ruleName): void
    {
        $this->withinContext(function () use ($settingKey, $ruleName) {
            $setting = Setting::withoutGlobalScopes()->where('key', $settingKey)->first();
            $rule = $setting?->rules()->where('name', $ruleName)->first();
            $rule?->delete();
        });
    }

    /**
     * Delete a rule if it exists.
     */
    protected function deleteRuleIfExists(string $settingKey, string $ruleName): void
    {
        $this->deleteRule($settingKey, $ruleName);
    }

    /**
     * Rename a rule.
     */
    protected function renameRule(string $settingKey, string $oldName, string $newName): SettingRule
    {
        return $this->modifyRule($settingKey, $oldName, function (RuleModifier $rule) use ($newName) {
            $rule->updateName($newName);
        });
    }

    /**
     * Update rule priority directly.
     */
    protected function updateRulePriority(string $settingKey, string $ruleName, int $priority): SettingRule
    {
        return $this->modifyRule($settingKey, $ruleName, function (RuleModifier $rule) use ($priority) {
            $rule->updatePriority($priority);
        });
    }

    /**
     * Update rule value directly.
     */
    protected function updateRuleValue(string $settingKey, string $ruleName, mixed $value): SettingRule
    {
        return $this->modifyRule($settingKey, $ruleName, function (RuleModifier $rule) use ($value) {
            $rule->updateValue($value);
        });
    }

    /**
     * Clear all rules from a setting.
     */
    protected function clearRules(string $settingKey): void
    {
        $this->modify($settingKey, function (SettingModifier $setting) {
            $setting->clearRules();
        });
    }

    /**
     * Check if a rule exists on a setting.
     */
    protected function ruleExists(string $settingKey, string $ruleName): bool
    {
        $setting = Setting::withoutGlobalScopes()->where('key', $settingKey)->first();

        return $setting?->rules()->where('name', $ruleName)->exists() ?? false;
    }

    // =========================================================================
    // CONDITION OPERATIONS
    // =========================================================================

    /**
     * Add a condition to a rule.
     *
     * @example
     * ```php
     * $this->addCondition('feature.checkout', 'premium_users', 'country', 'in', ['US', 'CA']);
     * ```
     */
    protected function addCondition(
        string $settingKey,
        string $ruleName,
        string $attribute,
        string|ComparisonOperator $operator,
        mixed $value = null,
        ?string $type = null
    ): SettingRuleCondition {
        return $this->withinContext(function () use ($settingKey, $ruleName, $attribute, $operator, $value, $type) {
            $setting = Setting::withoutGlobalScopes()->where('key', $settingKey)->firstOrFail();
            $rule = $setting->rules()->where('name', $ruleName)->firstOrFail();

            $operatorValue = $operator instanceof ComparisonOperator
                ? $operator->value
                : $operator;

            return $rule->conditions()->create([
                'type' => $type ?? ConditionType::default(),
                'attribute' => $attribute,
                'operator' => $operatorValue,
                'value' => $value,
            ]);
        });
    }

    /**
     * Modify a specific condition on a rule.
     *
     * @example
     * ```php
     * $this->modifyCondition('feature.checkout', 'premium', 'country', function (ConditionModifier $cond) {
     *     $cond->updateValue(['US', 'CA', 'UK']);
     * });
     * ```
     */
    protected function modifyCondition(
        string $settingKey,
        string $ruleName,
        string $attribute,
        Closure $callback
    ): SettingRuleCondition {
        return $this->withinContext(function () use ($settingKey, $ruleName, $attribute, $callback) {
            $setting = Setting::withoutGlobalScopes()->where('key', $settingKey)->firstOrFail();
            $rule = $setting->rules()->where('name', $ruleName)->firstOrFail();
            $condition = $rule->conditions()->where('attribute', $attribute)->firstOrFail();

            $conditionModifier = new ConditionModifier($condition);
            $callback($conditionModifier);

            return $conditionModifier->apply();
        });
    }

    /**
     * Delete a condition from a rule.
     */
    protected function deleteCondition(string $settingKey, string $ruleName, string $attribute): void
    {
        $this->withinContext(function () use ($settingKey, $ruleName, $attribute) {
            $setting = Setting::withoutGlobalScopes()->where('key', $settingKey)->first();
            $rule = $setting?->rules()->where('name', $ruleName)->first();
            $rule?->conditions()->where('attribute', $attribute)->delete();
        });
    }

    /**
     * Update condition value directly.
     */
    protected function updateConditionValue(
        string $settingKey,
        string $ruleName,
        string $attribute,
        mixed $value
    ): SettingRuleCondition {
        return $this->modifyCondition($settingKey, $ruleName, $attribute, function (ConditionModifier $cond) use ($value) {
            $cond->updateValue($value);
        });
    }

    /**
     * Update condition operator directly.
     */
    protected function updateConditionOperator(
        string $settingKey,
        string $ruleName,
        string $attribute,
        string|ComparisonOperator $operator
    ): SettingRuleCondition {
        return $this->modifyCondition($settingKey, $ruleName, $attribute, function (ConditionModifier $cond) use ($operator) {
            $cond->updateOperator($operator);
        });
    }

    /**
     * Clear all conditions from a rule.
     */
    protected function clearConditions(string $settingKey, string $ruleName): void
    {
        $this->modifyRule($settingKey, $ruleName, function (RuleModifier $rule) {
            $rule->clearConditions();
        });
    }

    // =========================================================================
    // ROLLOUT VARIANT OPERATIONS
    // =========================================================================

    /**
     * Add a rollout rule with variants.
     *
     * @example
     * ```php
     * $this->addRollout('feature.checkout', 'ab_test', function (RolloutDefinition $rollout) {
     *     $rollout->variant('control', 50, 'v1')
     *         ->variant('treatment', 50, 'v2');
     * });
     * ```
     */
    protected function addRollout(string $settingKey, string $ruleName, Closure $callback, int $priority = 0): SettingRule
    {
        return $this->addRule($settingKey, function (RuleDefinition $rule) use ($ruleName, $callback, $priority) {
            $rule->name($ruleName)
                ->priority($priority)
                ->rollout($callback);
        });
    }

    /**
     * Add a simple A/B test (50/50 split).
     *
     * @example
     * ```php
     * $this->addABTest('feature.checkout', 'experiment', 'v1', 'v2');
     * ```
     */
    protected function addABTest(
        string $settingKey,
        string $ruleName,
        mixed $controlValue,
        mixed $treatmentValue,
        int $priority = 0
    ): SettingRule {
        return $this->addRollout($settingKey, $ruleName, function (RolloutDefinition $rollout) use ($controlValue, $treatmentValue) {
            $rollout->fiftyFifty($controlValue, $treatmentValue);
        }, $priority);
    }

    /**
     * Add a gradual rollout (percentage-based).
     *
     * @example
     * ```php
     * $this->addGradualRollout('feature.new_ui', 'rollout', true, 10.0); // 10% get the feature
     * ```
     */
    protected function addGradualRollout(
        string $settingKey,
        string $ruleName,
        mixed $value,
        float $percentage,
        int $priority = 0
    ): SettingRule {
        return $this->addRollout($settingKey, $ruleName, function (RolloutDefinition $rollout) use ($value, $percentage) {
            $rollout->gradual($percentage, $value);
        }, $priority);
    }

    /**
     * Add a rollout variant to a rule.
     *
     * @example
     * ```php
     * $this->addVariant('feature.checkout', 'ab_test', 'control', 50.0, 'v1');
     * $this->addVariant('feature.checkout', 'ab_test', 'treatment', 50.0, 'v2');
     * ```
     */
    protected function addVariant(
        string $settingKey,
        string $ruleName,
        string $variantName,
        float $weight,
        mixed $value = null
    ): SettingRuleRolloutVariant {
        return $this->withinContext(function () use ($settingKey, $ruleName, $variantName, $weight, $value) {
            $setting = Setting::withoutGlobalScopes()->where('key', $settingKey)->firstOrFail();
            $rule = $setting->rules()->where('name', $ruleName)->firstOrFail();

            // Ensure rule has a salt for rollout
            if (! $rule->rollout_salt) {
                $rule->update(['rollout_salt' => Str::random(16)]);
            }

            $variant = $rule->rolloutVariants()->create([
                'name' => $variantName,
                'weight' => (int) round($weight * 1000),
            ]);

            if ($value !== null) {
                $variant->value()->create([
                    'valuable_type' => $variant->getMorphClass(),
                    'valuable_id' => $variant->id,
                    'value' => $value,
                ]);
            }

            return $variant;
        });
    }

    /**
     * Modify a rollout variant.
     *
     * @example
     * ```php
     * $this->modifyVariant('feature.checkout', 'ab_test', 'treatment', function (VariantModifier $v) {
     *     $v->updateWeight(70.0);
     * });
     * ```
     */
    protected function modifyVariant(
        string $settingKey,
        string $ruleName,
        string $variantName,
        Closure $callback
    ): SettingRuleRolloutVariant {
        return $this->withinContext(function () use ($settingKey, $ruleName, $variantName, $callback) {
            $setting = Setting::withoutGlobalScopes()->where('key', $settingKey)->firstOrFail();
            $rule = $setting->rules()->where('name', $ruleName)->firstOrFail();
            $variant = $rule->rolloutVariants()->where('name', $variantName)->firstOrFail();

            $variantModifier = new VariantModifier($variant);
            $callback($variantModifier);

            return $variantModifier->apply();
        });
    }

    /**
     * Delete a rollout variant.
     */
    protected function deleteVariant(string $settingKey, string $ruleName, string $variantName): void
    {
        $this->withinContext(function () use ($settingKey, $ruleName, $variantName) {
            $setting = Setting::withoutGlobalScopes()->where('key', $settingKey)->first();
            $rule = $setting?->rules()->where('name', $ruleName)->first();
            $variant = $rule?->rolloutVariants()->where('name', $variantName)->first();
            $variant?->value?->delete();
            $variant?->delete();
        });
    }

    /**
     * Update variant weight directly.
     */
    protected function updateVariantWeight(
        string $settingKey,
        string $ruleName,
        string $variantName,
        float $weight
    ): SettingRuleRolloutVariant {
        return $this->modifyVariant($settingKey, $ruleName, $variantName, function (VariantModifier $v) use ($weight) {
            $v->updateWeight($weight);
        });
    }

    /**
     * Update variant value directly.
     */
    protected function updateVariantValue(
        string $settingKey,
        string $ruleName,
        string $variantName,
        mixed $value
    ): SettingRuleRolloutVariant {
        return $this->modifyVariant($settingKey, $ruleName, $variantName, function (VariantModifier $v) use ($value) {
            $v->updateValue($value);
        });
    }

    /**
     * Regenerate the rollout salt to re-randomize user assignments.
     */
    protected function regenerateSalt(string $settingKey, string $ruleName): SettingRule
    {
        return $this->modifyRule($settingKey, $ruleName, function (RuleModifier $rule) {
            $rule->regenerateSalt();
        });
    }

    // =========================================================================
    // VALUE OPERATIONS
    // =========================================================================

    /**
     * Get the setting value record.
     */
    protected function getSettingValue(string $settingKey): ?SettingValue
    {
        $setting = $this->get($settingKey);

        return $setting?->defaultValue;
    }

    /**
     * Get the rule value record.
     */
    protected function getRuleValue(string $settingKey, string $ruleName): ?SettingValue
    {
        $setting = Setting::withoutGlobalScopes()->where('key', $settingKey)->first();
        $rule = $setting?->rules()->where('name', $ruleName)->first();

        return $rule?->value;
    }

    // =========================================================================
    // BULK OPERATIONS
    // =========================================================================

    /**
     * Delete multiple settings at once.
     *
     * @param  array<string>  $keys
     */
    protected function deleteMany(array $keys): void
    {
        $this->withinContext(function () use ($keys) {
            Setting::withoutGlobalScopes()->whereIn('key', $keys)->each(function ($setting) {
                $setting->delete();
            });
        });
    }

    /**
     * Execute raw database operations within the forced context.
     *
     * Useful for complex migrations that need direct database access.
     *
     * @example
     * ```php
     * $this->raw(function () {
     *     DB::table('settings')->where('type', 'string')->update(['type' => 'text']);
     * });
     * ```
     */
    protected function raw(Closure $callback): mixed
    {
        return $this->withinContext(fn () => DB::transaction($callback));
    }

    // =========================================================================
    // TIME-BOUNDED RULES
    // =========================================================================

    /**
     * Add a time-bounded rule (scheduled activation).
     *
     * @example
     * ```php
     * $this->addScheduledRule(
     *     'feature.holiday_theme',
     *     'christmas',
     *     'festive',
     *     '2025-12-01',
     *     '2025-12-31'
     * );
     * ```
     */
    protected function addScheduledRule(
        string $settingKey,
        string $ruleName,
        mixed $value,
        string $startsAt,
        string $endsAt,
        int $priority = 0
    ): SettingRule {
        return $this->addRule($settingKey, function (RuleDefinition $rule) use ($ruleName, $value, $startsAt, $endsAt, $priority) {
            $rule->name($ruleName)
                ->priority($priority)
                ->between($startsAt, $endsAt)
                ->then($value);
        });
    }

    /**
     * Update the time bounds of a rule.
     */
    protected function updateRuleTimeBounds(
        string $settingKey,
        string $ruleName,
        ?string $startsAt,
        ?string $endsAt
    ): SettingRule {
        return $this->modifyRule($settingKey, $ruleName, function (RuleModifier $rule) use ($startsAt, $endsAt) {
            $rule->updateTimeBounds($startsAt, $endsAt);
        });
    }

    /**
     * Remove time bounds from a rule (make it always active).
     */
    protected function removeRuleTimeBounds(string $settingKey, string $ruleName): SettingRule
    {
        return $this->modifyRule($settingKey, $ruleName, function (RuleModifier $rule) {
            $rule->removeTimeBounds();
        });
    }

    // =========================================================================
    // CONTEXT MANAGEMENT
    // =========================================================================

    /**
     * Execute callback within forced context (bypasses immutability).
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function withinContext(Closure $callback): mixed
    {
        FulcrumContext::force(true);

        try {
            return $callback();
        } finally {
            FulcrumContext::force(false);
        }
    }
}
