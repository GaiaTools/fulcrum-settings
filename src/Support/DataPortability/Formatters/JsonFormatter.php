<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\DataPortability\Formatters;

class JsonFormatter implements Formatter
{
    public function format(array $data): string
    {
        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? '[]' : $encoded;
    }

    public function parse(string $content): array
    {
        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            return [];
        }

        $normalized = [];
        foreach ($decoded as $row) {
            if (is_array($row)) {
                $normalized[] = $row;
            }
        }

        return $normalized;
    }
}
