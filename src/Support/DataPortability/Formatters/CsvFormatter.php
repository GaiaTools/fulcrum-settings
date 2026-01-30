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
        if ($output === false) {
            return '';
        }

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

        return $content === false ? '' : $content;
    }

    public function parse(string $content): array
    {
        if (trim($content) === '') {
            return [];
        }

        $rows = array_values(array_filter(str_getcsv($content, "\n"), fn ($row) => ! empty(trim((string) $row))));

        $headerRow = array_shift($rows);
        if (! is_string($headerRow)) {
            return [];
        }
        $headers = array_map(static fn ($header) => (string) $header, str_getcsv($headerRow));
        $data = [];

        foreach ($rows as $row) {
            if (! is_string($row)) {
                continue;
            }
            $values = str_getcsv($row);
            if (count($headers) !== count($values)) {
                continue;
            }
            $data[] = $this->unflatten(array_combine($headers, $values));
        }

        return $data;
    }

    /**
     * @param  array<int|string, mixed>  $array
     * @return array<string, bool|float|int|string|null>
     */
    protected function flatten(array $array, string $prefix = ''): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $keyString = (string) $key;
            $newKey = $prefix === '' ? $keyString : $prefix.'.'.$keyString;
            if (is_array($value)) {
                $result = array_merge($result, $this->flatten($value, $newKey));
            } else {
                $result[$newKey] = $this->normalizeValue($value);
            }
        }

        return $result;
    }

    /**
     * @param  array<int|string, mixed>  $array
     * @return array<string, mixed>
     */
    protected function unflatten(array $array): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $keys = explode('.', (string) $key);
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

    protected function normalizeValue(mixed $value): bool|float|int|string|null
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        $encoded = json_encode($value);

        return $encoded === false ? '' : $encoded;
    }
}
