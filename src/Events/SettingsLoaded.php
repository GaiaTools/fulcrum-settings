<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Events;

class SettingsLoaded
{
    public function __construct(
        public array $settings
    ) {}
}
