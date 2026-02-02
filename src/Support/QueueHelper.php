<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support;

use InvalidArgumentException;

class QueueHelper
{
    /**
     * Get the queue connection.
     */
    public static function getConnection(): ?string
    {
        $connection = config('fulcrum.queue.connection');

        return is_string($connection) ? $connection : null;
    }

    /**
     * Get a named queue by type.
     *
     *
     * @throws InvalidArgumentException
     */
    public static function getQueue(string $type): string
    {
        $queue = config("fulcrum.queue.queues.{$type}");

        if (! is_string($queue)) {
            throw new InvalidArgumentException("Invalid queue type: {$type}");
        }

        return $queue;
    }

    /**
     * Get default job settings.
     *
     * @return array{tries: int, timeout: int, backoff: int}
     */
    public static function getDefaultSettings(): array
    {
        $defaults = config('fulcrum.queue.defaults');
        $fallback = [
            'tries' => 3,
            'timeout' => 60,
            'backoff' => 60,
        ];

        if (! is_array($defaults)) {
            return $fallback;
        }

        $normalized = $fallback;
        foreach ($fallback as $key => $value) {
            if (array_key_exists($key, $defaults) && is_numeric($defaults[$key])) {
                $normalized[$key] = (int) $defaults[$key];
            }
        }

        return $normalized;
    }
}
