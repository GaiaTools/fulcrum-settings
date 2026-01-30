<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Models\Scopes;

use GaiaTools\FulcrumSettings\Contracts\TenantResolver;
use GaiaTools\FulcrumSettings\Facades\Fulcrum;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (! Fulcrum::isMultiTenancyEnabled()) {
            return;
        }

        $tenantId = $this->resolveTenantId();

        // Use a unique group for the scope to avoid conflicts with other where clauses
        $builder->where(function (Builder $query) use ($tenantId, $model) {
            if ($tenantId) {
                $query->where($model->getTable().'.tenant_id', $tenantId)
                    ->orWhereNull($model->getTable().'.tenant_id');
            } else {
                $query->whereNull($model->getTable().'.tenant_id');
            }
        });
    }

    public function extend(Builder $builder): void
    {
        $builder->macro('withoutTenant', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });
    }

    protected function resolveTenantId(): ?string
    {
        $resolver = config('fulcrum.multi_tenancy.tenant_resolver');

        if (is_string($resolver) && class_exists($resolver) && ($instance = app($resolver)) instanceof TenantResolver) {
            return $instance->resolve();
        }

        return is_callable($resolver)
            ? $resolver()
            : FulcrumContext::getTenantId();
    }
}
