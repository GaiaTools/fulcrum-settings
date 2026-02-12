<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = config()->array('fulcrum.table_names', []);
        $settingsTable = $tables['settings'] ?? 'settings';

        Schema::table($settingsTable, function (Blueprint $table) {
            $table->string('group')->nullable()->after('key')->index();
            $table->index(['group', 'tenant_id']);
        });

        DB::table($settingsTable)
            ->select(['id', 'key'])
            ->orderBy('id')
            ->chunkById(1000, function ($rows) use ($settingsTable) {
                foreach ($rows as $row) {
                    $group = null;
                    $key = is_string($row->key) ? $row->key : '';
                    $position = strrpos($key, '.');

                    if ($position !== false) {
                        $group = substr($key, 0, $position);
                    }

                    DB::table($settingsTable)
                        ->where('id', $row->id)
                        ->update(['group' => $group]);
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

        Schema::table($settingsTable, function (Blueprint $table) {
            $table->dropIndex(['group', 'tenant_id']);
            $table->dropIndex(['group']);
            $table->dropColumn('group');
        });
    }
};
