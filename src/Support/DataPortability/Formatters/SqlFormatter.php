<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\DataPortability\Formatters;

use GaiaTools\FulcrumSettings\Enums\ConditionType;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Models\SettingRule;
use GaiaTools\FulcrumSettings\Models\SettingRuleRolloutVariant;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class SqlFormatter implements Formatter
{
    public function __construct(protected ?string $connectionName = null) {}

    public function usingConnection(?string $name): static
    {
        $formatter = clone $this;
        $formatter->connectionName = $name;

        return $formatter;
    }

    protected function connection(): Connection
    {
        return DB::connection($this->connectionName);
    }

    public function format(array $data): string
    {
        $sql = "-- Laravel Fulcrum Settings Export\n";
        $sql .= '-- Generated at: '.date('Y-m-d H:i:s')."\n\n";

        foreach ($data as $setting) {
            $sql .= $this->generateSettingSql($setting);
        }

        return $sql;
    }

    public function parse(string $content): array
    {
        return [['__raw_sql' => $content]];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function generateSettingSql(array $data): string
    {
        $key = $data['key'] ?? '';
        $key = is_scalar($key) ? (string) $key : '';
        $sql = "-- Setting\n";

        $type = $data['type'] ?? 'string';
        $type = is_scalar($type) ? (string) $type : 'string';

        $settingData = [
            'key' => $key,
            'tenant_id' => $data['tenant_id'] ?? null,
            'type' => $type,
            'description' => $data['description'] ?? null,
            'group' => $data['group'] ?? null,
            'masked' => (bool) ($data['masked'] ?? false),
            'immutable' => (bool) ($data['immutable'] ?? false),
        ];

        $sql .= $this->insertStatement('settings', $settingData)."\n";

        if (array_key_exists('default_value', $data)) {
            $sql .= $this->insertValueSql((new Setting)->getMorphClass(), $this->settingId($key, $data['tenant_id'] ?? null), $data['tenant_id'] ?? null, $data['default_value']);
        }

        if (isset($data['rules']) && is_array($data['rules'])) {
            foreach ($data['rules'] as $rule) {
                if (! is_array($rule)) {
                    continue;
                }
                /** @var array<string, mixed> $rule */
                $sql .= $this->generateRuleSql($rule, $key, $data['tenant_id'] ?? null);
            }
        }

        $sql .= "\n";

        return $sql;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    protected function generateRuleSql(array $rule, string $settingKey, mixed $tenantId): string
    {
        $sql = "  -- Rule\n";
        $settingIdSubquery = $this->settingId($settingKey, $tenantId);

        $priority = $rule['priority'] ?? 0;
        $priority = is_numeric($priority) ? (int) $priority : 0;

        $ruleNameValue = $rule['name'] ?? null;
        $ruleNameValue = is_scalar($ruleNameValue) ? (string) $ruleNameValue : null;

        $ruleData = [
            'setting_id' => $settingIdSubquery,
            'tenant_id' => $rule['tenant_id'] ?? $tenantId,
            'name' => $ruleNameValue,
            'priority' => $priority,
            'rollout_salt' => $rule['rollout_salt'] ?? null,
            'starts_at' => $rule['starts_at'] ?? null,
            'ends_at' => $rule['ends_at'] ?? null,
        ];

        $sql .= '  '.$this->insertStatement('setting_rules', $ruleData)."\n";

        $ruleIdSubquery = $this->selectId('setting_rules', [
            'setting_id' => $settingIdSubquery, 'priority' => $priority,
        ]);

        if (array_key_exists('value', $rule)) {
            $sql .= '  '.$this->insertValueSql((new SettingRule)->getMorphClass(), $ruleIdSubquery, $rule['tenant_id'] ?? $tenantId, $rule['value']);
        }

        if (isset($rule['conditions']) && is_array($rule['conditions'])) {
            $sql .= $this->generateConditionsSql($rule['conditions'], $ruleIdSubquery, $rule['tenant_id'] ?? $tenantId);
        }
        if (isset($rule['rollout_variants']) && is_array($rule['rollout_variants'])) {
            $sql .= $this->generateVariantsSql($rule['rollout_variants'], $ruleIdSubquery, $rule['tenant_id'] ?? $tenantId);
        }

        return $sql;
    }

    /** @param array<array-key, mixed> $conditions */
    protected function generateConditionsSql(array $conditions, Builder $ruleIdSubquery, mixed $tenantId): string
    {
        $sql = '';
        foreach ($conditions as $condition) {
            if (! is_array($condition)) {
                continue;
            }
            $conditionData = [
                'setting_rule_id' => $ruleIdSubquery,
                'tenant_id' => $condition['tenant_id'] ?? $tenantId,
                'type' => $condition['type'] ?? ConditionType::default(),
                'attribute' => $condition['attribute'],
                'operator' => $condition['operator'],
                'value' => $this->encodeJsonValue($condition['value']),
            ];
            $sql .= '  '.$this->insertStatement('setting_rule_conditions', $conditionData)."\n";
        }

        return $sql;
    }

    /** @param array<array-key, mixed> $variants */
    protected function generateVariantsSql(array $variants, Builder $ruleIdSubquery, mixed $tenantId): string
    {
        $sql = '';
        foreach ($variants as $variant) {
            if (! is_array($variant)) {
                continue;
            }
            $variantData = [
                'setting_rule_id' => $ruleIdSubquery,
                'tenant_id' => $variant['tenant_id'] ?? $tenantId,
                'name' => $this->stringifyValue($variant['name'] ?? ''),
                'weight' => $variant['weight'],
            ];
            $sql .= '  '.$this->insertStatement('setting_rule_rollout_variants', $variantData)."\n";

            $variantIdSubquery = $this->selectId('setting_rule_rollout_variants', [
                'setting_rule_id' => $ruleIdSubquery,
                'name' => $this->stringifyValue($variant['name'] ?? ''),
            ]);

            if (array_key_exists('value', $variant)) {
                $sql .= '  '.$this->insertValueSql((new SettingRuleRolloutVariant)->getMorphClass(), $variantIdSubquery, $variant['tenant_id'] ?? $tenantId, $variant['value']);
            }
        }

        return $sql;
    }

    protected function insertValueSql(string $type, mixed $id, mixed $tenantId, mixed $value): string
    {
        $data = [
            'valuable_type' => $type,
            'valuable_id' => $id,
            'tenant_id' => $tenantId,
            'value' => $value === null || is_string($value) ? $value : $this->encodeJsonValue($value),
        ];

        return $this->insertStatement('setting_values', $data)."\n";
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function insertStatement(string $table, array $data): string
    {
        $grammar = $this->connection()->getQueryGrammar();
        $values = array_map(fn ($value): string => $this->formatSqlValue($value), array_values($data));

        return 'insert into '.$grammar->wrapTable($this->tableName($table)).' ('
            .$grammar->columnize(array_keys($data)).') values ('.implode(', ', $values).');';
    }

    protected function tableName(string $table): string
    {
        return config()->string('fulcrum.table_names.'.$table, $table);
    }

    protected function settingId(string $key, mixed $tenantId): Builder
    {
        return $this->selectId('settings', ['key' => $key, 'tenant_id' => $tenantId]);
    }

    /** @param array<string, mixed> $where */
    protected function selectId(string $table, array $where): Builder
    {
        return $this->connection()->table($this->tableName($table))->select('id')->where($where)->orderByDesc('id')->limit(1);
    }

    protected function stringifyValue(mixed $value): string
    {
        return match (true) {
            is_string($value) => $value,
            is_scalar($value) => (string) $value,
            is_object($value) && method_exists($value, '__toString') => (string) $value,
            default => $this->encodeJsonValue($value),
        };
    }

    protected function formatSqlValue(mixed $value): string
    {
        return match (true) {
            $value === null => 'NULL',
            $value instanceof Builder => '('.$value->toRawSql().')',
            is_bool($value) => $this->connection()->escape($value),
            default => $this->quoteSqlValue($value),
        };
    }

    protected function quoteSqlValue(mixed $value): string
    {
        return $this->connection()->escape($this->stringifyValue($value));
    }

    protected function encodeJsonValue(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}
