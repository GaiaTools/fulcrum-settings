<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Events;

use GaiaTools\FulcrumSettings\Models\Setting;

abstract class SettingEvent
{
    public function __construct(
        public Setting $setting
    ) {}
}
