<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Console\Commands\MakeSettingMigrationCommand;
use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

test('migration command rejects missing and non string names before writing files', function ($name) {
    $files = Mockery::mock(Filesystem::class);
    $files->shouldNotReceive('put', 'makeDirectory');
    $command = Mockery::mock(MakeSettingMigrationCommand::class)->makePartial();
    $command->__construct($files);
    $command->shouldReceive('argument')->with('name')->andReturn($name);
    $output = new BufferedOutput;
    $style = new OutputStyle(new ArrayInput([]), $output);
    $command->setOutput($style);
    (new ReflectionProperty($command, 'components'))->setValue($command, new Factory($style));

    expect($command->handle())->toBe(1)
        ->and($output->fetch())->toContain('A valid migration name is required.');
})->with([[''], [null], [123]]);

test('migration command resolves root storage and empty path options and normalizes names', function (string $path) {
    $directory = storage_path('migration-edge-'.bin2hex(random_bytes(8)));
    $files = new Filesystem;
    $originalStorage = storage_path();
    $originalDatabase = database_path();
    Carbon::setTestNow('2026-01-13 15:38:52');
    try {
        $this->app->useStoragePath($directory);
        $this->app->useDatabasePath($directory.'/database');
        $this->artisan('make:setting-migration', ['name' => '  Add Feature Flag  ', '--path' => $path])
            ->expectsOutputToContain('created successfully')->assertExitCode(0);
        $destination = $path === 'storage' ? $directory : $directory.'/database/migrations';
        $filename = $destination.'/2026_01_13_153852_add_feature_flag.php';
        expect($files->exists($filename))->toBeTrue()
            ->and($files->get($filename))->toContain('return new class extends SettingMigration');
    } finally {
        $this->app->useStoragePath($originalStorage);
        $this->app->useDatabasePath($originalDatabase);
        Carbon::setTestNow();
        $files->deleteDirectory($directory);
    }
})->with(['storage', '']);
