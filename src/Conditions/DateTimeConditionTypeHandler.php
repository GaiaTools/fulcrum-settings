<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Conditions;

use Carbon\Carbon;
use GaiaTools\FulcrumSettings\Contracts\ConditionTypeHandler;
use GaiaTools\FulcrumSettings\Conditions\AttributeValue;
use Illuminate\Contracts\Auth\Authenticatable;

class DateTimeConditionTypeHandler implements ConditionTypeHandler
{
    public function resolve(string $field, mixed $scope = null, ?Authenticatable $user = null): AttributeValue
    {
        $exists = $field === 'now';
        $value = $exists ? Carbon::now() : null;

        return new AttributeValue($exists, $value);
    }
}
