<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Models;

use GaiaTools\FulcrumSettings\Exceptions\ImmutableSettingException;
use GaiaTools\FulcrumSettings\Facades\Fulcrum;
use GaiaTools\FulcrumSettings\Models\Concerns\HasMaskedValue;
use GaiaTools\FulcrumSettings\Models\Scopes\TenantScope;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Represents a weighted variant within a percentage rollout rule.
 *
 * @property int $id
 * @property int $setting_rule_id
 * @property string|null $tenant_id
 * @property string $name
 * @property int $weight Weight in basis points (0-100000 for 0.000%-100.000%)
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read SettingRule $rule
 * @property-read SettingValue|null $value
 * @property-read float $weight_percentage
 */
class SettingRuleRolloutVariant extends Model
{
    use HasMaskedValue;

    /**
     * Weight precision: 100000 = 100.000% (3 decimal places)
     */
    public const WEIGHT_PRECISION = 100_000;

    protected $table = 'setting_rule_rollout_variants';

    protected $fillable = [
        'setting_rule_id',
        'tenant_id',
        'name',
        'weight',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SettingRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(SettingRule::class, 'setting_rule_id');
    }

    /**
     * @return MorphOne<SettingValue, $this>
     */
    public function value(): MorphOne
    {
        return $this->morphOne(SettingValue::class, 'valuable');
    }

    /**
     * Get the resolved value for this variant.
     */
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
        /** @var SettingRule|null $rule */
        $rule = $this->relationLoaded('rule') ? $this->getRelation('rule') : $this->rule;

        return $rule ? $rule->setting : null;
    }

    /**
     * Get the weight as a percentage (0.000 - 100.000).
     */
    /**
     * @return Attribute<float, float>
     */
    public function weightPercentage(): Attribute
    {
        /** @var Attribute<float, float> $attribute */
        $attribute = new Attribute(
            get: fn (): float => $this->weight / 1000
        );

        return $attribute;
    }

    /**
     * Set weight from a percentage value.
     */
    public function setWeightFromPercentage(float $percentage): self
    {
        $this->weight = (int) round($percentage * 1000);

        return $this;
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (self $model) {
            if (Fulcrum::isMultiTenancyEnabled() && $model->tenant_id === null) {
                $rule = SettingRule::find($model->setting_rule_id);
                $setting = $rule ? Setting::find($rule->setting_id) : null;
                if ($setting) {
                    $model->tenant_id = $setting->tenant_id;
                }
            }
        });

        // Immutability guards
        $guard = function (self $model) {
            $setting = $model->rule->setting;
            if ($setting->immutable && ! FulcrumContext::shouldForce()) {
                throw new ImmutableSettingException('Setting is immutable. Changes are not allowed.');
            }
        };

        static::creating($guard);
        static::updating($guard);
        static::deleting(function (self $model) {
            $setting = $model->rule->setting;
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
}
