<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class SettingProperty
{
    /**
     * @param  array<int|string, mixed>  $rules
     */
    public function __construct(
        public readonly string $key,
        public readonly mixed $default = null,
        public readonly array $rules = [],
        public readonly bool $readOnly = false,
        public readonly bool $lazy = false,
        public readonly ?string $cast = null,
        public readonly bool $tenantScoped = false,
    ) {}
}
