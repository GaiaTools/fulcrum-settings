<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\Registrars;

use GaiaTools\FulcrumSettings\Support\Settings\FulcrumSettings;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

class SettingsDiscovery
{
    /**
     * @param  array<mixed>  $paths
     * @return array<int, class-string<FulcrumSettings>>
     */
    public function discover(array $paths): array
    {
        $settings = [];

        foreach ($this->normalizeDiscoveryPaths($paths) as $path) {
            foreach ($this->expandDiscoveryPaths($path) as $expandedPath) {
                $settings = array_merge($settings, $this->discoverSettingsInPath($expandedPath));
            }
        }

        return array_values(array_unique($settings));
    }

    /**
     * @param  array<mixed>  $paths
     * @return array<int, string>
     */
    protected function normalizeDiscoveryPaths(array $paths): array
    {
        return array_values(array_filter($paths, fn ($path) => is_string($path) && $path !== ''));
    }

    /**
     * @return array<int, string>
     */
    protected function expandDiscoveryPaths(string $path): array
    {
        $expandedPaths = str_contains($path, '*')
            ? glob($path, GLOB_ONLYDIR)
            : [$path];

        if (! is_array($expandedPaths)) {
            return [];
        }

        return array_values(array_filter($expandedPaths, fn ($expandedPath) => $expandedPath !== ''));
    }

    /**
     * @return array<int, class-string<FulcrumSettings>>
     */
    protected function discoverSettingsInPath(string $expandedPath): array
    {
        if (! is_dir($expandedPath)) {
            return [];
        }

        $settings = [];
        foreach ((new Finder)->in($expandedPath)->files()->name('*.php') as $file) {
            $class = $this->getClassFromFile($file->getPathname());

            if ($this->isConcreteSettingsClass($class)) {
                $settings[] = $class;
            }
        }

        return $settings;
    }

    /**
     * @phpstan-assert-if-true class-string<FulcrumSettings> $class
     */
    protected function isConcreteSettingsClass(?string $class): bool
    {
        if (! $class) {
            return false;
        }

        if (! is_subclass_of($class, FulcrumSettings::class)) {
            return false;
        }

        return ! (new ReflectionClass($class))->isAbstract();
    }

    protected function getClassFromFile(string $path): ?string
    {
        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }
        $namespace = '';
        $class = '';

        if (preg_match('/namespace\s+(.+?);/', $content, $matches)) {
            $namespace = $matches[1];
        }

        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            $class = $matches[1];
        }

        return $namespace ? $namespace.'\\'.$class : $class;
    }
}
