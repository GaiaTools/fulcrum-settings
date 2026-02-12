<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Drivers;

use GaiaTools\FulcrumSettings\Contracts\SettingResolver;
use GaiaTools\FulcrumSettings\Exceptions\PennantException;
use GaiaTools\FulcrumSettings\Models\Setting;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Pennant\Contracts\DefinesFeaturesExternally;
use Laravel\Pennant\Contracts\Driver;
use Laravel\Pennant\Feature;

class PennantDriver implements DefinesFeaturesExternally, Driver
{
    public function __construct(
        protected SettingResolver $resolver,
    ) {}

    /**
     * Define a feature flag.
     * Not used by Fulcrum since features are defined in the database.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function define(string $feature, callable $resolver, array $attributes = []): void
    {
        // Fulcrum features are defined in the database, not in code
        // This method is called by Pennant's Feature::define() but we don't need it
    }

    /**
     * Retrieve the names of all defined features.
     *
     * @return array<int, string>
     */
    public function defined(): array
    {
        return Setting::pluck('key')
            ->map(fn ($key): string => $this->stringifyKey($key))
            ->values()
            ->all();
    }

    /**
     * Get multiple feature flag values.
     *
     * @param  array<int, string>|array<string, array<int, mixed>>  $features
     * @return array<string, array<int, mixed>|mixed>
     */
    public function getAll(array $features): array
    {
        $results = [];

        foreach ($features as $feature => $scopes) {
            if (is_int($feature) && is_string($scopes)) {
                $featureName = $scopes;
                $scopesList = [null];
                $isConvenienceCall = true;
            } else {
                $featureName = (string) $feature;
                $scopesList = $scopes;
                $isConvenienceCall = false;
            }

            if (! is_array($scopesList)) {
                continue;
            }

            if ($isConvenienceCall) {
                $results[$featureName] = $this->get($featureName, $scopesList[0] ?? null);
                continue;
            }

            $values = [];

            foreach ($scopesList as $scope) {
                $values[] = $this->get($featureName, $scope);
            }

            $results[$featureName] = $values;
        }

        return $results;
    }

    public function get(string $feature, mixed $scope): mixed
    {
        $context = $this->buildContext($scope);
        $user = $this->extractUser($scope);

        // When using Pennant features, we want to respect the current tenant scope.
        // If a tenant is set, it will only resolve settings for that tenant or shared ones.
        $resolver = $user ? $this->resolver->forUser($user) : $this->resolver;

        return $resolver->resolve($feature, $context);
    }

    /**
     * Set a feature flag's value.
     * Fulcrum is database-managed, so this operation is not supported.
     */
    public function set(string $feature, mixed $scope, mixed $value): void
    {
        // Fulcrum features are managed through the database models
        // Runtime setting via Pennant's API is not supported
        throw PennantException::unsupportedOperation('set');
    }

    /**
     * Set a feature flag's value for all scopes.
     * Fulcrum is database-managed, so this operation is not supported.
     */
    public function setForAllScopes(string $feature, mixed $value): void
    {
        throw PennantException::unsupportedOperation('setForAllScopes');
    }

    /**
     * Delete a feature flag's value.
     * Fulcrum is database-managed, so this operation is not supported.
     */
    public function delete(string $feature, mixed $scope): void
    {
        throw PennantException::unsupportedOperation('delete');
    }

    /**
     * Purge the given feature flags.
     * Fulcrum is database-managed, so this operation is not supported.
     *
     * @param  array<int, string>|null  $features
     */
    public function purge(?array $features): void
    {
        throw PennantException::unsupportedOperation('purge');
    }

    /**
     * Build context array from scope.
     *
     * @return array<int|string, mixed>|null
     */
    protected function buildContext(mixed $scope): ?array
    {
        return match (true) {
            $scope === null => null,
            is_array($scope) => $scope,
            is_object($scope) => $this->buildContextFromObject($scope),
            default => ['scope' => $scope],
        };
    }

    /**
     * Extract the authenticated user from scope if present.
     */
    protected function extractUser(mixed $scope): ?Authenticatable
    {
        if ($scope instanceof Authenticatable) {
            return $scope;
        }

        // Handle case where Pennant passes an array containing a user
        if (is_array($scope)) {
            foreach ($scope as $item) {
                if ($item instanceof Authenticatable) {
                    return $item;
                }
            }
        }

        return null;
    }

    protected function stringifyKey(mixed $key): string
    {
        return match (true) {
            is_scalar($key) => (string) $key,
            is_object($key) && method_exists($key, '__toString') => (string) $key,
            default => '',
        };
    }

    /**
     * @return array<int|string, mixed>
     */
    protected function buildContextFromObject(object $scope): array
    {
        $context = [];
        $context = array_merge($this->extractUserContext($scope), $context);
        $context = array_merge($this->extractObjectAttributes($scope), $context);

        return $context;
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractUserContext(object $scope): array
    {
        if (! $scope instanceof Authenticatable) {
            return [];
        }

        $context = ['id' => $scope->getAuthIdentifier()];
        $email = $this->resolveUserEmail($scope);

        if ($email !== null) {
            $context['email'] = $email;
        }

        return $context;
    }

    protected function resolveUserEmail(object $scope): ?string
    {
        $email = match (true) {
            $scope instanceof \Illuminate\Contracts\Auth\CanResetPassword => $scope->getEmailForPasswordReset(),
            isset($scope->email) => $scope->email,
            isset($scope->attributes['email']) => $scope->attributes['email'],
            default => null,
        };

        return is_string($email) ? $email : null;
    }

    /**
     * @return array<int|string, mixed>
     */
    protected function extractObjectAttributes(object $scope): array
    {
        $attributes = [];

        if (method_exists($scope, 'getAttributes')) {
            $attributes = $scope->getAttributes();
        } elseif (method_exists($scope, 'toArray')) {
            $attributes = $scope->toArray();
        } else {
            $attributes = (array) $scope;
        }

        return $attributes;
    }

    public function definedFeaturesForScope(mixed $scope): array
    {
        return array_values($this->defined());
    }
}
