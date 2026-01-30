<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Conditions;

use GaiaTools\FulcrumSettings\Contracts\ConditionTypeHandler;
use GaiaTools\FulcrumSettings\Contracts\GeoResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Arr;

class GeocodingConditionTypeHandler implements ConditionTypeHandler
{
    protected ?array $geoData = null;

    protected ?string $geoScopeKey = null;

    public function __construct(protected GeoResolver $geoResolver) {}

    public function resolve(string $field, mixed $scope = null, ?Authenticatable $user = null): AttributeValue
    {
        $geoData = $this->resolveGeoData($scope);

        $exists = Arr::has($geoData, $field);
        $value = $exists ? Arr::get($geoData, $field) : null;

        return new AttributeValue($exists, $value);
    }

    protected function resolveGeoData(mixed $scope): array
    {
        $scopeKey = $this->scopeKey($scope);

        if ($this->geoData === null || $this->geoScopeKey !== $scopeKey) {
            $this->geoScopeKey = $scopeKey;
            $this->geoData = $this->geoResolver->resolve($scope);
        }

        return $this->geoData ?? [];
    }

    protected function scopeKey(mixed $scope): string
    {
        if ($scope === null) {
            return 'null';
        }

        if (is_scalar($scope)) {
            return 'scalar:'.(string) $scope;
        }

        if (is_array($scope)) {
            return 'array:'.md5(serialize($scope));
        }

        if (is_object($scope)) {
            return 'object:'.spl_object_hash($scope);
        }

        return 'unknown';
    }
}
