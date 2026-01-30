<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Jobs\Concerns;

use GaiaTools\FulcrumSettings\Support\QueueHelper;

trait FulcrumJob
{
    /**
     * The tenant ID.
     *
     * @var string|int|null
     */
    protected $tenantId;

    /**
     * The batch ID.
     *
     * @var string|null
     */
    protected $batchId;

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        $tags = ['fulcrum'];

        $tags[] = 'type:'.$this->jobType;

        if ($this->tenantId) {
            $tags[] = 'tenant:'.$this->tenantId;
        }

        if ($this->batchId) {
            $tags[] = 'batch:'.$this->batchId;
        }

        return array_merge($tags, $this->jobSpecificTags());
    }

    /**
     * Get the job-specific tags.
     *
     * @return array<int, string>
     */
    protected function jobSpecificTags(): array
    {
        return [];
    }

    /**
     * Get the number of times the job may be attempted.
     */
    public function tries(): int
    {
        return $this->tries ?? QueueHelper::getDefaultSettings()['tries'];
    }

    /**
     * Get the number of seconds the job can run before timing out.
     */
    public function timeout(): int
    {
        return $this->timeout ?? QueueHelper::getDefaultSettings()['timeout'];
    }

    /**
     * Get the number of seconds to wait before retrying the job.
     */
    public function backoff(): int
    {
        return $this->backoff ?? QueueHelper::getDefaultSettings()['backoff'];
    }

    /**
     * Set the tenant ID for the job.
     *
     * @param  string|int|null  $tenantId
     * @return $this
     */
    public function forTenant($tenantId)
    {
        $this->tenantId = $tenantId;

        return $this;
    }

    /**
     * Set the batch ID for the job.
     *
     * @return $this
     */
    public function withBatchId(?string $batchId)
    {
        $this->batchId = $batchId;

        return $this;
    }
}
