<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Exceptions\DuplicateSettingException;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\CsvFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\JsonFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\YamlFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\ImportManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('import counts successful inserts and updates and resets for each call', function () {
    $formatter = new JsonFormatter;
    $manager = new ImportManager;
    Storage::put('import.json', $formatter->format([
        ['key' => 'first', 'type' => 'string'],
        ['key' => 'second', 'type' => 'string'],
    ]));
    expect($manager->importWithResult($formatter, 'import.json'))->toBe(['success' => true, 'count' => 2]);
    expect($manager->importWithResult($formatter, 'import.json'))->toBe(['success' => true, 'count' => 2]);
    Storage::put('empty.json', '[]');
    expect($manager->importWithResult($formatter, 'empty.json'))->toBe(['success' => true, 'count' => 0]);
});

test('skipped failed and malformed records do not contribute to the count', function (string $handling) {
    Setting::create(['key' => 'duplicate', 'type' => 'string']);
    Storage::put('import.json', json_encode([
        ['key' => 'duplicate', 'type' => 'string'],
        ['key' => 'valid', 'type' => 'string'],
        ['type' => 'string'],
        ['key' => '', 'type' => 'string'],
        ['key' => 'invalid-rule', 'type' => 'string', 'rules' => [['conditions' => [['operator' => 'equals', 'value' => 1]]]]],
    ]));
    $result = (new ImportManager)->importWithResult(new JsonFormatter, 'import.json', ['mode' => 'insert', 'conflict_handling' => $handling, 'chunk_size' => 1]);
    expect($result)->toBe(['success' => true, 'count' => 1])
        ->and(Setting::where('key', 'invalid-rule')->exists())->toBeFalse()
        ->and(Setting::count())->toBe(2);
})->with(['skip', 'log']);

test('dry run counts no writes and failed imports roll back', function () {
    Storage::put('import.json', '[{"key":"new","type":"string"}]');
    $manager = new ImportManager;
    expect($manager->importWithResult(new JsonFormatter, 'import.json', ['dry_run' => true]))->toBe(['success' => true, 'count' => 0])
        ->and(Setting::count())->toBe(0);
    Storage::put('import.json', '[{"key":"new","type":"string"},{"key":"new","type":"string"}]');
    expect(fn () => $manager->importWithResult(new JsonFormatter, 'import.json', ['mode' => 'insert']))->toThrow(DuplicateSettingException::class);
    expect(Setting::count())->toBe(0);
});

test('the selected connection receives structured imports and truncation', function () {
    config(['database.connections.portability' => config('database.connections.testing')]);
    $database = DB::connection('portability');
    $database->getSchemaBuilder()->create('settings', function ($table) {
        $table->id();
        $table->string('key');
        $table->string('type');
        $table->string('tenant_id')->nullable();
        $table->string('group')->nullable();
        $table->text('description')->nullable();
        $table->boolean('masked');
        $table->boolean('immutable');
        $table->timestamps();
    });
    foreach (['setting_rules', 'setting_rule_conditions', 'setting_rule_rollout_variants', 'setting_values'] as $table) {
        $database->getSchemaBuilder()->create($table, function ($schema) {
            $schema->id();
            $schema->string('tenant_id')->nullable();
        });
    }
    Setting::create(['key' => 'default-only', 'type' => 'string']);
    Storage::put('other.json', '[{"key":"selected-only","type":"string"}]');
    expect((new ImportManager)->importWithResult(new JsonFormatter, 'other.json', ['connection' => 'portability', 'truncate' => true]))->toBe(['success' => true, 'count' => 1])
        ->and($database->table('settings')->pluck('key')->all())->toBe(['selected-only'])
        ->and(Setting::pluck('key')->all())->toBe(['default-only']);
});

test('malformed raw SQL cannot count as an imported setting', function () {
    Storage::put('raw.json', '[{"__raw_sql":false}]');
    expect((new ImportManager)->importWithResult(new JsonFormatter, 'raw.json'))->toBe(['success' => true, 'count' => 0])
        ->and(Setting::count())->toBe(0);
});

test('invalid nested children are skipped without discarding the setting', function () {
    Storage::put('children.json', json_encode([
        ['key' => 'parent', 'type' => 'string', 'rules' => [['conditions' => [false], 'rollout_variants' => [false]]]],
    ]));
    expect((new ImportManager)->importWithResult(new JsonFormatter, 'children.json'))->toBe(['success' => true, 'count' => 1])
        ->and(Setting::first()->rules->first()->conditions)->toHaveCount(0)
        ->and(Setting::first()->rules->first()->rolloutVariants)->toHaveCount(0);
});

test('a missing gzip payload cannot count phantom writes', function () {
    expect((new ImportManager)->importWithResult(new JsonFormatter, 'missing.gz'))->toBe(['success' => true, 'count' => 0])
        ->and(Setting::count())->toBe(0);
});

test('the protected import extension point rejects records without scalar keys', function () {
    $manager = new class extends ImportManager
    {
        public function importInvalidRecord(): void
        {
            $this->importSetting(['key' => []], 'upsert', 'skip');
        }
    };
    $manager->importInvalidRecord();
    expect(Setting::count())->toBe(0);
});

test('legacy protected overrides keep their signatures and conflict arguments', function () {
    $manager = new class extends ImportManager
    {
        public array $calls = [];

        protected function truncateTables(): void
        {
            $this->calls[] = 'truncate';
            parent::truncateTables();
        }

        protected function importRecord(array $data, string $mode, string $conflictHandling, ?string $connection): void
        {
            $this->calls[] = 'record';
            parent::importRecord($data, $mode, $conflictHandling, $connection);
        }

        protected function importSetting(array $data, string $mode, string $conflictHandling): void
        {
            $this->calls[] = $conflictHandling;
            parent::importSetting($data, $mode, $conflictHandling);
        }
    };
    Storage::put('legacy.json', '[{"key":"legacy","type":"string"}]');
    expect($manager->importWithResult(new JsonFormatter, 'legacy.json', ['truncate' => true, 'conflict_handling' => 'skip']))->toBe(['success' => true, 'count' => 1])
        ->and($manager->calls)->toBe(['truncate', 'record', 'skip']);
});

test('structured imports preserve groups on inserts and updates', function () {
    foreach ([new JsonFormatter, new YamlFormatter, new CsvFormatter] as $formatter) {
        Storage::put('groups.txt', $formatter->format([['key' => 'grouped', 'type' => 'string', 'group' => 'initial']]));
        (new ImportManager)->import($formatter, 'groups.txt', ['truncate' => true]);
        expect(Setting::firstOrFail()->group)->toBe('initial');
        Storage::put('groups.txt', $formatter->format([['key' => 'grouped', 'type' => 'string', 'group' => 'updated']]));
        (new ImportManager)->import($formatter, 'groups.txt');
        expect(Setting::firstOrFail()->group)->toBe('updated');
    }
});

test('nested imports restore the outer operation and its count', function () {
    Storage::put('outer.json', '[{"key":"outer-one","type":"string"},{"key":"outer-two","type":"string"}]');
    Storage::put('inner.json', '[{"key":"inner","type":"string"}]');
    $manager = new class extends ImportManager
    {
        public ?array $innerResult = null;

        protected function importRecord(array $data, string $mode, string $conflictHandling, ?string $connection): void
        {
            if ($data['key'] === 'outer-one') {
                $this->innerResult = $this->importWithResult(new JsonFormatter, 'inner.json');
            }
            parent::importRecord($data, $mode, $conflictHandling, $connection);
        }
    };
    expect($manager->importWithResult(new JsonFormatter, 'outer.json'))->toBe(['success' => true, 'count' => 2])
        ->and($manager->innerResult)->toBe(['success' => true, 'count' => 1])
        ->and(Setting::count())->toBe(3);
});

test('legacy setting overrides still receive record conflict handling', function () {
    $manager = new class extends ImportManager
    {
        protected function importSetting(array $data, string $mode, string $conflictHandling): void
        {
            throw new RuntimeException('custom setting failure');
        }
    };
    Storage::put('custom.json', '[{"key":"custom","type":"string"}]');
    expect($manager->importWithResult(new JsonFormatter, 'custom.json', ['conflict_handling' => 'skip']))->toBe(['success' => true, 'count' => 0])
        ->and(Setting::count())->toBe(0);
});
