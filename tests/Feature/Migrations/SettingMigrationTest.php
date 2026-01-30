<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Feature\Migrations;

use GaiaTools\FulcrumSettings\Database\Migrations\SettingDefinition;
use GaiaTools\FulcrumSettings\Database\Migrations\SettingMigration;
use GaiaTools\FulcrumSettings\Database\Migrations\SettingModifier;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

class SettingMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_create_a_setting()
    {
        $migration = new class extends SettingMigration
        {
            public function up(): void
            {
                $this->create('test.setting', function (SettingDefinition $setting) {
                    $setting->string()->default('foo')->description('bar');
                });
            }
        };

        $migration->up();

        $this->assertDatabaseHas('settings', [
            'key' => 'test.setting',
            'type' => 'string',
            'description' => 'bar',
        ]);

        $setting = Setting::withoutGlobalScopes()->where('key', 'test.setting')->first();
        $this->assertNotNull($setting);
        $this->assertEquals('foo', $setting->defaultValue->value);
    }

    public function test_it_can_modify_a_setting()
    {
        Setting::create(['key' => 'test.setting', 'type' => 'string']);

        $migration = new class extends SettingMigration
        {
            public function up(): void
            {
                $this->modify('test.setting', function (SettingModifier $setting) {
                    $setting->updateDescription('new description');
                });
            }
        };

        $migration->up();

        $this->assertDatabaseHas('settings', [
            'key' => 'test.setting',
            'description' => 'new description',
        ]);
    }

    public function test_it_can_delete_a_setting()
    {
        Setting::create(['key' => 'test.setting', 'type' => 'string']);

        $migration = new class extends SettingMigration
        {
            public function up(): void
            {
                $this->delete('test.setting');
            }
        };

        $migration->up();

        $this->assertDatabaseMissing('settings', ['key' => 'test.setting']);
    }

    public function test_it_can_use_fluent_api_to_create_modify_upsert_and_delete_settings()
    {
        $migration = new class extends SettingMigration
        {
            public function up(): void
            {
                // Create
                $this->createSetting('fluent.create')
                    ->string()
                    ->default('foo')
                    ->description('bar')
                    ->save();

                // Modify
                $this->modifySetting('fluent.create')
                    ->updateDescription('new description')
                    ->apply();

                // Upsert (existing)
                $this->upsertSetting('fluent.create')
                    ->updateDefault('baz')
                    ->save(); // save() is on SettingDefinition, but SettingModifier has apply()

                // Upsert (new)
                $this->upsertSetting('fluent.new')
                    ->boolean()
                    ->default(true)
                    ->save();
            }

            public function down(): void
            {
                $this->deleteSetting('fluent.create');
                $this->deleteSetting('fluent.new');
            }
        };

        $migration->up();

        $this->assertDatabaseHas('settings', [
            'key' => 'fluent.create',
            'type' => 'string',
            'description' => 'new description',
        ]);
        $setting = Setting::withoutGlobalScopes()->where('key', 'fluent.create')->first();
        $this->assertEquals('baz', $setting->defaultValue->value);

        $this->assertDatabaseHas('settings', [
            'key' => 'fluent.new',
            'type' => 'boolean',
        ]);

        $migration->down();

        $this->assertDatabaseMissing('settings', ['key' => 'fluent.create']);
        $this->assertDatabaseMissing('settings', ['key' => 'fluent.new']);
    }

    public function test_it_can_rename_a_setting()
    {
        Setting::create(['key' => 'old.key', 'type' => 'string']);

        $migration = new class extends SettingMigration
        {
            public function up(): void
            {
                $this->rename('old.key', 'new.key');
            }
        };

        $migration->up();

        $this->assertDatabaseMissing('settings', ['key' => 'old.key']);
        $this->assertDatabaseHas('settings', ['key' => 'new.key']);
    }

    public function test_it_exercises_setting_migration_helper_methods()
    {
        Carbon::setTestNow('2026-01-13 15:38:52');

        $migration = new class extends SettingMigration
        {
            public function up(): void {}

            public function down(): void {}

            public function test_helpers()
            {
                $this->create('test-setting-cov', function ($setting) {
                    $setting->string()->default('initial');
                });

                $this->updateDefault('test-setting-cov', 'new-val');
                $this->makeImmutable('test-setting-cov');
                $this->makeMutable('test-setting-cov');
                $this->makeMasked('test-setting-cov');
                $this->removeMasking('test-setting-cov');

                $this->addRule('test-setting-cov', function ($rule) {
                    $rule->name('test-rule')->then('val');
                });

                $this->updateRulePriority('test-setting-cov', 'test-rule', 100);
                $this->updateRuleValue('test-setting-cov', 'test-rule', 'new-rule-val');
            }
        };

        $migration->test_helpers();

        $setting = Setting::where('key', 'test-setting-cov')->first();
        $this->assertEquals('new-val', $setting->defaultValue->value);

        $rule = $setting->rules()->first();
        $this->assertEquals('test-rule', $rule->name);
        $this->assertEquals(100, $rule->priority);
        $this->assertEquals('new-rule-val', $rule->value->value);

        Carbon::setTestNow();
    }

    public function test_it_exercises_variant_helper_methods()
    {
        $migration = new class extends SettingMigration
        {
            public function up(): void {}

            public function down(): void {}

            public function test_variant_helpers()
            {
                $this->addRule('test-variant-setting', function ($rule) {
                    $rule->name('rollout-rule')->rollout(function ($rollout) {
                        $rollout->variant('v1', 50, 'val1')
                            ->variant('v2', 50, 'val2');
                    });
                });

                $this->updateVariantWeight('test-variant-setting', 'rollout-rule', 'v1', 30);
                $this->updateVariantValue('test-variant-setting', 'rollout-rule', 'v2', 'new-val2');
                $this->deleteVariant('test-variant-setting', 'rollout-rule', 'v1');
            }
        };

        Setting::create(['key' => 'test-variant-setting', 'type' => 'string']);

        $migration->test_variant_helpers();

        $rule = Setting::where('key', 'test-variant-setting')->first()->rules()->where('name', 'rollout-rule')->first();
        $this->assertCount(1, $rule->rolloutVariants);
        $this->assertEquals('v2', $rule->rolloutVariants->first()->name);
        $this->assertEquals('new-val2', $rule->rolloutVariants->first()->value->value);
    }
}
