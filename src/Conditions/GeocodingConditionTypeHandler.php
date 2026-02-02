<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Conditions;

use GaiaTools\FulcrumSettings\Contracts\ConditionTypeHandler;
use GaiaTools\FulcrumSettings\Contracts\GeoResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class GeocodingConditionTypeHandler implements ConditionTypeHandler
{
    /** @var array<string, mixed>|null */
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

    /**
     * @return array<string, mixed>
     */
    protected function resolveGeoData(mixed $scope): array
    {
        $scopeKey = $this->scopeKey($scope);

        if ($this->geoData === null || $this->geoScopeKey !== $scopeKey) {
            $this->geoScopeKey = $scopeKey;
            $input = $this->resolveGeoInput($scope);
            $this->geoData = $this->geoResolver->resolve($input);
        }

        return $this->geoData ?? [];
    }

    protected function scopeKey(mixed $scope): string
    {
        return match (true) {
            $scope === null => 'null',
            is_scalar($scope) => 'scalar:'.(string) $scope,
            is_array($scope) => 'array:'.hash('sha256', serialize($scope)),
            is_object($scope) => 'object:'.spl_object_hash($scope),
            default => 'unknown',
        };
    }

    /**
     * @return Request|string|array<mixed>|null
     */
    protected function resolveGeoInput(mixed $scope): Request|string|array|null
    {
        $input = null;

        if (is_string($scope) || is_array($scope) || $scope instanceof Request) {
            $input = $scope;
        }

        return $input;
    }
}
