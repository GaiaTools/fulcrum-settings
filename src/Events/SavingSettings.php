<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Events;

class SavingSettings
{
    /**
     * @param  array<string, mixed>  $settings
     */
    public function __construct(
        public array $settings
    ) {}
}
