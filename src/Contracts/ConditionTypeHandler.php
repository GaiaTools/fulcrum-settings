<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Contracts;

use GaiaTools\FulcrumSettings\Conditions\AttributeValue;
use Illuminate\Contracts\Auth\Authenticatable;

interface ConditionTypeHandler
{
    /**
     * Resolve a field value for a condition type.
     *
     * Returns whether a value exists and the resolved value.
     */
    public function resolve(string $field, mixed $scope = null, ?Authenticatable $user = null): AttributeValue;
}
