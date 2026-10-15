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
        $activationKeys = [];
        foreach ($mappings as $name => $mapping) {
            if (! is_string($name) || $name === '' || (! is_string($mapping) && ! is_array($mapping))) {
                throw new InvalidArgumentException('Fulcrum module mappings require module names and setting definitions.');
            }
            $mapping = is_string($mapping) ? ['access_setting' => $mapping] : $mapping;
            $activation = $mapping['activation_setting'] ?? null;
            $access = $mapping['access_setting'] ?? null;
            if ($activation !== null && (! is_string($activation) || $activation === '')) {
                throw new InvalidArgumentException("Invalid activation setting key for module {$name}.");
            }
            if ($access !== null && (! is_string($access) || $access === '')) {
                throw new InvalidArgumentException("Invalid access setting key for module {$name}.");
            }
            if ($activation !== null && ($activation === $access || in_array($activation, $activationKeys, true))) {
                throw new InvalidArgumentException('Module activation settings must be unique and separate from access settings.');
            }
            $requires = $mapping['requires'] ?? [];
            if (! is_array($requires) || ! array_is_list($requires)) {
                throw new InvalidArgumentException("Module {$name} requires a list of dependencies.");
            }
            foreach ($requires as $dependency) {
                if (! is_string($dependency) || $dependency === '' || $dependency === $name) {
                    throw new InvalidArgumentException("Invalid dependency for module {$name}.");
                }
            }
            if ($activation === null && $requires !== []) {
                throw new InvalidArgumentException("Module {$name} needs an activation setting to declare activation dependencies.");
            }
            $activationKeys[] = $activation;
            $result[$name] = ['activation_setting' => $activation, 'access_setting' => $access, 'requires' => $requires];
        }
        foreach ($result as $name => $mapping) {
            if ($mapping['access_setting'] !== null && in_array($mapping['access_setting'], $activationKeys, true)) {
                throw new InvalidArgumentException('Module activation settings must be separate from access settings.');
            }
            foreach ($mapping['requires'] as $dependency) {
                if (($result[$dependency]['activation_setting'] ?? null) === null) {
                    throw new InvalidArgumentException("Module {$name} requires managed module {$dependency}.");
                }
            }
        }

        return $result;
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
