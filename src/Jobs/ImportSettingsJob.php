<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Jobs;

use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\CsvFormatter;
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
     * @var array
     */
    protected $options;

    /**
     * Create a new job instance.
     *
     * @param  string|int|null  $tenantId
     */
    public function __construct(string $path, string $format, array $options = [], $tenantId = null, ?string $batchId = null)
    {
        parent::__construct($tenantId, $batchId);
        $this->path = $path;
        $this->format = $format;
        $this->options = $options;
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
     *
     * @return mixed
     */
    protected function getFormatter()
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
}
