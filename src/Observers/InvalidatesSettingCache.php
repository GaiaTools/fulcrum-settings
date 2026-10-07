<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Observers;

use GaiaTools\FulcrumSettings\Support\Cache\CacheInvalidator;
use Illuminate\Database\Eloquent\Model;

class InvalidatesSettingCache
{
    public function created(Model $model): void
    {
        $this->deleted($model);
    }

    public function updated(Model $model): void
    {
        $this->deleted($model);
    }

    public function deleted(Model $model): void
    {
        if (config()->boolean('fulcrum.cache.enabled', false)) {
            CacheInvalidator::configured()->invalidateAfterCommit($model->getConnection());
        }
    }
}
