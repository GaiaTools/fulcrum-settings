<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\Traits;

use GaiaTools\FulcrumSettings\Support\Builders\SettingBuilder;

/** @phpstan-ignore-next-line */
trait InteractsWithSettings
{
    /**
     * Start building a new setting.
     */
    protected function createSetting(string $key): SettingBuilder
    {
        return SettingBuilder::define($key);
    }
}
