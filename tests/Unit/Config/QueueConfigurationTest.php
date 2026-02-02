<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Unit\Config;

use GaiaTools\FulcrumSettings\Tests\TestCase;
use Illuminate\Support\Facades\Config;

class QueueConfigurationTest extends TestCase
{
    public function test_configuration_structure_is_correct()
    {
        $config = Config::get('fulcrum.queue');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('connection', $config);
        $this->assertArrayHasKey('queues', $config);
        $this->assertArrayHasKey('defaults', $config);

        $this->assertArrayHasKey('imports', $config['queues']);
        $this->assertArrayHasKey('exports', $config['queues']);
        $this->assertArrayHasKey('cache', $config['queues']);
        $this->assertArrayHasKey('audit', $config['queues']);

        $this->assertArrayHasKey('tries', $config['defaults']);
        $this->assertArrayHasKey('timeout', $config['defaults']);
        $this->assertArrayHasKey('backoff', $config['defaults']);
    }

    public function test_default_values_are_sensible()
    {
        $this->assertEquals('fulcrum-imports', Config::get('fulcrum.queue.queues.imports'));
        $this->assertEquals('fulcrum-exports', Config::get('fulcrum.queue.queues.exports'));
        $this->assertEquals('fulcrum-cache', Config::get('fulcrum.queue.queues.cache'));
        $this->assertEquals('fulcrum-audit', Config::get('fulcrum.queue.queues.audit'));

        $this->assertEquals(3, Config::get('fulcrum.queue.defaults.tries'));
        $this->assertEquals(60, Config::get('fulcrum.queue.defaults.timeout'));
        $this->assertEquals(60, Config::get('fulcrum.queue.defaults.backoff'));
    }
}
