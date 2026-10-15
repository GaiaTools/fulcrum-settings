<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\Modules\Stores;

use GaiaTools\FulcrumSettings\Contracts\Modules\ActivationStore;
use GaiaTools\FulcrumSettings\Exceptions\ModuleActivationException;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;

class FilesystemActivationStore implements ActivationStore
{
    public function __construct(private readonly Repository $config, private readonly Filesystem $files) {}

    public function read(): ?array
    {
        $path = $this->path();
        if (! $this->files->exists($path)) {
            return null;
        }
        $state = json_decode($this->files->get($path), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($state)) {
            throw new ModuleActivationException('Invalid module activation document.');
        }

        return $state;
    }

    public function write(array $state): void
    {
        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
        $path = $this->path();
        $this->files->ensureDirectoryExists(dirname($path));
        $temporary = tempnam(dirname($path), '.fulcrum-');
        if ($temporary === false) {
            throw new ModuleActivationException('Cannot create module activation file.');
        }
        try {
            $permissions = $this->files->exists($path) ? fileperms($path) : (0666 & ~umask());
            if ($permissions === false || file_put_contents($temporary, $json) !== strlen($json)
                || ! chmod($temporary, $permissions & 0777) || ! rename($temporary, $path)) {
                throw new ModuleActivationException('Cannot publish module activation file.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    private function path(): string
    {
        return $this->config->string('fulcrum.modules.storage.path');
    }
}
