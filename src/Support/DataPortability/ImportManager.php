<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\DataPortability;

use GaiaTools\FulcrumSettings\Enums\ConditionType;
use GaiaTools\FulcrumSettings\Exceptions\DuplicateSettingException;
use GaiaTools\FulcrumSettings\Exceptions\InvalidImportDataException;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Models\SettingRule;
use GaiaTools\FulcrumSettings\Models\SettingRuleCondition;
use GaiaTools\FulcrumSettings\Models\SettingRuleRolloutVariant;
use GaiaTools\FulcrumSettings\Models\SettingValue;
use GaiaTools\FulcrumSettings\Support\Cache\CacheInvalidator;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\Formatter;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImportManager
{
    private ?ImportOperation $operation = null;

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
        return $this->importWithResult($formatter, $path, $options)['success'];
    }

    /**
     * @param array{
     *     connection?: string,
     *     mode?: 'insert'|'upsert',
     *     truncate?: bool,
     *     conflict_handling?: 'fail'|'skip'|'log',
     *     dry_run?: bool,
     *     chunk_size?: int
     * } $options
     * @return array{success: bool, count: int}
     */
    public function importWithResult(Formatter $formatter, string $path, array $options = []): array
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
            return ['success' => $this->validateData($data, $conflictHandling), 'count' => 0];
        }

        $previousOperation = $this->operation;
        $operation = new ImportOperation($connection);
        $this->operation = $operation;
        try {
            return DB::connection($connection)->transaction(function () use ($connection, $data, $mode, $truncate, $conflictHandling, $chunkSize, $operation) {
                if ($truncate) {
                    $this->truncateTables();
                }

                $chunks = array_chunk($data, $chunkSize);
                foreach ($chunks as $chunk) {
                    foreach ($chunk as $settingData) {
                        $this->importRecord($settingData, $mode, $conflictHandling, $connection);
                    }
                }

                if (config()->boolean('fulcrum.cache.enabled', false)) {
                    CacheInvalidator::configured()->invalidateAfterCommit(DB::connection($connection));
                }

                return ['success' => true, 'count' => $operation->count];
            });
        } finally {
            $this->operation = $previousOperation;
        }
    }

    /** @param array<string, mixed> $settingData */
    protected function importRecord(array $settingData, string $mode, string $conflictHandling, ?string $connection): void
    {
        try {
            if (isset($settingData['__raw_sql'])) {
                $this->importSafely(fn (): int => $this->importSql($settingData['__raw_sql'], $connection), $conflictHandling, $settingData['key'] ?? 'unknown', $connection);
            } elseif (is_scalar($settingData['key'] ?? null) && (string) $settingData['key'] !== '') {
                $this->importSetting($settingData, $mode, $conflictHandling);
            }
        } catch (\Throwable $exception) {
            $this->handleImportFailure($exception, $conflictHandling, $settingData['key'] ?? 'unknown');
        }
    }

    /** @param \Closure(): int $import */
    private function importSafely(\Closure $import, string $conflictHandling, mixed $key, ?string $connection): void
    {
        try {
            // Fail-fast imports roll back the outer transaction; skip/log modes
            // need a savepoint to discard an individual record's partial writes.
            $count = $conflictHandling === 'fail'
                ? $import()
                : DB::connection($connection)->transaction($import);
            if ($this->operation !== null) {
                $this->operation->count += $count;
            }
        } catch (\Throwable $e) {
            $this->handleImportFailure($e, $conflictHandling, $key);
        }
    }

    private function handleImportFailure(\Throwable $exception, string $conflictHandling, mixed $key): void
    {
        if ($conflictHandling === 'fail') {
            throw $exception;
        }
        if ($conflictHandling === 'log') {
            $keyLabel = is_scalar($key) ? (string) $key : 'unknown';
            Log::error('Import failed for setting: '.$keyLabel.'. Error: '.$exception->getMessage());
        }
    }

    protected function importSql(mixed $sql, ?string $connection): int
    {
        if (! is_string($sql)) {
            return 0;
        }
        $database = DB::connection($connection);
        $table = (new Setting)->getTable();
        $before = $database->table($table)->count();
        $database->unprepared($sql);

        return max(0, $database->table($table)->count() - $before);
    }

    /**
     * @param  array<int, mixed>  $data
     */
    protected function validateData(array $data, string $conflictHandling): bool
    {
        $valid = true;

        foreach ($data as $settingData) {
            if (! is_array($settingData)) {
                $valid = false;
                $this->reportValidationFailure('Record is not a valid object.', $conflictHandling);

                continue;
            }

            // Raw SQL payloads can only be verified by executing them, which a
            // dry run must not do, so they are accepted as-is here.
            if (isset($settingData['__raw_sql'])) {
                continue;
            }

            $key = $settingData['key'] ?? null;
            if (! is_scalar($key) || (string) $key === '') {
                $valid = false;
                $this->reportValidationFailure('Setting is missing a valid "key".', $conflictHandling);

                continue;
            }

            if (! isset($settingData['type'])) {
                $valid = false;
                $this->reportValidationFailure('Setting ['.(string) $key.'] is missing a "type".', $conflictHandling);
            }
        }

        return $valid;
    }

    protected function reportValidationFailure(string $reason, string $conflictHandling): void
    {
        if ($conflictHandling === 'fail') {
            throw new InvalidImportDataException($reason);
        }

        if ($conflictHandling === 'log') {
            Log::error('Import validation failed: '.$reason);
        }
    }

    protected function getContent(string $path): string
    {
        return match (true) {
            str_ends_with($path, '.gz') => $this->readGzipContent($path),
            default => $this->readPlainContent($path),
        };
    }

    protected function readGzipContent(string $path): string
    {
        $content = @file_get_contents($path);

        if ($content === false) {
            $content = Storage::disk('local')->get($path);
        }

        if (! is_string($content)) {
            return '';
        }

        $decoded = @gzdecode($content);

        return is_string($decoded) ? $decoded : '';
    }

    protected function readPlainContent(string $path): string
    {
        $content = @file_get_contents($path);

        if ($content === false) {
            $content = Storage::disk('local')->get($path);
        }

        return is_string($content) ? $content : '';
    }

    protected function truncateTables(): void
    {
        $connection = $this->operation?->connection;
        FulcrumContext::force(true);
        try {
            SettingValue::on($connection)->delete();
            SettingRuleCondition::on($connection)->delete();
            SettingRuleRolloutVariant::on($connection)->delete();
            SettingRule::on($connection)->delete();
            Setting::on($connection)->delete();
        } finally {
            FulcrumContext::force(false);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function importSetting(array $data, string $mode, string $conflictHandling): void
    {
        $connection = $this->operation?->connection;
        $this->importSafely(fn (): int => $this->storeSetting($data, $mode, $connection), $conflictHandling, $data['key'] ?? 'unknown', $connection);
    }

    /** @param array<string, mixed> $data */
    private function storeSetting(array $data, string $mode, ?string $connection): int
    {
        $keyValue = $data['key'] ?? null;
        if (! is_scalar($keyValue)) {
            return 0;
        }
        $key = (string) $keyValue;
        $tenantId = $data['tenant_id'] ?? null;

        $setting = Setting::on($connection)->where('key', $key)->where('tenant_id', $tenantId)->first();

        if ($setting && $mode === 'insert') {
            throw new DuplicateSettingException($key, is_scalar($tenantId) ? (string) $tenantId : null);
        }

        FulcrumContext::force(true);
        try {
            if (! $setting) {
                $setting = Setting::on($connection)->create([
                    'key' => $data['key'],
                    'tenant_id' => $data['tenant_id'] ?? null,
                    'type' => $data['type'],
                    'description' => $data['description'] ?? null,
                    ...(array_key_exists('group', $data) ? ['group' => $data['group']] : []),
                    'masked' => $data['masked'] ?? false,
                    'immutable' => $data['immutable'] ?? false,
                ]);
            } else {
                $setting->update([
                    'type' => $data['type'],
                    'description' => $data['description'] ?? null,
                    ...(array_key_exists('group', $data) ? ['group' => $data['group']] : []),
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
                // Rules are replaced wholesale so the imported state exactly
                // mirrors the source rather than merging with existing rules.
                $setting->rules()->lazyById()->each(fn ($rule) => $rule->delete());
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

        return 1;
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
