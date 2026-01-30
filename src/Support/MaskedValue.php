<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support;

class MaskedValue implements \JsonSerializable, \Stringable
{
    public function __construct(
        protected string $placeholder
    ) {}

    public function __toString(): string
    {
        return $this->placeholder;
    }

    public function jsonSerialize(): mixed
    {
        return $this->placeholder;
    }
}
