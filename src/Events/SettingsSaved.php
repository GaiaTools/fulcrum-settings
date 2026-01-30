<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Events;

class SettingsSaved
{
    public function __construct(
        public array $settings
    ) {}
}
