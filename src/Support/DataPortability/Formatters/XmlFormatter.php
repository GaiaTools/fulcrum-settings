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

        return json_decode(json_encode($xml) ?: '', true) ?: [];
    }

    protected function arrayToXml(array $data, \SimpleXMLElement $xml): void
    {
        foreach ($data as $key => $value) {
            if (is_numeric($key)) {
                $key = 'setting';
            }
            if (is_array($value)) {
                $subnode = $xml->addChild($key);
                $this->arrayToXml($value, $subnode);
            } else {
                $xml->addChild($key, htmlspecialchars((string) $value));
            }
        }
    }
}
