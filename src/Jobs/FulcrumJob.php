<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Jobs;

use GaiaTools\FulcrumSettings\Jobs\Concerns\FulcrumJob as FulcrumJobTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

abstract class FulcrumJob implements ShouldQueue
{
    use Dispatchable;
    use FulcrumJobTrait;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The type of job (e.g., 'import', 'export', 'cache', 'audit').
     *
     * @var string
     */
    protected $jobType = 'generic';

    /**
     * Create a new job instance.
     *
     * @param  string|int|null  $tenantId
     */
    public function __construct($tenantId = null, ?string $batchId = null)
    {
        $this->tenantId = $tenantId;
        $this->batchId = $batchId;
    }
}
