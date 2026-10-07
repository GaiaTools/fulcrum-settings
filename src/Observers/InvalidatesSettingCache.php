<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Observers;

use GaiaTools\FulcrumSettings\Support\Cache\CacheInvalidator;
use Illuminate\Database\Eloquent\Model;

class InvalidatesSettingCache
{
    public function saved(Model $model): void
    {
        if (config()->boolean('fulcrum.cache.enabled', false)) {
            CacheInvalidator::configured()->invalidateAfterCommit($model->getConnection());
        }
    }

    public function deleted(Model $model): void
    {
        $this->saved($model);
    }
}
