<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\PHPStan;

use PHPStan\PhpDoc\DefaultStubFilesProvider;
use PHPStan\PhpDoc\StubFilesProvider;

final class SqlImportStubFilesProvider implements StubFilesProvider
{
    public function __construct(private DefaultStubFilesProvider $provider) {}

    public function getStubFiles(): array
    {
        return $this->withoutLarastanDatabaseStub($this->provider->getStubFiles());
    }

    public function getProjectStubFiles(): array
    {
        return $this->withoutLarastanDatabaseStub($this->provider->getProjectStubFiles());
    }

    /**
     * Our database stub preserves Larastan's declarations and adds support for
     * dynamic SQL imports. Loading both would declare Connection twice.
     *
     * @param  list<string>  $files
     * @return list<string>
     */
    private function withoutLarastanDatabaseStub(array $files): array
    {
        return array_values(array_filter($files, static fn (string $file): bool => ! str_ends_with(
            str_replace('\\', '/', $file),
            '/larastan/larastan/stubs/common/Database/Database.stub',
        )));
    }
}
