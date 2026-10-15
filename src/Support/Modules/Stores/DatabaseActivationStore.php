<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\Modules\Stores;

use GaiaTools\FulcrumSettings\Contracts\Modules\ActivationStore;
use GaiaTools\FulcrumSettings\Exceptions\ModuleActivationException;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;

class DatabaseActivationStore implements ActivationStore
{
    public function __construct(private readonly Repository $config, private readonly DatabaseManager $database) {}

    public function read(): ?array
    {
        $json = $this->query()->where('key', $this->config->string('fulcrum.modules.storage.key'))->value('payload');
        if ($json === null) {
            return null;
        }
        if (! is_string($json)) {
            throw new ModuleActivationException('Invalid database module activation document.');
        }
        $state = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($state)) {
            throw new ModuleActivationException('Invalid database module activation document.');
        }

        return $state;
    }

    public function write(array $state): void
    {
        $this->query()->upsert([
            'key' => $this->config->string('fulcrum.modules.storage.key'),
            'payload' => json_encode($state, JSON_THROW_ON_ERROR),
        ], ['key'], ['payload']);
    }

    private function query(): Builder
    {
        return $this->database->connection($this->connection())
            ->table($this->config->string('fulcrum.modules.storage.table'));
    }

    private function connection(): ?string
    {
        $name = $this->config->get('fulcrum.modules.storage.connection');
        if ($name !== null && ! is_string($name)) {
            throw new ModuleActivationException('Invalid module activation storage configuration.');
        }

        return $name;
    }
}
