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
            get: function ($value): mixed {
                if ($value === null) {
                    return null;
                }

                $setting = $this->resolveSetting();
                if ($setting && $setting->masked && is_string($value)) {
                    try {
                        $value = Crypt::decryptString($value);
                    } catch (\Throwable) {
                        // If decryption fails, treat as raw
                    }
                }

                if ($setting) {
                    return app(TypeRegistry::class)->getHandler($setting->type)->get($value);
                }

                return $value;
            },
            set: function ($value, $attributes): mixed {
                if ($value === null) {
                    return null;
                }

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

                if (! $setting && ! $this->exists) {
                    // When creating, the relation might not be resolvable if the owner is not yet persisted
                    // but in SettingDefinition::save(), we create Setting first, then defaultValue.
                    // If we are here, it means resolveSetting(Setting::class, $id) failed.
                    if ($type && in_array($type, [Setting::class, SettingRule::class, SettingRuleRolloutVariant::class]) && $id) {
                        return $value;
                    }

                    // If we have no type/id yet, it might be a mass-assignment where value is set before other fields
                    if ($type === null || $id === null) {
                        return $value;
                    }
                }

                if (! $setting) {
                    // Fallback to manual resolution if we have the IDs in the model instance
                    if ($this->valuable_type && $this->valuable_id) {
                        $setting = $this->resolveSetting($this->valuable_type, $this->valuable_id);
                    }
                }

                if (! $setting) {
                    $typeLabel = is_scalar($type) ? (string) $type : 'unknown';
                    $idLabel = is_scalar($id) ? (string) $id : 'unknown';
                    throw new SettingNotFoundException("Setting record not found for value. (Type: {$typeLabel}, ID: {$idLabel})");
                }

                $handler = app(TypeRegistry::class)->getHandler($setting->type);
                $serialized = $handler->set($value);

                if ($setting->masked) {
                    if (! is_string($serialized)) {
                        if (is_scalar($serialized)) {
                            $serialized = (string) $serialized;
                        } elseif (is_object($serialized) && method_exists($serialized, '__toString')) {
                            $serialized = (string) $serialized;
                        } else {
                            $serialized = json_encode($serialized) ?: '';
                        }
                    }

                    return Crypt::encryptString($serialized);
                }

                return $serialized;
            }
        );
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

        if ($owner instanceof Setting) {
            return $owner;
        }
        if ($owner instanceof SettingRule) {
            // Make sure we don't recurse if setting is already being resolved
            return $owner->setting()->getResults();
        }

        if ($owner instanceof SettingRuleRolloutVariant) {
            return $owner->resolveSetting();
        }

        return null;
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
