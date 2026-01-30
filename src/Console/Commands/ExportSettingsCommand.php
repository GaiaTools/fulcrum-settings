<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Console\Commands;

use GaiaTools\FulcrumSettings\Jobs\ExportSettingsJob;
use GaiaTools\FulcrumSettings\Support\DataPortability\ExportManager;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\CsvFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\JsonFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\SqlFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\XmlFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\YamlFormatter;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ExportSettingsCommand extends Command
{
    protected $signature = 'fulcrum:export
                            {--format=csv : The file format (csv, json, xml, yaml, sql)}
                            {--directory=. : The directory to export to}
                            {--filename= : The name of the export file}
                            {--decrypt : Decrypt encrypted values}
                            {--gzip : Compress the export file using Gzip}
                            {--dry-run : Perform a dry run without saving the file}
                            {--connection= : The database connection to use}
                            {--anonymize : Anonymize sensitive data}
                            {--queue : Queue the export job}
                            {--queue-connection= : The queue connection to use}
                            {--queue-name= : The queue name to use}';

    protected $description = 'Export Fulcrum settings to a file';

    public function handle(ExportManager $manager): int
    {
        $format = $this->option('format');
        $formatter = match ($format) {
            'json' => new JsonFormatter,
            'xml' => new XmlFormatter,
            'yaml', 'yml' => new YamlFormatter,
            'sql' => new SqlFormatter,
            'csv' => new CsvFormatter,
            default => null,
        };

        if (! $formatter) {
            $this->error("Unsupported format: {$format}");

            return 1;
        }

        $options = [
            'decrypt' => $this->option('decrypt'),
            'anonymize' => $this->option('anonymize'),
            'gzip' => $this->option('gzip'),
            'dry_run' => $this->option('dry-run'),
            'directory' => $this->option('directory'),
            'filename' => $this->option('filename'),
            'connection' => $this->option('connection'),
        ];

        if ($this->option('queue')) {
            $batchId = Str::uuid()->toString();
            $job = new ExportSettingsJob($format, array_filter($options, fn ($value) => $value !== null), null, $batchId);

            if ($this->option('queue-connection')) {
                $job->onConnection($this->option('queue-connection'));
            }

            if ($this->option('queue-name')) {
                $job->onQueue($this->option('queue-name'));
            }

            dispatch($job);

            $this->info("Export job dispatched successfully with Batch ID: {$batchId}");

            return 0;
        }

        try {
            $result = $manager->export($formatter, array_filter($options, fn ($value) => $value !== null));

            if ($result === true) {
                $this->info('Dry run completed successfully.');
            } elseif (is_string($result)) {
                $this->info("Settings exported successfully to: {$result}");
            } else {
                $this->error('Export failed.');

                return 1;
            }
        } catch (\Throwable $e) {
            $this->error("Export failed: {$e->getMessage()}");

            return 1;
        }

        return 0;
    }
}
