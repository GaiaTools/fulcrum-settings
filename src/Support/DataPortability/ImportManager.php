<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\DataPortability;

use GaiaTools\FulcrumSettings\Enums\ConditionType;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Models\SettingRule;
use GaiaTools\FulcrumSettings\Models\SettingRuleCondition;
use GaiaTools\FulcrumSettings\Models\SettingRuleRolloutVariant;
use GaiaTools\FulcrumSettings\Models\SettingValue;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\Formatter;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImportManager
{
    /**
     * @param array{
     *     connection?: string,
     *     mode?: 'insert'|'upsert',
     *     truncate?: bool,
     *     conflict_handling?: 'fail'|'skip'|'log',
     *     dry_run?: bool,
     *     chunk_size?: int
     * } $options
     */
    public function import(Formatter $formatter, string $path, array $options = []): bool
    {
        $connection = $options['connection'] ?? config('database.default');
        if (! is_string($connection)) {
            $connection = null;
        }
        $mode = $options['mode'] ?? 'upsert';
        $truncate = $options['truncate'] ?? false;
        $conflictHandling = $options['conflict_handling'] ?? 'fail';
        $dryRun = $options['dry_run'] ?? false;
        $chunkSize = (int) ($options['chunk_size'] ?? 1000);
        $chunkSize = max(1, $chunkSize);

        $content = $this->getContent($path);
        $data = $formatter->parse($content);

        if ($dryRun) {
            return true;
        }

        return DB::connection($connection)->transaction(function () use ($connection, $data, $mode, $truncate, $conflictHandling, $chunkSize) {
            if ($truncate) {
                $this->truncateTables();
            }

            $chunks = array_chunk($data, $chunkSize);
            foreach ($chunks as $chunk) {
                foreach ($chunk as $settingData) {
                    try {
                        if (isset($settingData['__raw_sql'])) {
                            $rawSql = $settingData['__raw_sql'];
                            if (is_string($rawSql)) {
                                DB::connection($connection)->unprepared($rawSql);
                            }

                            continue;
                        }
                        $this->importSetting($settingData, $mode, $conflictHandling);
                    } catch (\Throwable $e) {
                        if ($conflictHandling === 'fail') {
                            throw $e;
                        }
                        if ($conflictHandling === 'log') {
                            $keyLabel = $settingData['key'] ?? 'unknown';
                            $keyLabel = is_scalar($keyLabel) ? (string) $keyLabel : 'unknown';
                            Log::error('Import failed for setting: '.$keyLabel.'. Error: '.$e->getMessage());
                        }
                        // if skip, just continue
                    }
                }
            }

            return true;
        });
    }

    protected function getContent(string $path): string
    {
        if (str_ends_with($path, '.gz')) {
            $content = @file_get_contents($path);
            if ($content === false) {
                // Try Storage
                $content = Storage::disk('local')->get($path);
            }

            if (! is_string($content)) {
                return '';
            }

            $decoded = @gzdecode($content);

            return is_string($decoded) ? $decoded : '';
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            return Storage::disk('local')->get($path) ?: '';
        }

        return $content;
    }

    protected function truncateTables(): void
    {
        FulcrumContext::force(true);
        try {
            SettingValue::query()->delete();
            SettingRuleCondition::query()->delete();
            SettingRuleRolloutVariant::query()->delete();
            SettingRule::query()->delete();
            Setting::query()->delete();
        } finally {
            FulcrumContext::force(false);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function importSetting(array $data, string $mode, string $conflictHandling): void
    {
        $keyValue = $data['key'] ?? null;
        if (! is_scalar($keyValue)) {
            return;
        }
        $key = (string) $keyValue;
        $tenantId = $data['tenant_id'] ?? null;

        $setting = Setting::where('key', $key)->where('tenant_id', $tenantId)->first();

        if ($setting && $mode === 'insert') {
            throw new \Exception("Setting already exists: {$key}");
        }

        FulcrumContext::force(true);
        try {
            if (! $setting) {
                $setting = Setting::create([
                    'key' => $data['key'],
                    'tenant_id' => $data['tenant_id'] ?? null,
                    'type' => $data['type'],
                    'description' => $data['description'] ?? null,
                    'masked' => $data['masked'] ?? false,
                    'immutable' => $data['immutable'] ?? false,
                ]);
            } else {
                $setting->update([
                    'type' => $data['type'],
                    'description' => $data['description'] ?? null,
                    'masked' => $data['masked'] ?? false,
                    'immutable' => $data['immutable'] ?? false,
                ]);
            }

            if (isset($data['default_value'])) {
                $setting->defaultValue()->updateOrCreate([
                    'valuable_type' => $setting->getMorphClass(),
                    'valuable_id' => $setting->getKey(),
                ], [
                    'tenant_id' => $setting->tenant_id,
                    'value' => $data['default_value'],
                ]);
            }

            if (isset($data['rules']) && is_array($data['rules'])) {
                // For simplicity in upsert, we might want to clear existing rules or match them.
                // Given the complexity of rules, clearing and re-creating might be safer if we want to match the export exactly.
                $setting->rules()->each(fn ($rule) => $rule->delete());
                foreach ($data['rules'] as $ruleData) {
                    if (is_array($ruleData)) {
                        /** @var array<string, mixed> $ruleData */
                        $this->importRule($setting, $ruleData);
                    }
                }
            }
        } finally {
            FulcrumContext::force(false);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function importRule(Setting $setting, array $data): void
    {
        $rule = $setting->rules()->create([
            'tenant_id' => $data['tenant_id'] ?? $setting->tenant_id,
            'name' => $data['name'] ?? null,
            'priority' => $data['priority'] ?? 0,
            'rollout_salt' => $data['rollout_salt'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
        ]);

        if (isset($data['value'])) {
            $rule->value()->updateOrCreate([
                'valuable_type' => $rule->getMorphClass(),
                'valuable_id' => $rule->getKey(),
            ], [
                'tenant_id' => $rule->tenant_id,
                'value' => $data['value'],
            ]);
        }

        if (isset($data['conditions']) && is_array($data['conditions'])) {
            foreach ($data['conditions'] as $conditionData) {
                if (! is_array($conditionData)) {
                    continue;
                }
                $rule->conditions()->create([
                    'tenant_id' => $conditionData['tenant_id'] ?? $rule->tenant_id,
                    'type' => $conditionData['type'] ?? ConditionType::default(),
                    'attribute' => $conditionData['attribute'],
                    'operator' => $conditionData['operator'],
                    'value' => $conditionData['value'],
                ]);
            }
        }

        if (isset($data['rollout_variants']) && is_array($data['rollout_variants'])) {
            foreach ($data['rollout_variants'] as $variantData) {
                if (! is_array($variantData)) {
                    continue;
                }
                $variant = $rule->rolloutVariants()->create([
                    'tenant_id' => $variantData['tenant_id'] ?? $rule->tenant_id,
                    'name' => $variantData['name'],
                    'weight' => $variantData['weight'],
                ]);

                if (isset($variantData['value'])) {
                    $variant->value()->updateOrCreate([
                        'valuable_type' => $variant->getMorphClass(),
                        'valuable_id' => $variant->getKey(),
                    ], [
                        'tenant_id' => $variant->tenant_id,
                        'value' => $variantData['value'],
                    ]);
                }
            }
        }
    }
}
