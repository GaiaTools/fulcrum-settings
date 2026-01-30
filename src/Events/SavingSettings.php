<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Events;

class SavingSettings
{
    public function __construct(
        public array $settings
    ) {}
}
