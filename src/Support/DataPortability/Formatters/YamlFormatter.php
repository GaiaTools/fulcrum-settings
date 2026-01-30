<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\DataPortability\Formatters;

use Symfony\Component\Yaml\Yaml;

class YamlFormatter implements Formatter
{
    public function format(array $data): string
    {
        return Yaml::dump($data, 10, 2);
    }

    public function parse(string $content): array
    {
        if (trim($content) === '') {
            return [];
        }

        return Yaml::parse($content) ?: [];
    }
}
