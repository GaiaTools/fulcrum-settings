<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\DataPortability\Pipelines\Import;

use GaiaTools\FulcrumSettings\Http\Requests\ImportRequest;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\CsvFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\JsonFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\SqlFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\XmlFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\YamlFormatter;

class ProcessImport
{
    public function handle(ImportRequest $request, \Closure $next): mixed
    {
        $file = $request->file('file');
        if (! $file) {
            throw new \RuntimeException('Import file is missing.');
        }

        $formatInput = $request->input('format');
        $format = is_string($formatInput) ? $formatInput : null;

        if (! $format) {
            $ext = $file->getClientOriginalExtension();
            if ($ext === 'gz') {
                $ext = pathinfo(basename($file->getClientOriginalName(), '.gz'), PATHINFO_EXTENSION);
            }
            $format = in_array($ext, ['json', 'xml', 'csv', 'yaml', 'yml', 'sql']) ? $ext : 'csv';
        }

        $formatter = match ($format) {
            'json' => new JsonFormatter,
            'xml' => new XmlFormatter,
            'yaml', 'yml' => new YamlFormatter,
            'sql' => new SqlFormatter,
            'csv' => new CsvFormatter,
            default => new CsvFormatter,
        };

        $request->attributes->set('formatter', $formatter);

        $filePath = $file->getRealPath();
        if ($filePath === false) {
            throw new \RuntimeException('Unable to resolve import file path.');
        }
        $request->attributes->set('file_path', $filePath);

        return $next($request);
    }
}
