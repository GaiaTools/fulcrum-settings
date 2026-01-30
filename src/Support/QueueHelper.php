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
        return config('fulcrum.queue.connection');
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

        if ($queue === null) {
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
        return config('fulcrum.queue.defaults') ?? [
            'tries' => 3,
            'timeout' => 60,
            'backoff' => 60,
        ];
    }
}
