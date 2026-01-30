<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\Builders;

use BackedEnum;
use Closure;
use GaiaTools\FulcrumSettings\Exceptions\InvalidSettingValueException;
use GaiaTools\FulcrumSettings\Exceptions\InvalidTypeHandlerException;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use GaiaTools\FulcrumSettings\Support\TypeRegistry;

class SettingBuilder
{
    protected string $type = 'string';

    protected mixed $defaultValue = null;

    protected string $description = '';

    /** @var array<int, RuleBuilder> */
    protected array $rules = [];

    protected bool $masked = false;

    protected bool $immutable = false;

    public function __construct(
        protected string $key
    ) {}

    public static function define(string $key): self
    {
        return new self($key);
    }

    public function type(string|BackedEnum $type): self
    {
        $typeString = $type instanceof BackedEnum ? (string) $type->value : $type;
        $typeRegistry = app(TypeRegistry::class);

        if (! $typeRegistry->has($typeString)) {
            throw InvalidTypeHandlerException::notRegistered($typeString);
        }

        $this->type = $typeString;

        return $this;
    }

    public function default(mixed $value): self
    {
        $this->defaultValue = $value;

        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function rule(Closure $callback): self
    {
        $ruleBuilder = new RuleBuilder;
        $callback($ruleBuilder);
        $this->rules[] = $ruleBuilder;

        return $this;
    }

    public function masked(): self
    {
        $this->masked = true;

        return $this;
    }

    public function immutable(): self
    {
        $this->immutable = true;

        return $this;
    }

    public function save(): Setting
    {
        $typeRegistry = app(TypeRegistry::class);
        $handler = $typeRegistry->getHandler($this->type);

        // Validate before storing
        if ($this->defaultValue !== null && ! $handler->validate($this->defaultValue)) {
            throw InvalidSettingValueException::forSetting($this->key, $this->type, $this->defaultValue);
        }

        FulcrumContext::force(true);

        try {
            $setting = Setting::create([
                'key' => $this->key,
                'type' => $this->type,
                'description' => $this->description,
                'masked' => $this->masked,
                'immutable' => $this->immutable,
            ]);

            $setting->defaultValue()->create([
                'valuable_type' => $setting->getMorphClass(),
                'valuable_id' => $setting->id,
                'value' => $this->defaultValue,
            ]);

            foreach ($this->rules as $ruleBuilder) {
                $ruleBuilder->createFor($setting);
            }

            return $setting;
        } finally {
            FulcrumContext::force(false);
        }
    }
}
