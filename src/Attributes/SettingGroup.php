<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class SettingGroup
{
    public readonly string $group;

    public function __construct(string $group, string ...$segments)
    {
        $allSegments = array_merge([$group], $segments);
        $normalized = array_filter(
            array_map(static fn (string $segment) => trim($segment, " .\t\n\r\0\x0B"), $allSegments),
            static fn (string $segment) => $segment !== ''
        );

        $this->group = implode('.', $normalized);
    }
}
