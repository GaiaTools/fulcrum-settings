<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\DataPortability\Pipelines\Export;

use GaiaTools\FulcrumSettings\Http\Requests\ExportRequest;
use GaiaTools\FulcrumSettings\Support\DataPortability\ExportManager;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\CsvFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\JsonFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\SqlFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\XmlFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\YamlFormatter;

class PrepareExport
{
    public function __construct(protected ExportManager $manager) {}

    public function handle(ExportRequest $request, \Closure $next)
    {
        $format = $request->input('format', 'csv');
        $formatter = match ($format) {
            'json' => new JsonFormatter,
            'xml' => new XmlFormatter,
            'yaml', 'yml' => new YamlFormatter,
            'sql' => new SqlFormatter,
            'csv' => new CsvFormatter,
            default => new CsvFormatter,
        };

        $options = [
            'decrypt' => $request->boolean('decrypt'),
            'anonymize' => $request->boolean('anonymize'),
            'gzip' => $request->boolean('gzip'),
            'connection' => $request->input('connection'),
        ];

        $filePath = $this->manager->export(
            $formatter,
            array_filter($options, fn ($value) => $value !== null)
        );

        $request->attributes->set('export_data', [
            'file_path' => $filePath,
            'format' => $format,
        ]);

        return $next($request);
    }
}
