<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Conditions;

use GaiaTools\FulcrumSettings\Contracts\ConditionTypeHandler;
use GaiaTools\FulcrumSettings\Contracts\UserAgentResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Arr;

class UserAgentConditionTypeHandler implements ConditionTypeHandler
{
    /** @var array<string, mixed>|null */
    protected ?array $uaData = null;

    protected ?string $uaScopeKey = null;

    public function __construct(protected UserAgentResolver $userAgentResolver) {}

    public function resolve(string $field, mixed $scope = null, ?Authenticatable $user = null): AttributeValue
    {
        $uaData = $this->resolveUserAgentData($scope);

        $exists = Arr::has($uaData, $field);
        $value = $exists ? Arr::get($uaData, $field) : null;

        return new AttributeValue($exists, $value);
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveUserAgentData(mixed $scope): array
    {
        $scopeKey = $this->scopeKey($scope);

        if ($this->uaData === null || $this->uaScopeKey !== $scopeKey) {
            $this->uaScopeKey = $scopeKey;
            $this->uaData = $this->userAgentResolver->resolve($scope);
        }

        return $this->uaData ?? [];
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
}
