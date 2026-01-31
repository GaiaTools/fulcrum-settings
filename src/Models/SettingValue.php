<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Models;

use GaiaTools\FulcrumSettings\Exceptions\ImmutableSettingException;
use GaiaTools\FulcrumSettings\Exceptions\SettingNotFoundException;
use GaiaTools\FulcrumSettings\Facades\Fulcrum;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use GaiaTools\FulcrumSettings\Support\TypeRegistry;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;

/**
 * @property int $id
 * @property string $valuable_type
 * @property int $valuable_id
 * @property string|null $tenant_id
 * @property mixed $value
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Model $valuable
 */
class SettingValue extends Model
{
    protected $fillable = [
        'valuable_type',
        'valuable_id',
        'tenant_id',
        'value',
    ];

    // We implement custom accessors/mutators for conditional encryption,
    // so we don't use native JSON casting here.

    /**
     * @return MorphTo<Model, $this>
     */
    public function valuable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Accessor/Mutator: Handle type resolution, serialization/deserialization, and encryption.
     */
    /**
     * @return Attribute<mixed, mixed>
     */
    public function value(): Attribute
    {
        return Attribute::make(
            get: [$this, 'getValueAttribute'],
            set: [$this, 'setValueAttribute']
        );
    }

    protected function getValueAttribute(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $setting = $this->resolveSetting();
        $value = $this->maybeDecryptValue($setting, $value);

        return $this->castValueFromSetting($setting, $value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function setValueAttribute(mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        [$setting, $type, $id] = $this->resolveSettingFromAttributes($attributes);

        if (! $setting && $this->shouldReturnRawValue($type, $id)) {
            return $value;
        }

        if (! $setting) {
            $setting = $this->resolveSettingFromModel();
        }

        if (! $setting) {
            $typeLabel = is_scalar($type) ? (string) $type : 'unknown';
            $idLabel = is_scalar($id) ? (string) $id : 'unknown';
            throw new SettingNotFoundException("Setting record not found for value. (Type: {$typeLabel}, ID: {$idLabel})");
        }

        return $this->serializeValueForStorage($setting, $value);
    }

    /**
     * Return decrypted and cast value.
     *
     * @deprecated Use the value attribute directly instead.
     */
    public function decrypted(): mixed
    {
        return $this->value;
    }

    /**
     * Resolve parent setting from polymorphic relation owner.
     */
    protected function resolveSetting(?string $type = null, mixed $id = null): ?Setting
    {
        // Try to get setting from valuable relation
        $owner = $this->relationLoaded('valuable') ? $this->getRelation('valuable') : null;

        if (! $owner) {
            // If the relation is not loaded, try to find it via the IDs
            $type = $type ?? $this->valuable_type;
            $id = $id ?? $this->valuable_id;

            if ($type === Setting::class) {
                $owner = Setting::query()->find($id);
            } elseif ($type === SettingRule::class) {
                $owner = SettingRule::query()->find($id);
            } elseif ($type === SettingRuleRolloutVariant::class) {
                $owner = SettingRuleRolloutVariant::query()->find($id);
            }
        }

        $setting = null;

        if ($owner instanceof Setting) {
            $setting = $owner;
        } elseif ($owner instanceof SettingRule) {
            // Make sure we don't recurse if setting is already being resolved
            $setting = $owner->setting()->getResults();
        } elseif ($owner instanceof SettingRuleRolloutVariant) {
            $setting = $owner->resolveSetting();
        }

        return $setting;
    }

    protected function maybeDecryptValue(?Setting $setting, mixed $value): mixed
    {
        if (! $setting || ! $setting->masked || ! is_string($value)) {
            return $value;
        }

        $decrypted = $this->tryDecryptString($value);

        return $decrypted ?? $value;
    }

    protected function tryDecryptString(string $value): ?string
    {
        $decrypted = null;

        try {
            $decrypted = Crypt::decryptString($value);
        } catch (\Throwable) {
            $decrypted = null;
        }

        return $decrypted;
    }

    protected function castValueFromSetting(?Setting $setting, mixed $value): mixed
    {
        if (! $setting) {
            return $value;
        }

        return app(TypeRegistry::class)->getHandler($setting->type)->get($value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{0: ?Setting, 1: mixed, 2: mixed}
     */
    protected function resolveSettingFromAttributes(array $attributes): array
    {
        // Determine setting context. Handle various Laravel versions and population states.
        $type = $attributes['valuable_type'] ?? $this->valuable_type ?? $this->attributes['valuable_type'] ?? null;
        $id = $attributes['valuable_id'] ?? $this->valuable_id ?? $this->attributes['valuable_id'] ?? null;

        $typeString = is_string($type) ? $type : null;
        $idValue = is_scalar($id) ? $id : null;
        $setting = $this->resolveSetting($typeString, $idValue);

        if (! $setting) {
            // Try with raw valuable_id from attributes if it differs from cast/accessed id
            $id = $attributes['valuable_id'] ?? $id;
            $idValue = is_scalar($id) ? $id : null;
            $setting = $this->resolveSetting($typeString, $idValue);
        }

        return [$setting, $type, $id];
    }

    protected function shouldReturnRawValue(mixed $type, mixed $id): bool
    {
        return match (true) {
            $this->exists => false,
            $type && in_array($type, [Setting::class, SettingRule::class, SettingRuleRolloutVariant::class]) && $id => true,
            $type === null || $id === null => true,
            default => false,
        };
    }

    protected function resolveSettingFromModel(): ?Setting
    {
        $setting = null;

        if ($this->valuable_type && $this->valuable_id) {
            $setting = $this->resolveSetting($this->valuable_type, $this->valuable_id);
        }

        return $setting;
    }

    protected function serializeValueForStorage(Setting $setting, mixed $value): mixed
    {
        $handler = app(TypeRegistry::class)->getHandler($setting->type);
        $serialized = $handler->set($value);

        if ($setting->masked) {
            $serialized = $this->ensureStringValue($serialized);

            return Crypt::encryptString($serialized);
        }

        return $serialized;
    }

    protected function ensureStringValue(mixed $value): string
    {
        $result = '';

        if (is_string($value)) {
            $result = $value;
        } elseif (is_scalar($value)) {
            $result = (string) $value;
        } elseif (is_object($value) && method_exists($value, '__toString')) {
            $result = (string) $value;
        } else {
            $result = json_encode($value) ?: '';
        }

        return $result;
    }

    protected static function booted(): void
    {
        $guard = function (self $model) {
            $setting = $model->resolveSetting();
            if ($setting && $setting->immutable && ! FulcrumContext::shouldForce()) {
                throw new ImmutableSettingException('Setting is immutable. Changes are not allowed.');
            }
        };

        static::creating(function (self $model) {
            if (Fulcrum::isMultiTenancyEnabled() && $model->tenant_id === null) {
                $setting = $model->resolveSetting();
                if ($setting) {
                    $model->tenant_id = $setting->tenant_id;
                }
            }
        });

        static::updating($guard);
        static::deleting(function (self $model) {
            $setting = $model->resolveSetting();
            if ($setting && $setting->immutable && ! FulcrumContext::shouldForce()) {
                if ((bool) config('fulcrum.immutability.allow_delete_via_gate', true)) {
                    $ability = config('fulcrum.immutability.delete_ability', 'deleteImmutableSetting');
                    $ability = is_string($ability) ? $ability : 'deleteImmutableSetting';
                    if (Gate::allows($ability, $setting)) {
                        return true;
                    }
                }
                throw new ImmutableSettingException('Setting is immutable. Deletion is not allowed.');
            }
        });
    }
}
