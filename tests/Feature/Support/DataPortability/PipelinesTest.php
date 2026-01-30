<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Feature\Support\DataPortability;

use GaiaTools\FulcrumSettings\Http\Requests\ExportRequest;
use GaiaTools\FulcrumSettings\Http\Requests\ImportRequest;
use GaiaTools\FulcrumSettings\Support\DataPortability\ExportManager;
use GaiaTools\FulcrumSettings\Support\DataPortability\ImportManager;
use GaiaTools\FulcrumSettings\Support\DataPortability\Pipelines\Export\PrepareExport;
use GaiaTools\FulcrumSettings\Support\DataPortability\Pipelines\Import\ProcessImport;
use GaiaTools\FulcrumSettings\Support\DataPortability\Pipelines\Import\StoreData;
use GaiaTools\FulcrumSettings\Support\DataPortability\Pipelines\Import\ValidateFile;
use GaiaTools\FulcrumSettings\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PipelinesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_prepare_export_pipeline_formats()
    {
        $manager = \Mockery::mock(ExportManager::class);
        $manager->shouldReceive('export')->andReturn('path');

        $pipeline = new PrepareExport($manager);

        foreach (['json', 'xml', 'csv', 'yaml', 'yml', 'sql', 'invalid'] as $format) {
            $request = new ExportRequest;
            $request->merge(['format' => $format]);
            $pipeline->handle($request, function ($req) use ($format) {
                $expectedFormat = in_array($format, ['json', 'xml', 'csv', 'yaml', 'yml', 'sql']) ? $format : 'invalid';
                $this->assertEquals($expectedFormat, $req->attributes->get('export_data')['format']);
            });
        }
    }

    public function test_process_import_pipeline_formats()
    {
        $pipeline = new ProcessImport;

        foreach (['json', 'xml', 'csv', 'yaml', 'yml', 'sql', 'invalid'] as $format) {
            $file = UploadedFile::fake()->create('test.txt');
            $request = new ImportRequest;
            $request->files->set('file', $file);
            $request->merge(['format' => $format]);

            $pipeline->handle($request, function ($req) use ($format) {
                $formatterClass = match ($format) {
                    'json' => \GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\JsonFormatter::class,
                    'xml' => \GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\XmlFormatter::class,
                    'yaml', 'yml' => \GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\YamlFormatter::class,
                    'sql' => \GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\SqlFormatter::class,
                    default => \GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\CsvFormatter::class,
                };
                $this->assertInstanceOf($formatterClass, $req->attributes->get('formatter'));
            });
        }
    }

    public function test_process_import_pipeline_auto_detect_yaml()
    {
        $file = UploadedFile::fake()->create('test.yaml');
        $request = new ImportRequest;
        $request->files->set('file', $file);

        $pipeline = new ProcessImport;
        $pipeline->handle($request, function ($req) {
            $this->assertInstanceOf(\GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\YamlFormatter::class, $req->attributes->get('formatter'));
        });
    }

    public function test_process_import_pipeline_auto_detect_sql()
    {
        $file = UploadedFile::fake()->create('test.sql');
        $request = new ImportRequest;
        $request->files->set('file', $file);

        $pipeline = new ProcessImport;
        $pipeline->handle($request, function ($req) {
            $this->assertInstanceOf(\GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\SqlFormatter::class, $req->attributes->get('formatter'));
        });
    }

    public function test_process_import_pipeline_auto_detect_xml()
    {
        $file = UploadedFile::fake()->create('test.xml');
        $request = new ImportRequest;
        $request->files->set('file', $file);

        $pipeline = new ProcessImport;
        $pipeline->handle($request, function ($req) {
            $this->assertInstanceOf(\GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\XmlFormatter::class, $req->attributes->get('formatter'));
        });
    }

    public function test_process_import_pipeline_auto_detect_csv()
    {
        $file = UploadedFile::fake()->create('test.csv');
        $request = new ImportRequest;
        $request->files->set('file', $file);

        $pipeline = new ProcessImport;
        $pipeline->handle($request, function ($req) {
            $this->assertInstanceOf(\GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\CsvFormatter::class, $req->attributes->get('formatter'));
        });
    }

    public function test_process_import_pipeline_auto_detect_invalid()
    {
        $file = UploadedFile::fake()->create('test.foo');
        $request = new ImportRequest;
        $request->files->set('file', $file);

        $pipeline = new ProcessImport;
        $pipeline->handle($request, function ($req) {
            $this->assertInstanceOf(\GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\CsvFormatter::class, $req->attributes->get('formatter'));
        });
    }

    public function test_process_import_pipeline_auto_detect_json()
    {
        $file = UploadedFile::fake()->create('test.json');
        $request = new ImportRequest;
        $request->files->set('file', $file);

        $pipeline = new ProcessImport;
        $pipeline->handle($request, function ($req) {
            $this->assertInstanceOf(\GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\JsonFormatter::class, $req->attributes->get('formatter'));
        });
    }

    public function test_process_import_pipeline_auto_detect_gz()
    {
        $file = UploadedFile::fake()->create('test.json.gz');
        $request = new ImportRequest;
        $request->files->set('file', $file);

        $pipeline = new ProcessImport;
        $pipeline->handle($request, function ($req) {
            $this->assertInstanceOf(\GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\JsonFormatter::class, $req->attributes->get('formatter'));
        });
    }

    public function test_process_import_pipeline_explicit_format()
    {
        $file = UploadedFile::fake()->create('test.txt');
        $request = new ImportRequest;
        $request->files->set('file', $file);
        $request->merge(['format' => 'xml']);

        $pipeline = new ProcessImport;
        $pipeline->handle($request, function ($req) {
            $this->assertInstanceOf(\GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\XmlFormatter::class, $req->attributes->get('formatter'));
        });
    }

    public function test_store_data_pipeline()
    {
        $manager = \Mockery::mock(ImportManager::class);
        $manager->shouldReceive('import')->once()->andReturn(true);

        $request = new ImportRequest;
        $request->attributes->set('formatter', new \GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\JsonFormatter);
        $request->attributes->set('file_path', 'some/path');

        $pipeline = new StoreData($manager);
        $pipeline->handle($request, function ($req) {
            $this->assertEquals(['success' => true, 'count' => 0], $req->attributes->get('import_result'));
        });
    }

    public function test_validate_file_pipeline_success()
    {
        $file = UploadedFile::fake()->create('test.json');
        $request = new ImportRequest;
        $request->files->set('file', $file);

        $pipeline = new ValidateFile;
        $result = $pipeline->handle($request, function ($req) {
            return 'next';
        });

        $this->assertEquals('next', $result);
    }

    public function test_validate_file_pipeline_failure()
    {
        $request = new ImportRequest;
        // No file

        $pipeline = new ValidateFile;

        $this->expectException(ValidationException::class);
        $pipeline->handle($request, function ($req) {});
    }
}
