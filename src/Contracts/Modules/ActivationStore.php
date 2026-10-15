<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Contracts\Modules;

interface ActivationStore
{
    /** @return array<array-key, mixed>|null */
    public function read(): ?array;

    /** @param array<array-key, mixed> $state */
    public function write(array $state): void;
}
