<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Feature\Jobs;

use GaiaTools\FulcrumSettings\Jobs\ExportSettingsJob;
use GaiaTools\FulcrumSettings\Support\DataPortability\ExportManager;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\CsvFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\JsonFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\SqlFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\XmlFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\YamlFormatter;
use GaiaTools\FulcrumSettings\Tests\TestCase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;

class ExportSettingsJobTest extends TestCase
{
    public function test_job_can_be_dispatched()
    {
        Queue::fake();

        ExportSettingsJob::dispatch('csv');

        Queue::assertPushed(ExportSettingsJob::class);
    }

    public function test_job_has_correct_tags()
    {
        $job = new ExportSettingsJob('csv', [], 'tenant-1', 'batch-123');

        $tags = $job->tags();

        $this->assertContains('fulcrum', $tags);
        $this->assertContains('type:export', $tags);
        $this->assertContains('tenant:tenant-1', $tags);
        $this->assertContains('batch:batch-123', $tags);
        $this->assertContains('format:csv', $tags);
    }

    public function test_job_handles_export()
    {
        $manager = Mockery::mock(ExportManager::class);
        $manager->shouldReceive('export')
            ->once()
            ->with(Mockery::any(), ['decrypt' => true])
            ->andReturn('path/to/export.csv');

        $job = new ExportSettingsJob('csv', ['decrypt' => true]);
        $job->handle($manager);

        $this->assertTrue(true);
    }

    public function test_job_throws_exception_for_unsupported_format()
    {
        $manager = Mockery::mock(ExportManager::class);
        $job = new ExportSettingsJob('invalid');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported format: invalid');

        $job->handle($manager);
    }

    /**
     * @dataProvider supportedFormatsProvider
     */
    #[DataProvider('supportedFormatsProvider')]
    public function test_it_uses_correct_formatter_for_each_format(string $format, string $expectedFormatterClass)
    {
        $manager = Mockery::mock(ExportManager::class);
        $manager->shouldReceive('export')
            ->once()
            ->with(Mockery::type($expectedFormatterClass), Mockery::any())
            ->andReturn('path/to/export.'.$format);

        $job = new ExportSettingsJob($format);
        $job->handle($manager);

        $this->assertTrue(true);
    }

    public static function supportedFormatsProvider(): array
    {
        return [
            ['json', JsonFormatter::class],
            ['xml', XmlFormatter::class],
            ['yaml', YamlFormatter::class],
            ['yml', YamlFormatter::class],
            ['sql', SqlFormatter::class],
            ['csv', CsvFormatter::class],
        ];
    }
}
