<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Conditions;

use GaiaTools\FulcrumSettings\Contracts\ConditionTypeHandler;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use GaiaTools\FulcrumSettings\Conditions\AttributeValue;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Arr;

class UserConditionTypeHandler implements ConditionTypeHandler
{
    public function resolve(string $field, mixed $scope = null, ?Authenticatable $user = null): AttributeValue
    {
        $result = $this->resolveFromContext($field);
        $scope = $scope ?? $user;
        if (! $result->exists && $scope !== null) {
            $result = $this->resolveFromScope($field, $scope);
        }

        return $result;
    }

    /**
     * @return AttributeValue
     */
    protected function resolveFromContext(string $field): AttributeValue
    {
        $result = new AttributeValue(false, null);
        $contextAttributes = FulcrumContext::all();
        return array_key_exists($field, $contextAttributes)
            ? new AttributeValue(true, $contextAttributes[$field])
            : $result;
    }

    /**
     * @return AttributeValue
     */
    protected function resolveFromScope(string $field, mixed $scope): AttributeValue
    {
        $result = new AttributeValue(false, null);
        if (is_array($scope) && Arr::has($scope, $field)) {
            $result = new AttributeValue(true, Arr::get($scope, $field));
        }

        if (! $result->exists && is_scalar($scope) && $field === 'scope') {
            $result = new AttributeValue(true, $scope);
        }

        if (! $result->exists && is_object($scope)) {
            $authResult = $this->resolveFromAuthenticatable($field, $scope);
            $result = $authResult->exists ? $authResult : $result;
        }

        if (! $result->exists && is_object($scope)) {
            $sentinel = new MissingAttribute();
            $value = data_get($scope, $field, $sentinel);
            $found = $value !== $sentinel;
            $result = $found ? new AttributeValue(true, $value) : $result;
        }

        return $result;
    }

    /**
     * @return AttributeValue
     */
    protected function resolveFromAuthenticatable(string $field, object $scope): AttributeValue
    {
        $result = new AttributeValue(false, null);

        if ($scope instanceof Authenticatable && $field === 'id') {
            $result = new AttributeValue(true, $scope->getAuthIdentifier());
        }

        if ($scope instanceof Authenticatable && ! $result->exists && $field === 'email') {
            $result = new AttributeValue(true, $scope->getEmailForPasswordReset());
        }

        return $result;
    }
}
