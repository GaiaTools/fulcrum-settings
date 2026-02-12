<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Models;

use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\Exceptions\ImmutableSettingException;
use GaiaTools\FulcrumSettings\Models\Concerns\HasMaskedValue;
use GaiaTools\FulcrumSettings\Models\Scopes\TenantScope;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * @property int $id
 * @property string $key
 * @property string|null $group
 * @property SettingType $type
 * @property string|null $description
 * @property bool $masked
 * @property bool $immutable
 * @property string|null $tenant_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Setting extends Model
{
    use HasMaskedValue;

    protected $fillable = [
        'key',
        'group',
        'tenant_id',
        'type',
        'description',
        'masked',
        'immutable',
    ];

    protected function casts(): array
    {
        return [
            'type' => SettingType::class,
            'masked' => 'boolean',
            'immutable' => 'boolean',
        ];
    }

    /**
     * @return HasMany<SettingRule, $this>
     */
    public function rules(): HasMany
    {
        return $this->hasMany(SettingRule::class);
    }

    /**
     * @return MorphOne<SettingValue, $this>
     */
    public function defaultValue(): MorphOne
    {
        return $this->morphOne(SettingValue::class, 'valuable');
    }

    public function getDefaultValue(): mixed
    {
        $valueModel = $this->defaultValue;

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
        return $this;
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);

        static::saving(function (self $model) {
            if (! $model->isDirty('key') && $model->group !== null) {
                return;
            }

            $model->group = self::deriveGroup($model->key);
        });

        // Prevent updates if immutable (unless forced)
        static::updating(function (self $model) {
            if ($model->immutable && ! FulcrumContext::shouldForce()) {
                throw new ImmutableSettingException('Setting is immutable. Changes are not allowed.');
            }
        });

        // Control deletion: allow via force, or via gate if enabled
        static::deleting(function (self $model) {
            if (! $model->immutable || FulcrumContext::shouldForce()) {
                return true;
            }

            $ability = config('fulcrum.immutability.delete_ability', 'deleteImmutableSetting');
            $ability = is_string($ability) ? $ability : 'deleteImmutableSetting';
            if ((bool) config('fulcrum.immutability.allow_delete_via_gate', true) && Gate::allows($ability, $model)) {
                return true;
            }

            throw new ImmutableSettingException('Setting is immutable. Deletion is not allowed.');
        });
    }

    private static function deriveGroup(string $key): ?string
    {
        $position = strrpos($key, '.');

        if ($position === false) {
            return null;
        }

        $group = substr($key, 0, $position);

        return $group !== '' ? $group : null;
    }
}
