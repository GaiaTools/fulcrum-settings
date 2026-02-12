<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support;

use GaiaTools\FulcrumSettings\Contracts\GroupedSettingResolver as GroupedSettingResolverContract;
use GaiaTools\FulcrumSettings\Contracts\SettingResolver;
use Illuminate\Contracts\Auth\Authenticatable;

final class GroupedSettingResolver implements GroupedSettingResolverContract
{
    public function __construct(
        private SettingResolver $resolver,
        private string $group
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function all(mixed $scope = null, bool $stripGroupPrefix = true): array
    {
        $values = [];
        $keys = $this->resolver->getGroupKeys($this->group);

        foreach ($keys as $key) {
            $outputKey = $stripGroupPrefix ? $this->stripGroupPrefix($key) : $key;
            $values[$outputKey] = $this->resolver->resolve($key, $scope);
        }

        return $values;
    }

    public function forUser(?Authenticatable $user): self
    {
        return new self($this->resolver->forUser($user), $this->group);
    }

    public function forTenant(?string $tenantId): self
    {
        return new self($this->resolver->forTenant($tenantId), $this->group);
    }

    public function forGroup(string $group): self
    {
        $normalized = $this->normalizeGroup($group);

        return new self($this->resolver->forGroup($normalized), $normalized);
    }

    public function group(string $group): self
    {
        return $this->forGroup($group);
    }

    private function stripGroupPrefix(string $key): string
    {
        $prefix = $this->group.'.';

        return str_starts_with($key, $prefix) ? substr($key, strlen($prefix)) : $key;
    }

    private function normalizeGroup(string $group): string
    {
        $normalized = trim($group, " .\t\n\r\0\x0B");

        if ($normalized === '') {
            throw new \InvalidArgumentException('Group name cannot be empty.');
        }

        return $normalized;
    }
}
