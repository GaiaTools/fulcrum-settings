<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Events;

class SettingsLoaded
{
    /**
     * @param  array<string, mixed>  $settings
     */
    public function __construct(
        public array $settings
    ) {}
}
