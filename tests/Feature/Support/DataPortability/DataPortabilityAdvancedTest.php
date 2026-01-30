<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Feature\Support\DataPortability;

use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Models\SettingValue;
use GaiaTools\FulcrumSettings\Support\DataPortability\ExportManager;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\CsvFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\Formatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\JsonFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\XmlFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\ImportManager;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use GaiaTools\FulcrumSettings\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;

class DataPortabilityAdvancedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        FulcrumContext::force(true);
    }

    protected function tearDown(): void
    {
        FulcrumContext::force(false);
        parent::tearDown();
    }

    public function test_export_manager_anonymize_rules_and_variants()
    {
        $setting = Setting::create([
            'key' => 'test_setting',
            'type' => 'string',
            'description' => 'Original Description',
        ]);

        $rule = $setting->rules()->create([
            'name' => 'Original Rule Name',
            'priority' => 1,
        ]);

        $rule->rolloutVariants()->create([
            'name' => 'Original Variant Name',
            'weight' => 100,
        ]);

        $manager = new ExportManager;
        $path = $manager->export(new JsonFormatter, [
            'filename' => 'anonymized_full.json',
            'anonymize' => true,
        ]);

        $data = json_decode(Storage::disk('local')->get('anonymized_full.json'), true);

        $this->assertEquals('Anonymized description', $data[0]['description']);
        $this->assertEquals('Anonymized Rule', $data[0]['rules'][0]['name']);
        $this->assertEquals('Anonymized Variant', $data[0]['rules'][0]['rollout_variants'][0]['name']);

        $this->assertArrayNotHasKey('created_at', $data[0]);
        $this->assertArrayNotHasKey('created_at', $data[0]['rules'][0]);
        $this->assertArrayNotHasKey('created_at', $data[0]['rules'][0]['rollout_variants'][0]);
    }

    public function test_export_manager_decrypt_rules_and_variants()
    {
        $setting = Setting::create([
            'key' => 'masked_setting',
            'type' => 'string',
            'masked' => true,
        ]);

        $rule = $setting->rules()->create(['priority' => 1]);
        $ruleValue = new SettingValue([
            'valuable_type' => $rule->getMorphClass(),
            'valuable_id' => $rule->getKey(),
            'value' => 'secret-rule-value', // Let the model handle encryption
        ]);
        $ruleValue->save();

        $variant = $rule->rolloutVariants()->create(['name' => 'v1', 'weight' => 100]);
        $variantValue = new SettingValue([
            'valuable_type' => $variant->getMorphClass(),
            'valuable_id' => $variant->getKey(),
            'value' => 'secret-variant-value',
        ]);
        $variantValue->save();

        $manager = new ExportManager;

        // With decrypt
        $path = $manager->export(new JsonFormatter, [
            'filename' => 'decrypted_full.json',
            'decrypt' => true,
        ]);

        $data = json_decode(Storage::disk('local')->get('decrypted_full.json'), true);
        $this->assertEquals('secret-rule-value', $data[0]['rules'][0]['value']);
        $this->assertEquals('secret-variant-value', $data[0]['rules'][0]['rollout_variants'][0]['value']);
    }

    public function test_export_manager_absolute_path()
    {
        $manager = new ExportManager;
        $tempDir = sys_get_temp_dir();
        $filename = 'abs_export_'.uniqid().'.json';
        $fullPath = $tempDir.DIRECTORY_SEPARATOR.$filename;

        $resultPath = $manager->export(new JsonFormatter, [
            'directory' => $tempDir,
            'filename' => $filename,
        ]);

        $this->assertEquals($fullPath, $resultPath);
        $this->assertFileExists($fullPath);
        unlink($fullPath);
    }

    public function test_export_manager_unsupported_formatter_extension()
    {
        $manager = new ExportManager;
        $customFormatter = new class implements Formatter
        {
            public function format(array $data): string
            {
                return 'data';
            }

            public function parse(string $content): array
            {
                return [];
            }
        };

        $path = $manager->export($customFormatter, ['filename' => 'custom.txt']);
        $this->assertStringEndsWith('.txt', $path);
    }

    public function test_import_manager_conflict_handling_fail()
    {
        Setting::create(['key' => 'existing', 'type' => 'string']);

        $data = [['key' => 'existing', 'type' => 'string']];
        Storage::disk('local')->put('fail.json', json_encode($data));
        $path = Storage::disk('local')->path('fail.json');

        $manager = new ImportManager;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Setting already exists: existing');

        $manager->import(new JsonFormatter, $path, [
            'mode' => 'insert',
            'conflict_handling' => 'fail',
        ]);
    }

    public function test_import_manager_conflict_handling_log()
    {
        Setting::create(['key' => 'existing', 'type' => 'string']);

        $data = [['key' => 'existing', 'type' => 'string']];
        Storage::disk('local')->put('log.json', json_encode($data));
        $path = Storage::disk('local')->path('log.json');

        Log::shouldReceive('error')->once()->with(Mockery::on(fn ($msg) => str_contains($msg, 'Import failed for setting: existing')));

        $manager = new ImportManager;
        $result = $manager->import(new JsonFormatter, $path, [
            'mode' => 'insert',
            'conflict_handling' => 'log',
        ]);

        $this->assertTrue($result);
    }

    public function test_import_manager_conflict_handling_skip()
    {
        Setting::create(['key' => 'existing', 'type' => 'string']);

        $data = [
            ['key' => 'existing', 'type' => 'string'],
            ['key' => 'new', 'type' => 'string'],
        ];
        Storage::disk('local')->put('skip.json', json_encode($data));
        $path = Storage::disk('local')->path('skip.json');

        $manager = new ImportManager;
        $result = $manager->import(new JsonFormatter, $path, [
            'mode' => 'insert',
            'conflict_handling' => 'skip',
        ]);

        $this->assertTrue($result);
        $this->assertDatabaseHas('settings', ['key' => 'new']);
    }

    public function test_import_manager_chunk_size()
    {
        $data = [];
        for ($i = 0; $i < 10; $i++) {
            $data[] = ['key' => "setting_$i", 'type' => 'string'];
        }
        Storage::disk('local')->put('chunk.json', json_encode($data));
        $path = Storage::disk('local')->path('chunk.json');

        $manager = new ImportManager;
        $result = $manager->import(new JsonFormatter, $path, [
            'chunk_size' => 3,
        ]);

        $this->assertTrue($result);
        $this->assertCount(10, Setting::all());
    }

    public function test_import_manager_nested_structures()
    {
        $data = [
            [
                'key' => 'complex_setting',
                'type' => 'string',
                'rules' => [
                    [
                        'name' => 'Rule 1',
                        'priority' => 10,
                        'value' => 'rule-value',
                        'conditions' => [
                            ['attribute' => 'user_id', 'operator' => 'equals', 'value' => '1'],
                        ],
                        'rollout_variants' => [
                            ['name' => 'v1', 'weight' => 50, 'value' => 'v1-value'],
                            ['name' => 'v2', 'weight' => 50, 'value' => 'v2-value'],
                        ],
                    ],
                ],
            ],
        ];
        Storage::disk('local')->put('complex.json', json_encode($data));
        $path = Storage::disk('local')->path('complex.json');

        // We need to bypass the resolution issue during import too.
        // The ImportManager uses $rule->value()->create(['value' => ...])
        // Since we are in force mode, it might still fail if resolution fails.

        $manager = new ImportManager;
        $manager->import(new JsonFormatter, $path);

        $setting = Setting::where('key', 'complex_setting')->first();
        $this->assertCount(1, $setting->rules);
        $rule = $setting->rules[0];
        $this->assertEquals('rule-value', $rule->getValue());
        $this->assertCount(1, $rule->conditions);
        $this->assertCount(2, $rule->rolloutVariants);
        $this->assertEquals('v1-value', $rule->rolloutVariants[0]->getValue());
    }

    public function test_csv_formatter_complex_structures()
    {
        $formatter = new CsvFormatter;
        $data = [
            [
                'key' => 's1',
                'type' => 'string', // Need type for valid setting-like structure if we were to import it
                'meta' => ['a' => '1', 'b' => '2'],
                'tags' => ['x', 'y'],
            ],
        ];

        $csv = $formatter->format($data);
        $this->assertStringContainsString('key,type,meta.a,meta.b,tags.0,tags.1', $csv);

        $parsed = $formatter->parse($csv);
        $this->assertEquals($data, $parsed);
    }

    public function test_xml_formatter_complex_structures()
    {
        $formatter = new XmlFormatter;
        $data = [
            [
                'key' => 's1',
                'rules' => [
                    ['name' => 'r1', 'value' => 'v1'],
                ],
            ],
        ];

        $xml = $formatter->format($data);
        $this->assertStringContainsString('<key>s1</key>', $xml);
        $this->assertStringContainsString('<rules>', $xml);

        $parsed = $formatter->parse($xml);
        $this->assertArrayHasKey('setting', $parsed);
    }

    public function test_import_manager_get_content_fallback_to_storage()
    {
        $data = [['key' => 'fallback', 'type' => 'string']];
        Storage::disk('local')->put('test.json', json_encode($data));
        $manager = new ImportManager;

        $result = $manager->import(new JsonFormatter, 'test.json');
        $this->assertTrue($result);
        $this->assertDatabaseHas('settings', ['key' => 'fallback']);
    }

    public function test_import_manager_get_content_gz_fallback_to_storage()
    {
        $data = [['key' => 'fallback_gz', 'type' => 'string']];
        $json = json_encode($data);
        Storage::disk('local')->put('test.json.gz', gzencode($json));
        $manager = new ImportManager;

        $result = $manager->import(new JsonFormatter, 'test.json.gz');
        $this->assertTrue($result);
        $this->assertDatabaseHas('settings', ['key' => 'fallback_gz']);
    }

    public function test_export_manager_dry_run()
    {
        $manager = new ExportManager;
        $result = $manager->export(new JsonFormatter, ['dry_run' => true]);
        $this->assertTrue($result);
    }

    public function test_export_manager_gzip()
    {
        $manager = new ExportManager;
        $path = $manager->export(new JsonFormatter, [
            'filename' => 'test.json',
            'gzip' => true,
        ]);

        $this->assertStringEndsWith('.json.gz', $path);
        $content = Storage::disk('local')->get('test.json.gz');
        $this->assertEquals(json_encode([]), gzdecode($content));
    }

    public function test_import_manager_dry_run()
    {
        Storage::disk('local')->put('dry.json', json_encode([]));
        $manager = new ImportManager;
        $result = $manager->import(new JsonFormatter, Storage::disk('local')->path('dry.json'), [
            'dry_run' => true,
        ]);
        $this->assertTrue($result);
    }

    public function test_import_manager_truncate()
    {
        Setting::create(['key' => 'to_be_truncated', 'type' => 'string']);
        Storage::disk('local')->put('empty.json', json_encode([]));

        $manager = new ImportManager;
        $manager->import(new JsonFormatter, Storage::disk('local')->path('empty.json'), [
            'truncate' => true,
        ]);

        $this->assertDatabaseMissing('settings', ['key' => 'to_be_truncated']);
    }

    public function test_csv_formatter_edge_cases()
    {
        $formatter = new CsvFormatter;

        // Empty data
        $this->assertEquals('', $formatter->format([]));

        // Empty parse
        $this->assertEquals([], $formatter->parse(''));

        // Mismatched columns
        $csv = "key,type\ns1,string,extra";
        $this->assertEquals([], $formatter->parse($csv));
    }

    public function test_xml_formatter_parse_failure()
    {
        $formatter = new XmlFormatter;
        $this->assertEquals([], $formatter->parse('invalid xml'));
    }

    public function test_import_manager_import_setting_upsert_existing()
    {
        $setting = Setting::create(['key' => 'existing', 'type' => 'string', 'description' => 'old']);
        $data = [['key' => 'existing', 'type' => 'string', 'description' => 'new', 'masked' => true, 'immutable' => true]];

        Storage::disk('local')->put('upsert.json', json_encode($data));
        $manager = new ImportManager;
        $manager->import(new JsonFormatter, Storage::disk('local')->path('upsert.json'), ['mode' => 'upsert']);

        $setting->refresh();
        $this->assertEquals('new', $setting->description);
        $this->assertTrue($setting->masked);
        $this->assertTrue($setting->immutable);
    }

    public function test_export_manager_transform_value_decryption_failure()
    {
        $setting = Setting::create([
            'key' => 'masked_failure',
            'type' => 'string',
            'masked' => true,
        ]);

        // Manually insert an invalid encrypted string
        $rule = $setting->rules()->create(['priority' => 1]);
        \DB::table('setting_values')->insert([
            'valuable_type' => $rule->getMorphClass(),
            'valuable_id' => $rule->getKey(),
            'value' => 'invalid-base64',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $manager = new ExportManager;
        $path = $manager->export(new JsonFormatter, ['decrypt' => true]);

        $data = json_decode(Storage::disk('local')->get(basename($path)), true);
        $this->assertEquals('invalid-base64', $data[0]['rules'][0]['value']);
    }

    public function test_export_manager_get_extension_default()
    {
        $manager = new ExportManager;
        $customFormatter = new class implements Formatter
        {
            public function format(array $data): string
            {
                return '';
            }

            public function parse(string $content): array
            {
                return [];
            }
        };

        $path = $manager->export($customFormatter);
        $this->assertStringEndsWith('.txt', $path);
    }

    public function test_import_manager_import_setting_minimal_data()
    {
        $data = [['key' => 'minimal', 'type' => 'string']];
        Storage::disk('local')->put('minimal.json', json_encode($data));
        $manager = new ImportManager;
        $manager->import(new JsonFormatter, Storage::disk('local')->path('minimal.json'));

        $this->assertDatabaseHas('settings', ['key' => 'minimal', 'description' => null]);
    }

    public function test_export_manager_transform_value_not_masked()
    {
        $setting = Setting::create([
            'key' => 'not_masked',
            'type' => 'string',
            'masked' => false,
        ]);

        $defaultValue = new SettingValue([
            'valuable_type' => $setting->getMorphClass(),
            'valuable_id' => $setting->getKey(),
            'value' => 'plain-value',
        ]);
        $defaultValue->save();

        $manager = new ExportManager;
        $path = $manager->export(new JsonFormatter, ['decrypt' => true]);

        $data = json_decode(Storage::disk('local')->get(basename($path)), true);
        $settingData = collect($data)->firstWhere('key', 'not_masked');
        $this->assertEquals('plain-value', $settingData['default_value']);
    }

    public function test_import_manager_get_content_path_not_found()
    {
        $manager = new ImportManager;
        // file_get_contents and Storage::get will both return false/null
        $result = $manager->import(new JsonFormatter, 'non-existent.json');
        $this->assertTrue($result);
    }

    public function test_export_manager_transform_condition_anonymize()
    {
        $setting = Setting::create(['key' => 'c_setting', 'type' => 'string']);
        $rule = $setting->rules()->create(['priority' => 1]);
        $condition = $rule->conditions()->create([
            'attribute' => 'user_id',
            'operator' => 'equals',
            'value' => '1',
        ]);

        $manager = new ExportManager;
        $path = $manager->export(new JsonFormatter, [
            'filename' => 'condition_anon.json',
            'anonymize' => true,
        ]);

        $data = json_decode(Storage::disk('local')->get('condition_anon.json'), true);
        $conditionData = $data[0]['rules'][0]['conditions'][0];

        $this->assertArrayNotHasKey('created_at', $conditionData);
        $this->assertArrayNotHasKey('updated_at', $conditionData);
        $this->assertEquals('user_id', $conditionData['attribute']);
    }

    public function test_csv_formatter_parse_empty_or_null_rows()
    {
        $formatter = new CsvFormatter;

        // Line 42: Triggered by string that results in empty array after filter
        // str_getcsv("\n", "\n") -> [null] -> filter -> []
        $this->assertEquals([], $formatter->parse("\n"));

        // Line 47: Triggered if $headerRow is falsy
        // We'd need array_shift to return a falsy value.
        // If $rows = [0], then array_shift returns 0.
        // str_getcsv("0", "\n") -> ["0"]
        // It's hard to get a literal 0 that passes array_filter but is falsy.
        // Actually, "0" is falsy in PHP but array_filter might keep it depending on flags.
        // By default array_filter removes "0".

        // Let's try to trigger line 42 with empty string (though trim handles it at line 36)
        // Line 36 handles trim($content) === ''
        // So line 42 needs something that is NOT empty after trim, but empty after str_getcsv and filter.
        // A single space " " -> trim returns " "? No, trim(" ") is "".
        // So maybe a tab? trim("\t") is "".

        // If I use a character that str_getcsv doesn't like or results in null.
        // Actually, $this->assertEquals([], $formatter->parse("\n")); SHOULD hit 42.
    }
}
