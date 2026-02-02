<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Models;

use GaiaTools\FulcrumSettings\Exceptions\ImmutableSettingException;
use GaiaTools\FulcrumSettings\Facades\Fulcrum;
use GaiaTools\FulcrumSettings\Models\Concerns\HasMaskedValue;
use GaiaTools\FulcrumSettings\Models\Scopes\TenantScope;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $setting_id
 * @property string|null $tenant_id
 * @property string|null $name
 * @property int $priority
 * @property string|null $rollout_salt
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Setting $setting
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SettingRule> $rules
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SettingRuleCondition> $conditions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SettingRuleRolloutVariant> $rolloutVariants
 * @property-read SettingValue|null $value
 */
class SettingRule extends Model
{
    use HasMaskedValue;

    protected $fillable = [
        'setting_id',
        'tenant_id',
        'name',
        'priority',
        'rollout_salt',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Setting, $this>
     */
    public function setting(): BelongsTo
    {
        return $this->belongsTo(Setting::class);
    }

    /**
     * @return HasMany<SettingRuleCondition, $this>
     */
    public function conditions(): HasMany
    {
        return $this->hasMany(SettingRuleCondition::class);
    }

    /** @return HasMany<SettingRule, $this> */
    public function rules(): HasMany
    {
        // A rule doesn't have child rules in the current schema
        return $this->hasMany(SettingRule::class, 'id', 'id')->whereRaw('1=0');
    }

    /**
     * @return MorphOne<SettingValue, $this>
     */
    public function value(): MorphOne
    {
        return $this->morphOne(SettingValue::class, 'valuable');
    }

    public function getValue(): mixed
    {
        $valueModel = $this->value;

        if (! $valueModel) {
            return null;
        }

        return $this->applyMasking($valueModel->value);
    }

    /**
     * Resolve the parent setting model.
     */
    public function resolveSetting(): ?Setting
    {
        return $this->setting;
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (self $model) {
            if (Fulcrum::isMultiTenancyEnabled() && $model->tenant_id === null) {
                $setting = Setting::find($model->setting_id);
                if ($setting) {
                    $model->tenant_id = $setting->tenant_id;
                }
            }
        });

        $guard = function (self $model) {
            $setting = $model->setting;
            if ($setting->immutable && ! FulcrumContext::shouldForce()) {
                throw new ImmutableSettingException('Setting is immutable. Changes are not allowed.');
            }
        };

        static::creating($guard);
        static::updating($guard);
        static::deleting(function (self $model) {
            $setting = $model->setting;
            if ($setting->immutable && ! FulcrumContext::shouldForce()) {
                $ability = config('fulcrum.immutability.delete_ability', 'deleteImmutableSetting');
                $ability = is_string($ability) ? $ability : 'deleteImmutableSetting';
                if (Gate::allows($ability, $setting)) {
                    return true;
                }
                throw new ImmutableSettingException('Setting is immutable. Deletion is not allowed.');
            }
        });
    }

    /**
     * @return HasMany<SettingRuleRolloutVariant, $this>
     */
    public function rolloutVariants(): HasMany
    {
        return $this->hasMany(SettingRuleRolloutVariant::class);
    }

    /**
     * Check if this rule is currently active based on its time bounds.
     */
    public function isActive(): bool
    {
        $now = Carbon::now();

        if ($this->starts_at !== null && $now->lessThan($this->starts_at)) {
            return false;
        }

        if ($this->ends_at !== null && $now->greaterThan($this->ends_at)) {
            return false;
        }

        return true;
    }

    /**
     * Check if this rule uses percentage-based rollout variants.
     */
    public function hasRolloutVariants(): bool
    {
        // Check if relation is loaded to avoid N+1
        if ($this->relationLoaded('rolloutVariants')) {
            return $this->rolloutVariants->isNotEmpty();
        }

        return $this->rolloutVariants()->exists();
    }

    /**
     * Check if this rule returns a direct value (not a rollout).
     */
    public function hasDirectValue(): bool
    {
        return ! $this->hasRolloutVariants();
    }

    /**
     * Get the total weight of all rollout variants.
     */
    public function getTotalRolloutWeightAttribute(): int
    {
        if ($this->relationLoaded('rolloutVariants')) {
            $sum = $this->rolloutVariants->sum('weight');

            return is_numeric($sum) ? (int) $sum : 0;
        }

        $sum = $this->rolloutVariants()->sum('weight');

        return (int) $sum;
    }

    /**
     * Get the total weight as a percentage (0.000 - 100.000).
     */
    public function getTotalRolloutPercentageAttribute(): float
    {
        return $this->total_rollout_weight / 1000;
    }

    /**
     * Reset rollout assignments by regenerating the salt.
     * This causes all users to be re-bucketed.
     */
    public function resetRolloutAssignments(): self
    {
        $this->update([
            'rollout_salt' => Str::random(16),
        ]);

        return $this;
    }

    /**
     * Get the effective salt for bucket calculation.
     * Falls back to setting key if no salt is set.
     */
    public function getEffectiveSalt(): string
    {
        return $this->rollout_salt ?? $this->setting->key;
    }
}
