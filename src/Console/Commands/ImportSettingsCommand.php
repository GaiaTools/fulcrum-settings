<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Console\Commands;

use GaiaTools\FulcrumSettings\Jobs\ImportSettingsJob;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\CsvFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\JsonFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\SqlFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\XmlFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\YamlFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\ImportManager;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ImportSettingsCommand extends Command
{
    protected $signature = 'fulcrum:import
                            {path : The path to the file to import}
                            {--format=csv : The file format (csv, json, xml, yaml, sql)}
                            {--mode=upsert : Import mode (insert, upsert)}
                            {--truncate : Truncate tables before importing}
                            {--conflict-handling=fail : Conflict handling (fail, skip, log)}
                            {--dry-run : Perform a dry run without saving data}
                            {--connection= : The database connection to use}
                            {--chunk-size=1000 : Chunk size for importing}
                            {--queue : Queue the import job}
                            {--queue-connection= : The queue connection to use}
                            {--queue-name= : The queue name to use}';

    protected $description = 'Import Fulcrum settings from a file';

    public function handle(ImportManager $manager): int
    {
        $path = $this->argument('path');
        $format = $this->option('format');

        // Auto-detect format from extension if not explicitly provided or if default csv is used but file is different
        if ($this->isDefaultOption('format')) {
            $ext = pathinfo($path, PATHINFO_EXTENSION);
            if ($ext === 'gz') {
                $ext = pathinfo(basename($path, '.gz'), PATHINFO_EXTENSION);
            }
            if (in_array($ext, ['json', 'xml', 'csv', 'yaml', 'yml', 'sql'])) {
                $format = $ext;
            }
        }

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
            'mode' => $this->option('mode'),
            'truncate' => $this->option('truncate'),
            'conflict_handling' => $this->option('conflict-handling'),
            'dry_run' => $this->option('dry-run'),
            'connection' => $this->option('connection'),
            'chunk_size' => (int) $this->option('chunk-size'),
        ];

        if ($this->option('queue')) {
            $batchId = Str::uuid()->toString();
            $job = new ImportSettingsJob($path, $format, array_filter($options, fn ($value) => $value !== null), null, $batchId);

            if ($this->option('queue-connection')) {
                $job->onConnection($this->option('queue-connection'));
            }

            if ($this->option('queue-name')) {
                $job->onQueue($this->option('queue-name'));
            }

            dispatch($job);

            $this->info("Import job dispatched successfully with Batch ID: {$batchId}");

            return 0;
        }

        try {
            $result = $manager->import($formatter, $path, array_filter($options, fn ($value) => $value !== null));

            if ($result) {
                $this->info('Settings imported successfully.');
            } else {
                $this->error('Import failed.');

                return 1;
            }
        } catch (\Throwable $e) {
            $this->error("Import failed: {$e->getMessage()}");

            return 1;
        }

        return 0;
    }

    protected function isDefaultOption(string $name): bool
    {
        return ! $this->hasOption($name) || $this->option($name) === $this->getDefinition()->getOption($name)->getDefault();
    }
}
