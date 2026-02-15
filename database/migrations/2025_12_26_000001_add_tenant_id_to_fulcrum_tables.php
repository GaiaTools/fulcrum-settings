<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = config()->array('fulcrum.table_names', []);

        $settingsTable = $tables['settings'] ?? 'settings';
        $rulesTable = $tables['setting_rules'] ?? 'setting_rules';
        $conditionsTable = $tables['setting_rule_conditions'] ?? 'setting_rule_conditions';
        $valuesTable = $tables['setting_values'] ?? 'setting_values';
        $variantsTable = $tables['setting_rule_rollout_variants'] ?? 'setting_rule_rollout_variants';

        Schema::table($settingsTable, function (Blueprint $table) use ($settingsTable) {
            $table->string('tenant_id')->nullable()->after('id')->index();

            // Re-create the unique index to include tenant_id
            $table->dropUnique($settingsTable.'_key_unique');
            $table->unique(['group', 'key', 'tenant_id']);
            $table->index(['group', 'key', 'tenant_id']);
        });

        Schema::table($rulesTable, function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->after('id')->index();
        });

        Schema::table($conditionsTable, function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->after('id')->index();
        });

        Schema::table($valuesTable, function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->after('id')->index();
        });

        Schema::table($variantsTable, function (Blueprint $table) use ($variantsTable) {
            if (! Schema::hasColumn($variantsTable, 'tenant_id')) {
                $table->string('tenant_id')->nullable()->after('id')->index();
            }
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        $tables = config()->array('fulcrum.table_names', []);

        $settingsTable = $tables['settings'] ?? 'settings';
        $rulesTable = $tables['setting_rules'] ?? 'setting_rules';
        $conditionsTable = $tables['setting_rule_conditions'] ?? 'setting_rule_conditions';
        $valuesTable = $tables['setting_values'] ?? 'setting_values';
        $variantsTable = $tables['setting_rule_rollout_variants'] ?? 'setting_rule_rollout_variants';
        $hasGroup = Schema::hasColumn($settingsTable, 'group');

        Schema::table($valuesTable, function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });

        Schema::table($conditionsTable, function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });

        Schema::table($rulesTable, function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });

        Schema::table($settingsTable, function (Blueprint $table) use ($hasGroup) {
            if ($hasGroup) {
                $table->dropIndex(['group', 'tenant_id']);
            }
            $table->dropUnique(['key', 'tenant_id']);
            $table->unique('key');
            $table->dropColumn('tenant_id');
        });

        Schema::table($variantsTable, function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });
    }
};
