<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Jobs;

use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\CsvFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\Formatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\JsonFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\SqlFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\XmlFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\YamlFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\ImportManager;
use GaiaTools\FulcrumSettings\Support\QueueHelper;

class ImportSettingsJob extends FulcrumJob
{
    /**
     * The type of job.
     *
     * @var string
     */
    protected $jobType = 'import';

    /**
     * The path to the file to import.
     *
     * @var string
     */
    protected $path;

    /**
     * The format of the file.
     *
     * @var string
     */
    protected $format;

    /**
     * The import options.
     *
     * @var array{
     *     connection?: string,
     *     mode?: 'insert'|'upsert',
     *     truncate?: bool,
     *     conflict_handling?: 'fail'|'log'|'skip',
     *     dry_run?: bool,
     *     chunk_size?: int
     * }
     */
    protected $options;

    /**
     * Create a new job instance.
     *
     * @param  array<string, mixed>  $options
     * @param  string|int|null  $tenantId
     */
    public function __construct(string $path, string $format, array $options = [], $tenantId = null, ?string $batchId = null)
    {
        parent::__construct($tenantId, $batchId);
        $this->path = $path;
        $this->format = $format;
        $this->options = $this->normalizeOptions($options);
        $this->queue = QueueHelper::getQueue('imports');
        $this->connection = QueueHelper::getConnection();
    }

    /**
     * Execute the job.
     */
    public function handle(ImportManager $manager): void
    {
        $formatter = $this->getFormatter();

        if (! $formatter) {
            throw new \RuntimeException("Unsupported format: {$this->format}");
        }

        // Check idempotency if batchId is provided
        // In a real scenario, we might check a database table for batch status
        // For now, we proceed with the import.

        $manager->import($formatter, $this->path, $this->options);
    }

    /**
     * Get the job-specific tags.
     */
    protected function jobSpecificTags(): array
    {
        return [
            'format:'.$this->format,
            'path:'.$this->path,
        ];
    }

    /**
     * Get the formatter instance based on the format.
     */
    protected function getFormatter(): ?Formatter
    {
        return match ($this->format) {
            'json' => new JsonFormatter,
            'xml' => new XmlFormatter,
            'yaml', 'yml' => new YamlFormatter,
            'sql' => new SqlFormatter,
            'csv' => new CsvFormatter,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{
     *     connection?: string,
     *     mode?: 'insert'|'upsert',
     *     truncate?: bool,
     *     conflict_handling?: 'fail'|'log'|'skip',
     *     dry_run?: bool,
     *     chunk_size?: int
     * }
     */
    protected function normalizeOptions(array $options): array
    {
        $mode = isset($options['mode']) && is_string($options['mode']) ? $options['mode'] : null;
        $conflictHandling = isset($options['conflict_handling']) && is_string($options['conflict_handling'])
            ? $options['conflict_handling']
            : null;

        $normalized = [
            'connection' => isset($options['connection']) && is_string($options['connection']) ? $options['connection'] : null,
            'mode' => in_array($mode, ['insert', 'upsert'], true) ? $mode : null,
            'truncate' => isset($options['truncate']) ? (bool) $options['truncate'] : null,
            'conflict_handling' => in_array($conflictHandling, ['fail', 'log', 'skip'], true) ? $conflictHandling : null,
            'dry_run' => isset($options['dry_run']) ? (bool) $options['dry_run'] : null,
            'chunk_size' => isset($options['chunk_size']) && is_numeric($options['chunk_size'])
                ? max(1, (int) $options['chunk_size'])
                : null,
        ];

        return array_filter($normalized, static fn ($value) => $value !== null);
    }
}
