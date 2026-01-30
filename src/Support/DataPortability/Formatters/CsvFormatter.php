<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\DataPortability\Formatters;

class CsvFormatter implements Formatter
{
    public function format(array $data): string
    {
        if (empty($data)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');

        // Use keys of the first element as headers
        // Since the data might be nested, we should probably flatten it or decide how to handle it.
        // For now, let's assume we flatten it or just handle top-level.
        $headers = array_keys($this->flatten($data[0]));
        fputcsv($output, $headers);

        foreach ($data as $row) {
            fputcsv($output, $this->flatten($row));
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return $content;
    }

    public function parse(string $content): array
    {
        if (trim($content) === '') {
            return [];
        }

        $rows = array_values(array_filter(str_getcsv($content, "\n"), fn ($row) => ! empty(trim((string) $row))));

        $headerRow = array_shift($rows);
        $headers = str_getcsv((string) $headerRow);
        $data = [];

        foreach ($rows as $row) {
            $values = str_getcsv($row);
            if (count($headers) !== count($values)) {
                continue;
            }
            $data[] = $this->unflatten(array_combine($headers, $values));
        }

        return $data;
    }

    protected function flatten(array $array, string $prefix = ''): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $newKey = $prefix === '' ? $key : $prefix.'.'.$key;
            if (is_array($value)) {
                $result = array_merge($result, $this->flatten($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }

    protected function unflatten(array $array): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $keys = explode('.', $key);
            $current = &$result;
            foreach ($keys as $i => $k) {
                if ($i === count($keys) - 1) {
                    $current[$k] = $value;
                } else {
                    if (! isset($current[$k]) || ! is_array($current[$k])) {
                        $current[$k] = [];
                    }
                    $current = &$current[$k];
                }
            }
        }

        return $result;
    }
}
