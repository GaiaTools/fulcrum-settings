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
            ->map(static function ($key): string {
                if (is_scalar($key)) {
                    return (string) $key;
                }
                if (is_object($key) && method_exists($key, '__toString')) {
                    return (string) $key;
                }

                return '';
            })
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
                $feature = $scopes;
                $scopes = [null];
                $isConvenienceCall = true;
            } else {
                $isConvenienceCall = false;
            }

            if (! is_array($scopes)) {
                continue;
            }

            $results[$feature] = [];

            foreach ($scopes as $scope) {
                $value = $this->get((string) $feature, $scope);

                if ($isConvenienceCall) {
                    $results[$feature] = $value;
                } else {
                    $results[$feature][] = $value;
                }
            }
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
        if ($scope === null) {
            return null;
        }

        if (is_array($scope)) {
            return $scope;
        }

        if (is_object($scope)) {
            // Convert object to array for rule evaluation
            $context = [];

            // If it's a user, add common user properties
            if ($scope instanceof Authenticatable) {
                $context['id'] = $scope->getAuthIdentifier();
                if ($scope instanceof \Illuminate\Contracts\Auth\CanResetPassword) {
                    $context['email'] = $scope->getEmailForPasswordReset();
                } elseif (isset($scope->email)) {
                    $context['email'] = $scope->email;
                } elseif (isset($scope->attributes['email'])) {
                    $context['email'] = $scope->attributes['email'];
                }
            }

            // If it's an Eloquent model, use its attributes
            if (method_exists($scope, 'getAttributes')) {
                $context = array_merge($scope->getAttributes(), $context);
            } elseif (method_exists($scope, 'toArray')) {
                $context = array_merge($scope->toArray(), $context);
            } else {
                $context = array_merge((array) $scope, $context);
            }

            return $context;
        }

        // Scalar scope - wrap in array
        return ['scope' => $scope];
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

    public function definedFeaturesForScope(mixed $scope): array
    {
        return array_values($this->defined());
    }
}
