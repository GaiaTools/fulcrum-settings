<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Integration;

use GaiaTools\FulcrumSettings\Jobs\ImportSettingsJob;
use GaiaTools\FulcrumSettings\Tests\TestCase;
use Illuminate\Support\Facades\Config;

class HorizonIntegrationTest extends TestCase
{
    public function test_jobs_emit_correct_horizon_tags()
    {
        $job = new ImportSettingsJob('test.csv', 'csv', [], 'tenant-1', 'batch-123');

        $tags = $job->tags();

        $this->assertContains('fulcrum', $tags);
        $this->assertContains('type:import', $tags);
        $this->assertContains('tenant:tenant-1', $tags);
        $this->assertContains('batch:batch-123', $tags);
        $this->assertContains('format:csv', $tags);
    }

    public function test_jobs_respect_queue_configuration()
    {
        Config::set('fulcrum.queue.queues.imports', 'custom-imports');
        Config::set('fulcrum.queue.connection', 'redis-custom');

        $job = new ImportSettingsJob('test.csv', 'csv');

        $this->assertEquals('custom-imports', $job->queue);
        $this->assertEquals('redis-custom', $job->connection);
    }

    public function test_jobs_respect_default_settings()
    {
        Config::set('fulcrum.queue.defaults.tries', 5);
        Config::set('fulcrum.queue.defaults.timeout', 120);
        Config::set('fulcrum.queue.defaults.backoff', 30);

        $job = new ImportSettingsJob('test.csv', 'csv');

        $this->assertEquals(5, $job->tries());
        $this->assertEquals(120, $job->timeout());
        $this->assertEquals(30, $job->backoff());
    }
}
