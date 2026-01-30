<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Unit\Support;

use GaiaTools\FulcrumSettings\Support\QueueHelper;
use GaiaTools\FulcrumSettings\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;

class QueueHelperTest extends TestCase
{
    public function test_it_returns_queue_connection()
    {
        Config::set('fulcrum.queue.connection', 'redis');
        $this->assertEquals('redis', QueueHelper::getConnection());

        Config::set('fulcrum.queue.connection', null);
        $this->assertNull(QueueHelper::getConnection());
    }

    #[DataProvider('queueTypeProvider')]
    public function test_it_returns_named_queue_by_type(string $type, string $configKey, string $default)
    {
        // Test default
        $this->assertEquals($default, QueueHelper::getQueue($type));

        // Test override
        $customQueue = "custom-{$type}-queue";
        Config::set("fulcrum.queue.queues.{$type}", $customQueue);
        $this->assertEquals($customQueue, QueueHelper::getQueue($type));
    }

    public static function queueTypeProvider(): array
    {
        return [
            ['imports', 'fulcrum.queue.queues.imports', 'fulcrum-imports'],
            ['exports', 'fulcrum.queue.queues.exports', 'fulcrum-exports'],
            ['cache', 'fulcrum.queue.queues.cache', 'fulcrum-cache'],
            ['audit', 'fulcrum.queue.queues.audit', 'fulcrum-audit'],
        ];
    }

    public function test_it_throws_exception_for_invalid_queue_type()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid queue type: non-existent');

        QueueHelper::getQueue('non-existent');
    }

    public function test_it_returns_default_job_settings()
    {
        Config::set('fulcrum.queue.defaults', [
            'tries' => 5,
            'timeout' => 120,
            'backoff' => 30,
        ]);

        $settings = QueueHelper::getDefaultSettings();

        $this->assertEquals(5, $settings['tries']);
        $this->assertEquals(120, $settings['timeout']);
        $this->assertEquals(30, $settings['backoff']);
    }

    public function test_it_returns_hardcoded_defaults_if_config_missing()
    {
        Config::offsetUnset('fulcrum.queue.defaults');

        $settings = QueueHelper::getDefaultSettings();

        $this->assertEquals(3, $settings['tries']);
        $this->assertEquals(60, $settings['timeout']);
        $this->assertEquals(60, $settings['backoff']);
    }
}
