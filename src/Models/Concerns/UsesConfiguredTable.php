<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Models\Concerns;

trait UsesConfiguredTable
{
    public function getTable(): string
    {
        $table = parent::getTable();

        return config()->string('fulcrum.table_names.'.$table, $table);
    }
}
