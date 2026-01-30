<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\DataPortability\Formatters;

class SqlFormatter implements Formatter
{
    public function format(array $data): string
    {
        $sql = "-- Laravel Fulcrum Settings Export\n";
        $sql .= '-- Generated at: '.date('Y-m-d H:i:s')."\n\n";

        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($data as $setting) {
            $sql .= $this->generateSettingSql($setting);
        }

        $sql .= "\nSET FOREIGN_KEY_CHECKS=1;\n";

        return $sql;
    }

    public function parse(string $content): array
    {
        // For SQL, we don't return an array of data.
        // Instead, we might want to execute the SQL directly.
        // But the Formatter interface expects an array.
        // Given the requirement, we will try to execute the SQL here if we have a connection.
        // However, the ImportManager handles transactions and connections.

        // If we want to stay within the current architecture:
        // We'll return a special structure that ImportManager can recognize, or we just execute it here.
        // Executing here might break the transaction in ImportManager if not careful.

        // Let's see if we can just return the raw SQL lines as "pseudo-data"
        // and have ImportManager execute them, but that requires changing ImportManager.

        // Alternatively, we can return an empty array and hope the user just wanted the formatter to exist?
        // No, the issue says "The import command is missing SQL formatter entirely".

        // Let's try to implement a very basic "parser" that just returns the content
        // wrapped in a way that we can handle.
        return [['__raw_sql' => $content]];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function generateSettingSql(array $data): string
    {
        $key = $data['key'] ?? '';
        $key = is_scalar($key) ? (string) $key : '';
        $sql = "-- Setting: {$key}\n";

        $type = $data['type'] ?? 'string';
        $type = is_scalar($type) ? (string) $type : 'string';

        $settingData = [
            'key' => $key,
            'tenant_id' => $data['tenant_id'] ?? null,
            'type' => $type,
            'description' => $data['description'] ?? null,
            'masked' => (int) (bool) ($data['masked'] ?? false),
            'immutable' => (int) (bool) ($data['immutable'] ?? false),
        ];

        $sql .= $this->insertStatement('settings', $settingData)."\n";

        if (isset($data['default_value'])) {
            $sql .= $this->insertValueSql('GaiaTools\FulcrumSettings\Models\Setting', $key, $data['tenant_id'] ?? null, $data['default_value']);
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
        $ruleName = $rule['name'] ?? 'Unnamed';
        $ruleName = is_scalar($ruleName) ? (string) $ruleName : 'Unnamed';
        $sql = '  -- Rule: '.$ruleName."\n";

        // This is tricky because we need the setting_id.
        // In a raw SQL export, we might need to use subqueries or variables.
        $tenantClause = 'tenant_id IS NULL';
        if ($tenantId !== null) {
            $tenantClause = "tenant_id = '".addslashes($this->stringifyValue($tenantId))."'";
        }
        $settingIdSubquery = "(SELECT id FROM settings WHERE `key` = '{$settingKey}' AND {$tenantClause} LIMIT 1)";

        $priority = $rule['priority'] ?? 0;
        $priority = is_numeric($priority) ? (int) $priority : 0;

        $ruleNameValue = $rule['name'] ?? null;
        $ruleNameValue = is_scalar($ruleNameValue) ? (string) $ruleNameValue : null;

        $ruleData = [
            'setting_id' => 'RAW:'.$settingIdSubquery,
            'tenant_id' => $rule['tenant_id'] ?? $tenantId,
            'name' => $ruleNameValue,
            'priority' => $priority,
            'rollout_salt' => $rule['rollout_salt'] ?? null,
            'starts_at' => $rule['starts_at'] ?? null,
            'ends_at' => $rule['ends_at'] ?? null,
        ];

        $sql .= '  '.$this->insertStatement('setting_rules', $ruleData)."\n";

        $ruleIdSubquery = "(SELECT id FROM setting_rules WHERE setting_id = {$settingIdSubquery} AND priority = {$priority} ORDER BY id DESC LIMIT 1)";

        if (isset($rule['value'])) {
            $sql .= '  '.$this->insertValueSql('GaiaTools\FulcrumSettings\Models\SettingRule', 'RAW:'.$ruleIdSubquery, $rule['tenant_id'] ?? $tenantId, $rule['value']);
        }

        if (isset($rule['conditions']) && is_array($rule['conditions'])) {
            foreach ($rule['conditions'] as $condition) {
                if (! is_array($condition)) {
                    continue;
                }
                $conditionData = [
                    'setting_rule_id' => 'RAW:'.$ruleIdSubquery,
                    'tenant_id' => $condition['tenant_id'] ?? $rule['tenant_id'] ?? $tenantId,
                    'attribute' => $condition['attribute'],
                    'operator' => $condition['operator'],
                    'value' => $condition['value'],
                ];
                $sql .= '  '.$this->insertStatement('setting_rule_conditions', $conditionData)."\n";
            }
        }

        if (isset($rule['rollout_variants']) && is_array($rule['rollout_variants'])) {
            foreach ($rule['rollout_variants'] as $variant) {
                if (! is_array($variant)) {
                    continue;
                }
                $variantData = [
                    'setting_rule_id' => 'RAW:'.$ruleIdSubquery,
                    'tenant_id' => $variant['tenant_id'] ?? $rule['tenant_id'] ?? $tenantId,
                    'name' => $this->stringifyValue($variant['name'] ?? ''),
                    'weight' => $variant['weight'],
                ];
                $sql .= '  '.$this->insertStatement('setting_rule_rollout_variants', $variantData)."\n";

                $variantName = addslashes($this->stringifyValue($variant['name'] ?? ''));
                $variantIdSubquery = "(SELECT id FROM setting_rule_rollout_variants WHERE setting_rule_id = {$ruleIdSubquery} AND name = '{$variantName}' ORDER BY id DESC LIMIT 1)";

                if (isset($variant['value'])) {
                    $sql .= '  '.$this->insertValueSql('GaiaTools\FulcrumSettings\Models\SettingRuleRolloutVariant', 'RAW:'.$variantIdSubquery, $variant['tenant_id'] ?? $rule['tenant_id'] ?? $tenantId, $variant['value']);
                }
            }
        }

        return $sql;
    }

    protected function insertValueSql(string $type, mixed $id, mixed $tenantId, mixed $value): string
    {
        $rawId = is_string($id) && str_starts_with($id, 'RAW:');
        $idValue = $rawId ? substr($id, 4) : "'".$this->stringifyValue($id)."'";

        $data = [
            'valuable_type' => $type,
            'valuable_id' => 'RAW:'.$idValue,
            'tenant_id' => $tenantId,
            'value' => $value,
        ];

        return $this->insertStatement('setting_values', $data)."\n";
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function insertStatement(string $table, array $data): string
    {
        $columns = array_keys($data);
        $values = array_map(function ($value) {
            if ($value === null) {
                return 'NULL';
            }
            if (is_string($value) && str_starts_with($value, 'RAW:')) {
                return substr($value, 4);
            }
            if (is_bool($value)) {
                return $value ? '1' : '0';
            }

            $stringValue = $this->stringifyValue($value);

            return "'".addslashes(str_replace('\\', '\\\\', $stringValue))."'";
        }, array_values($data));

        return "INSERT INTO `{$table}` (`".implode('`, `', $columns).'`) VALUES ('.implode(', ', $values).');';
    }

    protected function stringifyValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        $encoded = json_encode($value);

        return $encoded === false ? '' : $encoded;
    }
}
