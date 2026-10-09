<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Feature\Support\DataPortability;

use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Models\SettingValue;
use GaiaTools\FulcrumSettings\Support\DataPortability\ExportManager;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\JsonFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\SqlFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\ImportManager;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use GaiaTools\FulcrumSettings\Tests\TestCase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SqlPortabilityTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        foreach (array_keys($app['config']->get('fulcrum.table_names')) as $name) {
            $app['config']->set('fulcrum.table_names.'.$name, 'custom_'.$name);
        }
        $app['config']->set('database.connections.testing.prefix', 'pkg_');
    }

    public function test_sql_round_trip_uses_configured_tables_prefixes_and_valid_relationships(): void
    {
        FulcrumContext::setTenantId('0');
        $key = "quote'\\path\n--comment";
        $value = "RAW:SELECT 'quoted'\\path";
        $setting = Setting::create(['key' => $key, 'type' => 'string', 'tenant_id' => '0']);
        $setting->defaultValue()->create(['tenant_id' => '0', 'value' => $value]);
        $rule = $setting->rules()->create(['tenant_id' => '0', 'name' => "rule's", 'priority' => 1]);
        $rule->value()->create(['tenant_id' => '0', 'value' => 'rule value']);
        $rule->conditions()->create(['tenant_id' => '0', 'type' => 'user_agent', 'attribute' => 'browser', 'operator' => 'equals', 'value' => "Chrome's"]);
        $variant = $rule->rolloutVariants()->create(['tenant_id' => '0', 'name' => "variant's", 'weight' => 100]);
        $variant->value()->create(['tenant_id' => '0', 'value' => 'variant value']);
        Storage::fake('local');
        $path = (new ExportManager)->export(new SqlFormatter, ['filename' => 'roundtrip.sql']);
        $sql = file_get_contents($path);
        $this->assertStringNotContainsString('FOREIGN_KEY_CHECKS', $sql);
        foreach (config('fulcrum.table_names') as $table) {
            $this->assertStringContainsString('"pkg_'.$table.'"', $sql);
        }
        $result = (new ImportManager)->importWithResult(new SqlFormatter, $path, ['truncate' => true]);
        $this->assertSame(['success' => true, 'count' => 1], $result);
        $restored = Setting::where('key', $key)->firstOrFail();
        $this->assertSame($value, $restored->defaultValue->value);
        $this->assertSame('rule value', $restored->rules->first()->value->value);
        $this->assertSame('user_agent', $restored->rules->first()->conditions->first()->type);
        $this->assertSame("Chrome's", $restored->rules->first()->conditions->first()->value);
        $this->assertSame('variant value', $restored->rules->first()->rolloutVariants->first()->value->value);
        $this->assertSame('0', $restored->tenant_id);
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
    }

    public function test_custom_table_migration_rolls_back_in_dependency_order(): void
    {
        $migration = require __DIR__.'/../../../../database/migrations/2025_12_04_000001_create_settings_table.php';
        $migration->down();
        foreach (config('fulcrum.table_names') as $table) {
            $this->assertFalse(DB::connection()->getSchemaBuilder()->hasTable($table));
        }
    }

    public function test_sql_round_trip_preserves_null_values_for_all_owner_types(): void
    {
        foreach ([false, true] as $masked) {
            $setting = Setting::create(['key' => 'null-'.(int) $masked, 'type' => 'string', 'masked' => $masked]);
            $rule = $setting->rules()->create(['priority' => 1]);
            $variant = $rule->rolloutVariants()->create(['name' => 'nullable', 'weight' => 100]);
            foreach ([$setting, $rule, $variant] as $owner) {
                DB::table((new SettingValue)->getTable())->insert([
                    'valuable_type' => $owner->getMorphClass(), 'valuable_id' => $owner->getKey(), 'value' => null,
                ]);
            }
        }
        Storage::fake('local');
        $path = (new ExportManager)->export(new SqlFormatter, ['filename' => 'nulls.sql', 'decrypt' => true]);
        (new ImportManager)->import(new SqlFormatter, $path, ['truncate' => true]);
        $this->assertSame(array_fill(0, 6, null), DB::table((new SettingValue)->getTable())->pluck('value')->all());
        foreach (Setting::all() as $setting) {
            $this->assertNull($setting->defaultValue->value);
            $this->assertNull($setting->rules->first()->value->value);
            $this->assertNull($setting->rules->first()->rolloutVariants->first()->value->value);
        }
    }

    public function test_alternate_connection_values_use_their_own_setting_type_and_encryption(): void
    {
        Storage::fake('local');
        foreach ([false, true] as $collision) {
            $name = 'alternate_'.(int) $collision;
            config(['database.connections.'.$name => config('database.connections.testing')]);
            $database = DB::connection($name);
            $originalSchema = Schema::getFacadeRoot();
            Schema::swap($database->getSchemaBuilder());
            try {
                foreach (glob(__DIR__.'/../../../../database/migrations/*.php') as $migration) {
                    (require $migration)->up();
                }
            } finally {
                Schema::swap($originalSchema);
            }
            if ($collision) {
                Setting::create(['key' => 'wrong-owner', 'type' => 'integer', 'masked' => false]);
            }
            $data = [['key' => 'masked', 'type' => 'string', 'masked' => true, 'default_value' => 'secret', 'rules' => [[
                'priority' => 1, 'value' => 'rule secret',
                'rollout_variants' => [['name' => 'secret variant', 'weight' => 100, 'value' => 'variant secret']],
            ]]], ['key' => 'typed', 'type' => 'json', 'default_value' => ['enabled' => true]]];
            Storage::put('alternate.json', json_encode($data));
            $this->assertSame(['success' => true, 'count' => 2], (new ImportManager)->importWithResult(new JsonFormatter, 'alternate.json', ['connection' => $name]));
            $typed = Setting::on($name)->where('key', 'typed')->firstOrFail();
            $this->assertSame(['enabled' => true], $typed->defaultValue->value);
            $this->assertSame(['enabled' => true], json_decode($typed->defaultValue->getRawOriginal('value'), true));
            $restored = Setting::on($name)->firstOrFail();
            $this->assertSame('secret', $restored->defaultValue->value);
            $this->assertSame('rule secret', $restored->rules->first()->value->value);
            $this->assertSame('variant secret', $restored->rules->first()->rolloutVariants->first()->value->value);
            foreach ($database->table((new SettingValue)->getTable())->where('valuable_id', $restored->id)->pluck('value') as $stored) {
                $this->assertContains(Crypt::decryptString($stored), ['secret', 'rule secret', 'variant secret']);
            }
        }
        $this->assertSame('wrong-owner', Setting::firstOrFail()->key);
        $this->assertSame(0, DB::table((new SettingValue)->getTable())->count());
    }
}
