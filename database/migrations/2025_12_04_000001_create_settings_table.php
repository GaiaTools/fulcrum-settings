<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Enums\ConditionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = config()->array('fulcrum.table_names', []);
        $settingsTable = $tables['settings'] ?? 'settings';

        // Check if settings table already exists and might be from Spatie
        if (Schema::hasTable($settingsTable)) {
            if (Schema::hasColumn($settingsTable, 'group') && Schema::hasColumn($settingsTable, 'payload')) {
                Schema::rename($settingsTable, 'spatie_settings');
            }
        }

        Schema::create($settingsTable, function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('type');
            $table->text('description')->nullable();
            $table->boolean('masked')->default(false);
            $table->boolean('immutable')->default(false);
            $table->timestamps();
        });

        Schema::create($tables['setting_rules'], function (Blueprint $table) {
            $table->id();
            $table->foreignId('setting_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->integer('priority')->default(0);
            $table->string('rollout_salt', 32)->nullable();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->timestamps();

            $table->index(['setting_id', 'priority']);
        });

        Schema::create($tables['setting_rule_conditions'], function (Blueprint $table) {
            $table->id();
            $table->foreignId('setting_rule_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default(ConditionType::default());
            $table->string('attribute');
            $table->string('operator');
            $table->json('value')->nullable();
            $table->timestamps();

            $table->index('setting_rule_id');
        });

        Schema::create($tables['setting_rule_rollout_variants'], function (Blueprint $table) use ($tables) {
            $table->id();
            $table->foreignId('setting_rule_id')
                ->constrained($tables['setting_rules'])
                ->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('weight'); // 0-100000 for 0.000% - 100.000%
            $table->timestamps();

            $table->unique(['setting_rule_id', 'name']);
            $table->index('setting_rule_id');
        });

        Schema::create($tables['setting_values'], function (Blueprint $table) {
            $table->id();
            $table->morphs('valuable');
            // store (optionally encrypted) JSON as text for portability across drivers
            $table->longText('value')->nullable();
            $table->timestamps();

            $table->unique(['valuable_type', 'valuable_id']);
        });
    }

    public function down(): void
    {
        $tables = config()->array('fulcrum.table_names', []);

        Schema::dropIfExists($tables['setting_rule_conditions'] ?? 'setting_rule_conditions');
        Schema::dropIfExists($tables['setting_values'] ?? 'setting_values');
        Schema::dropIfExists($tables['setting_rules'] ?? 'setting_rules');
        Schema::dropIfExists($tables['settings'] ?? 'settings');
        Schema::dropIfExists($tables['setting_rule_rollout_variants'] ?? 'setting_rule_rollout_variants');
    }
};
