<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Unit\Jobs\Concerns;

use GaiaTools\FulcrumSettings\Jobs\Concerns\FulcrumJob;
use GaiaTools\FulcrumSettings\Tests\TestCase;
use Illuminate\Support\Facades\Config;

class FulcrumJobTraitTest extends TestCase
{
    private $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->job = new class
        {
            use FulcrumJob;

            protected $jobType = 'test';

            public $tries;

            public $timeout;

            public $backoff;

            protected function jobSpecificTags(): array
            {
                return ['custom:tag'];
            }
        };
    }

    public function test_it_can_set_tenant_id_using_fluent_interface()
    {
        $result = $this->job->forTenant('tenant-123');

        $this->assertSame($this->job, $result);
        $this->assertContains('tenant:tenant-123', $this->job->tags());
    }

    public function test_it_can_set_batch_id_using_fluent_interface()
    {
        $result = $this->job->withBatchId('batch-456');

        $this->assertSame($this->job, $result);
        $this->assertContains('batch:batch-456', $this->job->tags());
    }

    public function test_it_generates_correct_tags()
    {
        $this->job->forTenant(1)->withBatchId('B-1');

        $tags = $this->job->tags();

        $this->assertContains('fulcrum', $tags);
        $this->assertContains('type:test', $tags);
        $this->assertContains('tenant:1', $tags);
        $this->assertContains('batch:B-1', $tags);
        $this->assertContains('custom:tag', $tags);
    }

    public function test_it_returns_default_queue_settings()
    {
        Config::set('fulcrum.queue.defaults', [
            'tries' => 5,
            'timeout' => 120,
            'backoff' => 30,
        ]);

        $this->assertEquals(5, $this->job->tries());
        $this->assertEquals(120, $this->job->timeout());
        $this->assertEquals(30, $this->job->backoff());
    }

    public function test_it_respects_overridden_queue_settings()
    {
        $this->job->tries = 10;
        $this->job->timeout = 300;
        $this->job->backoff = 600;

        $this->assertEquals(10, $this->job->tries());
        $this->assertEquals(300, $this->job->timeout());
        $this->assertEquals(600, $this->job->backoff());
    }

    public function test_default_job_specific_tags_returns_empty_array()
    {
        $basicJob = new class
        {
            use FulcrumJob;

            // We need to make this public to test it easily if it was protected,
            // but since it is protected in the trait, we can call it through a wrapper
            // or just test it via tags() which calls it.

            public function callJobSpecificTags(): array
            {
                return $this->jobSpecificTags();
            }
        };

        $this->assertEquals([], $basicJob->callJobSpecificTags());
    }
}
