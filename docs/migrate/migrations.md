---
title: Version Migrations
description: Upgrade guide for Fulcrum Settings schema changes between versions
---

# Version Migrations

## v0.1.x → v0.2.x

In v0.2.x the `group` column is part of the initial settings table migration. Existing installs on v0.1.x need to add the column manually.

### Add the `group` column

Create a new Laravel migration in your app and add the column + indexes (no backfill is performed).

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = config()->array('fulcrum.table_names', []);
        $settingsTable = $tables['settings'] ?? 'settings';

        Schema::table($settingsTable, function (Blueprint $table) {
            $table->string('group')->nullable()->after('key')->index();
            $table->unique(['group', 'key', 'tenant_id']);
            $table->index(['group', 'key', 'tenant_id']);
        });
    }
};
```

If you are not using multi-tenancy, you can omit the composite index.

Then split the settings key into the group and key parts and update the settings table.
```php
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
```
