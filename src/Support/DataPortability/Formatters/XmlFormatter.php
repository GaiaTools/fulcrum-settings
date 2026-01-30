<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\DataPortability\Formatters;

class XmlFormatter implements Formatter
{
    public function format(array $data): string
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><settings/>');
        $this->arrayToXml($data, $xml);

        return $xml->asXML() ?: '';
    }

    public function parse(string $content): array
    {
        $xml = @simplexml_load_string($content);
        if ($xml === false) {
            return [];
        }

        $encoded = json_encode($xml);
        if ($encoded === false) {
            return [];
        }

        $decoded = json_decode($encoded, true);

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

    /**
     * @param  array<int|string, mixed>  $data
     */
    protected function arrayToXml(array $data, \SimpleXMLElement $xml): void
    {
        foreach ($data as $key => $value) {
            if (is_numeric($key)) {
                $key = 'setting';
            }
            if (is_array($value)) {
                $subnode = $xml->addChild((string) $key);
                $this->arrayToXml($value, $subnode);
            } else {
                $xml->addChild((string) $key, htmlspecialchars($this->stringifyValue($value)));
            }
        }
    }

    protected function stringifyValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        $encoded = json_encode($value);

        return $encoded === false ? '' : $encoded;
    }
}
