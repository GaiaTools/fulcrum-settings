<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\DataPortability\Formatters;

interface Formatter
{
    /**
     * @param  array<int, array<string, mixed>>  $data
     */
    public function format(array $data): string;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $content): array;
}
