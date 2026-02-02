<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Jobs;

use GaiaTools\FulcrumSettings\Support\DataPortability\ExportManager;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\CsvFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\Formatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\JsonFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\SqlFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\XmlFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\YamlFormatter;
use GaiaTools\FulcrumSettings\Support\QueueHelper;

class ExportSettingsJob extends FulcrumJob
{
    /**
     * The type of job.
     *
     * @var string
     */
    protected $jobType = 'export';

    /**
     * The format of the export.
     *
     * @var string
     */
    protected $format;

    /**
     * The export options.
     *
     * @var array{
     *     connection?: string,
     *     decrypt?: bool,
     *     anonymize?: bool,
     *     gzip?: bool,
     *     dry_run?: bool,
     *     directory?: string,
     *     filename?: string
     * }
     */
    protected $options;

    /**
     * Create a new job instance.
     *
     * @param  array<string, mixed>  $options
     * @param  string|int|null  $tenantId
     */
    public function __construct(string $format, array $options = [], $tenantId = null, ?string $batchId = null)
    {
        parent::__construct($tenantId, $batchId);
        $this->format = $format;
        $this->options = $this->normalizeOptions($options);
        $this->queue = QueueHelper::getQueue('exports');
        $this->connection = QueueHelper::getConnection();
    }

    /**
     * Execute the job.
     */
    public function handle(ExportManager $manager): void
    {
        $formatter = $this->getFormatter();

        if (! $formatter) {
            throw new \RuntimeException("Unsupported format: {$this->format}");
        }

        $manager->export($formatter, $this->options);
    }

    /**
     * Get the job-specific tags.
     */
    protected function jobSpecificTags(): array
    {
        return [
            'format:'.$this->format,
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
     *     decrypt?: bool,
     *     anonymize?: bool,
     *     gzip?: bool,
     *     dry_run?: bool,
     *     directory?: string,
     *     filename?: string
     * }
     */
    protected function normalizeOptions(array $options): array
    {
        $normalized = [
            'connection' => isset($options['connection']) && is_string($options['connection']) ? $options['connection'] : null,
            'decrypt' => isset($options['decrypt']) ? (bool) $options['decrypt'] : null,
            'anonymize' => isset($options['anonymize']) ? (bool) $options['anonymize'] : null,
            'gzip' => isset($options['gzip']) ? (bool) $options['gzip'] : null,
            'dry_run' => isset($options['dry_run']) ? (bool) $options['dry_run'] : null,
            'directory' => isset($options['directory']) && is_string($options['directory']) ? $options['directory'] : null,
            'filename' => isset($options['filename']) && is_string($options['filename']) ? $options['filename'] : null,
        ];

        return array_filter($normalized, static fn ($value) => $value !== null);
    }
}
