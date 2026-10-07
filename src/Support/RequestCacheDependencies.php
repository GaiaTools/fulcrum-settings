<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support;

final class RequestCacheDependencies
{
    /** @param list<string> $inputs */
    public function __construct(public readonly array $inputs, public readonly string $revision) {}
}
