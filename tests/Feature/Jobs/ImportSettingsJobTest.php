<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Feature\Jobs;

use GaiaTools\FulcrumSettings\Jobs\ImportSettingsJob;
use GaiaTools\FulcrumSettings\Support\DataPortability\ImportManager;
use GaiaTools\FulcrumSettings\Tests\TestCase;
use Illuminate\Support\Facades\Queue;
use Mockery;

class ImportSettingsJobTest extends TestCase
{
    public function test_job_can_be_dispatched()
    {
        Queue::fake();

        ImportSettingsJob::dispatch('test.csv', 'csv');

        Queue::assertPushed(ImportSettingsJob::class);
    }

    public function test_job_has_correct_tags()
    {
        $job = new ImportSettingsJob('test.csv', 'csv', [], 'tenant-1', 'batch-123');

        $tags = $job->tags();

        $this->assertContains('fulcrum', $tags);
        $this->assertContains('type:import', $tags);
        $this->assertContains('tenant:tenant-1', $tags);
        $this->assertContains('batch:batch-123', $tags);
        $this->assertContains('format:csv', $tags);
    }

    public function test_job_handles_import()
    {
        $manager = Mockery::mock(ImportManager::class);
        $manager->shouldReceive('import')
            ->once()
            ->with(Mockery::any(), 'test.csv', ['mode' => 'upsert'])
            ->andReturn(true);

        $job = new ImportSettingsJob('test.csv', 'csv', ['mode' => 'upsert']);
        $job->handle($manager);

        $this->assertTrue(true); // If we reached here without exception, it's good
    }

    public function test_job_throws_exception_for_unsupported_format()
    {
        $manager = Mockery::mock(ImportManager::class);
        $job = new ImportSettingsJob('test.ext', 'invalid');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported format: invalid');

        $job->handle($manager);
    }

    /**
     * @dataProvider supportedFormatsProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('supportedFormatsProvider')]
    public function test_it_uses_correct_formatter_for_each_format(string $format, string $expectedFormatterClass)
    {
        $manager = Mockery::mock(ImportManager::class);
        $manager->shouldReceive('import')
            ->once()
            ->with(Mockery::type($expectedFormatterClass), 'test.'.$format, Mockery::any())
            ->andReturn(true);

        $job = new ImportSettingsJob('test.'.$format, $format);
        $job->handle($manager);

        $this->assertTrue(true);
    }

    public static function supportedFormatsProvider(): array
    {
        return [
            ['json', \GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\JsonFormatter::class],
            ['xml', \GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\XmlFormatter::class],
            ['yaml', \GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\YamlFormatter::class],
            ['yml', \GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\YamlFormatter::class],
            ['sql', \GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\SqlFormatter::class],
            ['csv', \GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\CsvFormatter::class],
        ];
    }
}
