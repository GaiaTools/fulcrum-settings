<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Feature\Migrations;

use GaiaTools\FulcrumSettings\Database\Migrations\SettingDefinition;
use GaiaTools\FulcrumSettings\Database\Migrations\SettingMigration;
use GaiaTools\FulcrumSettings\Enums\ComparisonOperator;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class SettingMigrationExtendedTest extends TestCase
{
    use RefreshDatabase;

    private function getMigration(): SettingMigration
    {
        return new class extends SettingMigration
        {
            // Empty migration for testing protected methods
            public function call(string $method, ...$args)
            {
                return $this->$method(...$args);
            }
        };
    }

    public function test_create_if_not_exists()
    {
        $migration = $this->getMigration();

        // Should create
        $migration->call('createIfNotExists', 'test.setting', function (SettingDefinition $setting) {
            $setting->string()->default('foo');
        });
        $this->assertDatabaseHas('settings', ['key' => 'test.setting']);

        // Should NOT create again
        $result = $migration->call('createIfNotExists', 'test.setting', function (SettingDefinition $setting) {
            $setting->string()->default('bar');
        });
        $this->assertNull($result);
        $setting = Setting::withoutGlobalScopes()->where('key', 'test.setting')->first();
        $this->assertEquals('foo', $setting->defaultValue->value);
    }

    public function test_upsert()
    {
        $migration = $this->getMigration();

        // Upsert (create)
        $migration->call('upsert', 'test.setting', function ($s) {
            $s instanceof SettingDefinition ? $s->string()->default('foo') : $s->updateDescription('desc');
        });
        $this->assertDatabaseHas('settings', ['key' => 'test.setting']);

        // Upsert (modify)
        $migration->call('upsert', 'test.setting', function ($s) {
            if ($s instanceof SettingDefinition) {
                $s->string()->default('bar');
            } else {
                $s->updateDescription('new desc');
            }
        });
        $this->assertDatabaseHas('settings', ['key' => 'test.setting', 'description' => 'new desc']);
    }

    public function test_delete_if_exists()
    {
        Setting::create(['key' => 'test.setting', 'type' => 'string']);
        $migration = $this->getMigration();

        $migration->call('deleteIfExists', 'test.setting');
        $this->assertDatabaseMissing('settings', ['key' => 'test.setting']);

        // Should not throw when doesn't exist
        $migration->call('deleteIfExists', 'test.setting');
    }

    public function test_property_updates()
    {
        $migration = $this->getMigration();
        $migration->call('create', 'test.setting', function (SettingDefinition $setting) {
            $setting->string()->default('old_val')->description('old');
        });

        $migration->call('updateDefault', 'test.setting', 'new_val');
        $setting = Setting::withoutGlobalScopes()->where('key', 'test.setting')->first();
        $this->assertEquals('new_val', $setting->defaultValue->value);

        $migration->call('updateDescription', 'test.setting', 'new_desc');
        $migration->call('updateType', 'test.setting', 'boolean');
        $migration->call('modify', 'test.setting', function ($s) {
            $s->updateDefault(123)->updateType('integer');
        });
        $migration->call('makeImmutable', 'test.setting');

        $setting = $setting->fresh();
        $this->assertEquals('new_desc', $setting->description);
        $this->assertEquals('integer', $setting->type->value);
        $this->assertTrue($setting->immutable);

        $migration->call('makeMutable', 'test.setting');
        $this->assertFalse($setting->fresh()->immutable);

        $migration->call('makeMasked', 'test.setting');
        $this->assertTrue($setting->fresh()->masked);

        $migration->call('removeMasking', 'test.setting');
        $this->assertFalse($setting->fresh()->masked);
    }

    public function test_rule_operations()
    {
        $setting = Setting::create(['key' => 'test.setting', 'type' => 'string']);
        $migration = $this->getMigration();

        $migration->call('addSimpleRule', 'test.setting', 'r1', 'v1', 10);
        $this->assertTrue($migration->call('ruleExists', 'test.setting', 'r1'));

        $migration->call('addConditionalRule', 'test.setting', 'r2', 'v2', 'attr', 'equals', 'val', 20);
        $this->assertTrue($migration->call('ruleExists', 'test.setting', 'r2'));

        $migration->call('renameRule', 'test.setting', 'r1', 'r1_new');
        $this->assertFalse($migration->call('ruleExists', 'test.setting', 'r1'));
        $this->assertTrue($migration->call('ruleExists', 'test.setting', 'r1_new'));

        $migration->call('updateRulePriority', 'test.setting', 'r1_new', 100);
        $migration->call('updateRuleValue', 'test.setting', 'r1_new', 'v1_new');

        $rule = $setting->rules()->where('name', 'r1_new')->first();
        $this->assertEquals(100, $rule->priority);
        $this->assertEquals('v1_new', $rule->value->value);

        $migration->call('deleteRuleIfExists', 'test.setting', 'r2');
        $this->assertFalse($migration->call('ruleExists', 'test.setting', 'r2'));

        $migration->call('clearRules', 'test.setting');
        $this->assertEquals(0, $setting->rules()->count());
    }

    public function test_condition_operations()
    {
        $setting = Setting::create(['key' => 'test.setting', 'type' => 'string']);
        $migration = $this->getMigration();
        $migration->call('addSimpleRule', 'test.setting', 'r1', 'v1');

        $migration->call('addCondition', 'test.setting', 'r1', 'attr1', 'equals', 'val1');
        $rule = $setting->rules()->where('name', 'r1')->first();
        $this->assertCount(1, $rule->conditions);

        $migration->call('updateConditionValue', 'test.setting', 'r1', 'attr1', 'val2');
        $migration->call('updateConditionOperator', 'test.setting', 'r1', 'attr1', 'not_equals');

        $cond = $rule->conditions()->where('attribute', 'attr1')->first();
        $this->assertEquals('val2', $cond->value);
        $this->assertEquals('not_equals', $cond->operator->value);

        $migration->call('deleteCondition', 'test.setting', 'r1', 'attr1');
        $this->assertCount(0, $rule->fresh()->conditions);

        $migration->call('addCondition', 'test.setting', 'r1', 'attr2', 'is_true');
        $migration->call('clearConditions', 'test.setting', 'r1');
        $this->assertCount(0, $rule->fresh()->conditions);
    }

    public function test_rollout_operations()
    {
        $setting = Setting::create(['key' => 'test.setting', 'type' => 'string']);
        $migration = $this->getMigration();

        $migration->call('addABTest', 'test.setting', 'ab', 'v1', 'v2', 10);
        $rule = $setting->rules()->where('name', 'ab')->first();
        $this->assertCount(2, $rule->rolloutVariants);

        $migration->call('addGradualRollout', 'test.setting', 'gradual', 'v3', 10.0, 20);
        $rule2 = $setting->rules()->where('name', 'gradual')->first();
        $this->assertCount(1, $rule2->rolloutVariants);

        $migration->call('addVariant', 'test.setting', 'ab', 'v3_name', 33.3, 'v3_val');
        $this->assertCount(3, $rule->fresh()->rolloutVariants);

        $migration->call('updateVariantWeight', 'test.setting', 'ab', 'v3_name', 40.0);
        $migration->call('updateVariantValue', 'test.setting', 'ab', 'v3_name', 'v3_new');

        $v = $rule->rolloutVariants()->where('name', 'v3_name')->first();
        $this->assertEquals(40000, $v->weight);
        $this->assertEquals('v3_new', $v->value->value);

        $migration->call('deleteVariant', 'test.setting', 'ab', 'v3_name');
        $this->assertCount(2, $rule->fresh()->rolloutVariants);

        $oldSalt = $rule->rollout_salt;
        $migration->call('regenerateSalt', 'test.setting', 'ab');
        $this->assertNotEquals($oldSalt, $rule->fresh()->rollout_salt);
    }

    public function test_bulk_and_raw_operations()
    {
        Setting::create(['key' => 's1', 'type' => 'string']);
        Setting::create(['key' => 's2', 'type' => 'string']);
        $migration = $this->getMigration();

        $migration->call('deleteMany', ['s1', 's2']);
        $this->assertDatabaseMissing('settings', ['key' => 's1']);
        $this->assertDatabaseMissing('settings', ['key' => 's2']);

        $migration->call('raw', function () {
            DB::table('settings')->insert(['key' => 'raw_setting', 'type' => 'string', 'created_at' => now(), 'updated_at' => now()]);
        });
        $this->assertDatabaseHas('settings', ['key' => 'raw_setting']);
    }

    public function test_scheduled_rules()
    {
        Setting::create(['key' => 'test.setting', 'type' => 'string']);
        $migration = $this->getMigration();

        $migration->call('addScheduledRule', 'test.setting', 'sched', 'val', '2025-01-01', '2025-12-31');
        $rule = Setting::withoutGlobalScopes()->where('key', 'test.setting')->first()->rules()->where('name', 'sched')->first();
        $this->assertNotNull($rule->starts_at);
        $this->assertNotNull($rule->ends_at);

        $migration->call('updateRuleTimeBounds', 'test.setting', 'sched', '2026-01-01', '2026-12-31');
        $this->assertEquals('2026-01-01 00:00:00', $rule->fresh()->starts_at->toDateTimeString());

        $migration->call('removeRuleTimeBounds', 'test.setting', 'sched');
        $this->assertNull($rule->fresh()->starts_at);
        $this->assertNull($rule->fresh()->ends_at);
    }

    public function test_getters()
    {
        $migration = $this->getMigration();
        $migration->call('create', 'test.setting', function (SettingDefinition $setting) {
            $setting->string()->default('val');
        });

        $migration->call('addSimpleRule', 'test.setting', 'r1', 'v1');

        $this->assertEquals('test.setting', $migration->call('get', 'test.setting')->key);
        $this->assertNotNull($migration->call('getSettingValue', 'test.setting'));
        $this->assertNotNull($migration->call('getRuleValue', 'test.setting', 'r1'));
    }

    public function test_handles_missing_elements_gracefully()
    {
        $migration = $this->getMigration();

        // These should not throw
        $migration->call('delete', 'non_existent');
        $migration->call('deleteRule', 'non_existent', 'r1');
        $migration->call('deleteRule', 'test.setting', 'non_existent');
        $migration->call('deleteCondition', 'non_existent', 'r1', 'attr');
        $migration->call('deleteVariant', 'non_existent', 'r1', 'v1');
        $migration->call('ruleExists', 'non_existent', 'r1');
        $migration->call('getSettingValue', 'non_existent');
        $migration->call('getRuleValue', 'non_existent', 'r1');

        $this->assertTrue(true);
    }

    public function test_modify_throws_if_not_exists()
    {
        $migration = $this->getMigration();
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $migration->call('modify', 'non_existent', function () {});
    }

    public function test_rename_throws_if_not_exists()
    {
        $migration = $this->getMigration();
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $migration->call('rename', 'non_existent', 'new_key');
    }

    public function test_it_handles_comparison_operator_enum_in_add_condition()
    {
        $setting = Setting::create(['key' => 'test.setting', 'type' => 'string']);
        $migration = $this->getMigration();
        $migration->call('addSimpleRule', 'test.setting', 'r1', 'v1');

        $migration->call('addCondition', 'test.setting', 'r1', 'attr', ComparisonOperator::EQUALS, 'val');

        $rule = $setting->rules()->where('name', 'r1')->first();
        $this->assertEquals('equals', $rule->conditions[0]->operator->value);
    }

    public function test_add_variant_generates_salt_if_missing()
    {
        $setting = Setting::create(['key' => 'test.setting', 'type' => 'string']);
        $migration = $this->getMigration();
        $migration->call('addSimpleRule', 'test.setting', 'r1', 'v1');

        $rule = $setting->rules()->where('name', 'r1')->first();
        $this->assertNull($rule->rollout_salt);

        $migration->call('addVariant', 'test.setting', 'r1', 'v1_name', 50.0, 'v1_val');

        $this->assertNotNull($rule->fresh()->rollout_salt);
    }
}
