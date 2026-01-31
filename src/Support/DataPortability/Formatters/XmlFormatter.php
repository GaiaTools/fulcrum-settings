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

        $decoded = $this->decodeXmlToArray($xml);
        if ($decoded === null) {
            return [];
        }

        return $this->normalizeDecodedXml($decoded);
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
        return match (true) {
            is_string($value) => $value,
            is_scalar($value) => (string) $value,
            is_object($value) && method_exists($value, '__toString') => (string) $value,
            default => $this->encodeJsonValue($value),
        };
    }

    /**
     * @return array<int, mixed>|null
     */
    protected function decodeXmlToArray(\SimpleXMLElement $xml): ?array
    {
        $decoded = null;
        $encoded = json_encode($xml);

        if ($encoded !== false) {
            $value = json_decode($encoded, true);
            $decoded = is_array($value) ? $value : null;
        }

        return $decoded;
    }

    /**
     * @param  array<int, mixed>  $decoded
     * @return array<int, mixed>
     */
    protected function normalizeDecodedXml(array $decoded): array
    {
        $normalized = [];

        foreach ($decoded as $row) {
            if (is_array($row)) {
                $normalized[] = $row;
            }
        }

        return $normalized;
    }

    protected function encodeJsonValue(mixed $value): string
    {
        $encoded = json_encode($value);

        return $encoded === false ? '' : $encoded;
    }
}
