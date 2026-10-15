<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\Modules;

use Illuminate\Config\Repository;
use InvalidArgumentException;

class ModuleConfiguration
{
    public function __construct(private readonly Repository $config) {}

    /** @return array<string, array{activation_setting: ?string, access_setting: ?string, requires: list<string>}> */
    public function mappings(): array
    {
        $mappings = $this->config->get('fulcrum.modules.mappings', []);
        if (! is_array($mappings)) {
            throw new InvalidArgumentException('Fulcrum module mappings must be an array.');
        }

        $result = [];
        foreach ($mappings as $name => $mapping) {
            if (! is_string($name) || $name === '') {
                throw new InvalidArgumentException('Fulcrum module mappings require module names.');
            }
            $result[$name] = $this->parseMapping($name, $mapping);
        }
        $this->validateRelationships($result);

        return $result;
    }

    /** @return array{activation_setting: ?string, access_setting: ?string, requires: list<string>} */
    private function parseMapping(string $name, mixed $mapping): array
    {
        if (is_string($mapping)) {
            $mapping = ['access_setting' => $mapping];
        }
        if (! is_array($mapping)) {
            throw new InvalidArgumentException("Module {$name} requires a setting definition.");
        }
        $activation = $this->settingKey($name, 'activation', $mapping['activation_setting'] ?? null);
        $access = $this->settingKey($name, 'access', $mapping['access_setting'] ?? null);
        $requires = $this->dependencies($name, $mapping['requires'] ?? []);
        if ($activation === null && $requires !== []) {
            throw new InvalidArgumentException("Module {$name} needs an activation setting to declare activation dependencies.");
        }

        return ['activation_setting' => $activation, 'access_setting' => $access, 'requires' => $requires];
    }

    private function settingKey(string $name, string $kind, mixed $key): ?string
    {
        if ($key !== null && (! is_string($key) || $key === '')) {
            throw new InvalidArgumentException("Invalid {$kind} setting key for module {$name}.");
        }

        return $key;
    }

    /** @return list<string> */
    private function dependencies(string $name, mixed $requires): array
    {
        if (! is_array($requires) || ! array_is_list($requires)) {
            throw new InvalidArgumentException("Module {$name} requires a list of dependencies.");
        }
        foreach ($requires as $dependency) {
            if (! is_string($dependency) || $dependency === '' || $dependency === $name) {
                throw new InvalidArgumentException("Invalid dependency for module {$name}.");
            }
        }

        return $requires;
    }

    /** @param array<string, array{activation_setting: ?string, access_setting: ?string, requires: list<string>}> $mappings */
    private function validateRelationships(array $mappings): void
    {
        $activationKeys = array_filter(array_column($mappings, 'activation_setting'), fn (?string $key) => $key !== null);
        if (count($activationKeys) !== count(array_unique($activationKeys))) {
            throw new InvalidArgumentException('Module activation settings must be unique.');
        }
        foreach ($mappings as $name => $mapping) {
            if ($mapping['access_setting'] !== null && in_array($mapping['access_setting'], $activationKeys, true)) {
                throw new InvalidArgumentException('Module activation settings must be separate from access settings.');
            }
            foreach ($mapping['requires'] as $dependency) {
                if (($mappings[$dependency]['activation_setting'] ?? null) === null) {
                    throw new InvalidArgumentException("Module {$name} requires managed module {$dependency}.");
                }
            }
        }
    }

    /** @return array<string, array{activation_setting: string, access_setting: ?string, requires: list<string>}> */
    public function managed(): array
    {
        return array_filter($this->mappings(), fn (array $mapping) => $mapping['activation_setting'] !== null);
    }

    public function fingerprint(): string
    {
        $definitions = [];
        foreach ($this->managed() as $name => $mapping) {
            $requires = $mapping['requires'];
            sort($requires);
            $definitions[$name] = [$mapping['activation_setting'], $requires];
        }
        ksort($definitions);

        return hash('sha256', json_encode($definitions, JSON_THROW_ON_ERROR));
    }
}
