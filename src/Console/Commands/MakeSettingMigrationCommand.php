<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeSettingMigrationCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'make:setting-migration 
                            {name : The name of the migration}
                            {--path= : The location where the migration file should be created}';

    /**
     * The console command description.
     */
    protected $description = 'Create a new setting migration file';

    public function __construct(
        protected Filesystem $files
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = Str::snake(trim($this->argument('name')));
        $path = $this->getMigrationPath();

        $this->ensureDirectoryExists($path);

        $filename = $this->getFilename($name);
        $filepath = $path.'/'.$filename;

        if ($this->files->exists($filepath)) {
            $this->components->error("Migration [{$filename}] already exists.");

            return self::FAILURE;
        }

        $this->files->put($filepath, $this->getStub($name));

        $this->components->info("Setting migration [{$filename}] created successfully.");

        return self::SUCCESS;
    }

    /**
     * Get the migration filename.
     */
    protected function getFilename(string $name): string
    {
        return now()->format('Y_m_d_His').'_'.$name.'.php';
    }

    /**
     * Get the path to the migration directory.
     */
    protected function getMigrationPath(): string
    {
        if ($path = $this->option('path')) {
            return $this->laravel->basePath($path);
        }

        return $this->laravel->databasePath('migrations');
    }

    /**
     * Ensure the migration directory exists.
     */
    protected function ensureDirectoryExists(string $path): void
    {
        if (! $this->files->isDirectory($path)) {
            $this->files->makeDirectory($path, 0755, true);
        }
    }

    /**
     * Get the migration stub content.
     */
    protected function getStub(string $name): string
    {
        return $this->files->get(__DIR__.'/../../../stubs/setting-migration.stub');
    }
}
