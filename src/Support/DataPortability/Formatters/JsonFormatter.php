<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\DataPortability\Formatters;

class JsonFormatter implements Formatter
{
    public function format(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function parse(string $content): array
    {
        return json_decode($content, true) ?: [];
    }
}
