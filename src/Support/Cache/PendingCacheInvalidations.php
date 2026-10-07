<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\Cache;

use Illuminate\Database\Connection;
use WeakMap;

class PendingCacheInvalidations
{
    /** @var WeakMap<Connection, array<string, non-empty-array<int, bool>>> */
    private WeakMap $pending;

    public function __construct()
    {
        $this->pending = new WeakMap;
    }

    public function register(Connection $connection, string $namespace): bool
    {
        $entries = $this->pending[$connection] ?? [];
        $first = empty($entries[$namespace]);
        $entries[$namespace][$connection->transactionLevel()] = true;
        $this->pending[$connection] = $entries;

        return $first;
    }

    public function hasWrites(Connection $connection): bool
    {
        return ! empty($this->pending[$connection]);
    }

    public function forget(Connection $connection, string $namespace): void
    {
        $entries = $this->pending[$connection] ?? [];
        unset($entries[$namespace]);
        $this->pending[$connection] = $entries;
    }

    public function rolledBack(Connection $connection): void
    {
        $entries = $this->pending[$connection] ?? [];
        foreach ($entries as $namespace => $levels) {
            $remaining = array_filter($levels, fn ($level) => $level <= $connection->transactionLevel(), ARRAY_FILTER_USE_KEY);
            if ($remaining === []) {
                unset($entries[$namespace]);
            } else {
                $entries[$namespace] = $remaining;
            }
        }
        $this->pending[$connection] = $entries;
    }

    public function committed(Connection $connection): void
    {
        $entries = $this->pending[$connection] ?? [];
        foreach ($entries as $namespace => $levels) {
            $entries[$namespace] = [min(min(array_keys($levels)), $connection->transactionLevel()) => true];
        }
        $this->pending[$connection] = $connection->transactionLevel() === 0 ? [] : $entries;
    }
}
