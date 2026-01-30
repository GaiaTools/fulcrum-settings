<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Conditions;

final class AttributeValue
{
    public function __construct(
        public readonly bool $exists,
        public readonly mixed $value
    ) {}
}
