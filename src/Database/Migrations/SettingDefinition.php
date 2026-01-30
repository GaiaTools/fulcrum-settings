<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Database\Migrations;

use BackedEnum;
use Closure;
use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\Exceptions\InvalidSettingValueException;
use GaiaTools\FulcrumSettings\Exceptions\InvalidTypeHandlerException;
use GaiaTools\FulcrumSettings\Facades\Fulcrum;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Support\TypeRegistry;

/**
 * Fluent builder for defining a new setting in a migration.
 *
 * @example
 * ```php
 * $this->create('feature.new_checkout', function (SettingDefinition $setting) {
 *     $setting->type('boolean')
 *         ->default(false)
 *         ->description('Enable new checkout flow')
 *         ->masked()
 *         ->immutable()
 *         ->rule(function (RuleDefinition $rule) {
 *             $rule->name('beta_users')
 *                 ->when('user.is_beta', 'is_true')
 *                 ->then(true);
 *         });
 * });
 * ```
 */
class SettingDefinition
{
    protected string $type = 'string';

    protected mixed $defaultValue = null;

    protected string $description = '';

    /** @var array<int, RuleDefinition> */
    protected array $rules = [];

    protected bool $masked = false;

    protected bool $immutable = false;

    protected ?string $tenantId = null;

    public function __construct(
        protected readonly string $key
    ) {}

    /**
     * Set the setting type.
     */
    public function type(string|BackedEnum $type): self
    {
        $typeString = $type instanceof BackedEnum ? $type->value : $type;
        $typeRegistry = app(TypeRegistry::class);

        if (! $typeRegistry->has($typeString)) {
            throw InvalidTypeHandlerException::notRegistered($typeString);
        }

        $this->type = $typeString;

        return $this;
    }

    /**
     * Set the setting type to string.
     */
    public function string(): self
    {
        return $this->type(SettingType::STRING);
    }

    /**
     * Set the setting type to integer.
     */
    public function integer(): self
    {
        return $this->type(SettingType::INTEGER);
    }

    /**
     * Set the setting type to float.
     */
    public function float(): self
    {
        return $this->type(SettingType::FLOAT);
    }

    /**
     * Set the setting type to boolean.
     */
    public function boolean(): self
    {
        return $this->type(SettingType::BOOLEAN);
    }

    /**
     * Set the setting type to array.
     */
    public function array(): self
    {
        return $this->type(SettingType::JSON);
    }

    /**
     * Set the setting type to JSON.
     */
    public function json(): self
    {
        return $this->type(SettingType::JSON);
    }

    /**
     * Set the default value for the setting.
     */
    public function default(mixed $value): self
    {
        $this->defaultValue = $value;

        return $this;
    }

    /**
     * Set the description for the setting.
     */
    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Mark the setting value as masked (encrypted at rest).
     */
    public function masked(): self
    {
        $this->masked = true;

        return $this;
    }

    /**
     * Mark the setting as immutable (cannot be changed without force).
     */
    public function immutable(): self
    {
        $this->immutable = true;

        return $this;
    }

    /**
     * Set the tenant ID for multi-tenancy support.
     */
    public function forTenant(?string $tenantId): self
    {
        $this->tenantId = $tenantId;

        return $this;
    }

    /**
     * Add a rule to this setting.
     */
    public function rule(Closure $callback): self
    {
        $ruleDefinition = new RuleDefinition;
        $callback($ruleDefinition);
        $this->rules[] = $ruleDefinition;

        return $this;
    }

    /**
     * Add multiple rules at once.
     *
     * @param  array<Closure>  $callbacks
     */
    public function rules(array $callbacks): self
    {
        foreach ($callbacks as $callback) {
            $this->rule($callback);
        }

        return $this;
    }

    /**
     * Get the setting key.
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * Persist the setting definition to the database.
     */
    public function save(): Setting
    {
        $typeRegistry = app(TypeRegistry::class);
        $handler = $typeRegistry->getHandler($this->type);

        // Validate default value if provided
        if ($this->defaultValue !== null && ! $handler->validate($this->defaultValue)) {
            throw InvalidSettingValueException::forSetting($this->key, $this->type, $this->defaultValue);
        }

        $attributes = [
            'key' => $this->key,
            'type' => $this->type,
            'description' => $this->description,
            'masked' => $this->masked,
            'immutable' => $this->immutable,
        ];

        if (Fulcrum::isMultiTenancyEnabled() && $this->hasTenantId()) {
            $attributes['tenant_id'] = $this->tenantId;
        }

        $setting = Setting::create($attributes);

        // Create the default value
        $setting->defaultValue()->create([
            'valuable_type' => $setting->getMorphClass(),
            'valuable_id' => $setting->getKey(),
            'value' => $this->defaultValue,
        ]);

        // Create all rules
        foreach ($this->rules as $ruleDefinition) {
            $ruleDefinition->createFor($setting);
        }

        return $setting;
    }

    public function hasTenantId(): bool
    {
        return $this->tenantId !== null;
    }
}
