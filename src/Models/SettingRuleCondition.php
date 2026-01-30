<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Models;

use GaiaTools\FulcrumSettings\Enums\ComparisonOperator;
use GaiaTools\FulcrumSettings\Enums\ConditionType;
use GaiaTools\FulcrumSettings\Exceptions\ImmutableSettingException;
use GaiaTools\FulcrumSettings\Facades\Fulcrum;
use GaiaTools\FulcrumSettings\Models\Scopes\TenantScope;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $setting_rule_id
 * @property string|null $tenant_id
 * @property string|null $type
 * @property string $attribute
 * @property ComparisonOperator $operator
 * @property mixed $value
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property SettingRule $rule
 */
class SettingRuleCondition extends Model
{
    protected $fillable = [
        'setting_rule_id',
        'tenant_id',
        'type',
        'attribute',
        'operator',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'operator' => ComparisonOperator::class,
            'value' => 'json',
        ];
    }

    /**
     * @return BelongsTo<SettingRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(SettingRule::class, 'setting_rule_id');
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (self $model) {
            if (! $model->type) {
                $model->type = ConditionType::default();
            }

            if (Fulcrum::isMultiTenancyEnabled() && $model->tenant_id === null) {
                $rule = SettingRule::find($model->setting_rule_id);
                $setting = $rule ? Setting::find($rule->setting_id) : null;
                if ($setting) {
                    $model->tenant_id = $setting->tenant_id;
                }
            }
        });

        $guard = function (self $model) {
            $setting = $model->rule->setting;
            if ($setting->immutable && ! FulcrumContext::shouldForce()) {
                throw new ImmutableSettingException('Setting is immutable. Changes are not allowed.');
            }
        };

        static::creating($guard);
        static::updating($guard);
        static::deleting($guard);
    }
}
