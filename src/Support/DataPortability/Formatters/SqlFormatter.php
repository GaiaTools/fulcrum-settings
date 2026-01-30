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

    protected function generateSettingSql(array $data): string
    {
        $sql = "-- Setting: {$data['key']}\n";

        $settingData = [
            'key' => $data['key'],
            'tenant_id' => $data['tenant_id'] ?? null,
            'type' => $data['type'],
            'description' => $data['description'] ?? null,
            'masked' => (int) ($data['masked'] ?? 0),
            'immutable' => (int) ($data['immutable'] ?? 0),
        ];

        $sql .= $this->insertStatement('settings', $settingData)."\n";

        if (isset($data['default_value'])) {
            $sql .= $this->insertValueSql('GaiaTools\FulcrumSettings\Models\Setting', $data['key'], $data['tenant_id'] ?? null, $data['default_value']);
        }

        if (isset($data['rules'])) {
            foreach ($data['rules'] as $rule) {
                $sql .= $this->generateRuleSql($rule, $data['key'], $data['tenant_id'] ?? null);
            }
        }

        $sql .= "\n";

        return $sql;
    }

    protected function generateRuleSql(array $rule, string $settingKey, mixed $tenantId): string
    {
        $sql = '  -- Rule: '.($rule['name'] ?? 'Unnamed')."\n";

        // This is tricky because we need the setting_id.
        // In a raw SQL export, we might need to use subqueries or variables.
        $settingIdSubquery = "(SELECT id FROM settings WHERE `key` = '{$settingKey}' AND ".($tenantId === null ? 'tenant_id IS NULL' : "tenant_id = '{$tenantId}'").' LIMIT 1)';

        $ruleData = [
            'setting_id' => 'RAW:'.$settingIdSubquery,
            'tenant_id' => $rule['tenant_id'] ?? $tenantId,
            'name' => $rule['name'] ?? null,
            'priority' => $rule['priority'] ?? 0,
            'rollout_salt' => $rule['rollout_salt'] ?? null,
            'starts_at' => $rule['starts_at'] ?? null,
            'ends_at' => $rule['ends_at'] ?? null,
        ];

        $sql .= '  '.$this->insertStatement('setting_rules', $ruleData)."\n";

        $ruleIdSubquery = "(SELECT id FROM setting_rules WHERE setting_id = {$settingIdSubquery} AND priority = ".($rule['priority'] ?? 0).' ORDER BY id DESC LIMIT 1)';

        if (isset($rule['value'])) {
            $sql .= '  '.$this->insertValueSql('GaiaTools\FulcrumSettings\Models\SettingRule', 'RAW:'.$ruleIdSubquery, $rule['tenant_id'] ?? $tenantId, $rule['value']);
        }

        if (isset($rule['conditions'])) {
            foreach ($rule['conditions'] as $condition) {
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

        if (isset($rule['rollout_variants'])) {
            foreach ($rule['rollout_variants'] as $variant) {
                $variantData = [
                    'setting_rule_id' => 'RAW:'.$ruleIdSubquery,
                    'tenant_id' => $variant['tenant_id'] ?? $rule['tenant_id'] ?? $tenantId,
                    'name' => $variant['name'],
                    'weight' => $variant['weight'],
                ];
                $sql .= '  '.$this->insertStatement('setting_rule_rollout_variants', $variantData)."\n";

                $variantIdSubquery = "(SELECT id FROM setting_rule_rollout_variants WHERE setting_rule_id = {$ruleIdSubquery} AND name = '".addslashes($variant['name'])."' ORDER BY id DESC LIMIT 1)";

                if (isset($variant['value'])) {
                    $sql .= '  '.$this->insertValueSql('GaiaTools\FulcrumSettings\Models\SettingRuleRolloutVariant', 'RAW:'.$variantIdSubquery, $variant['tenant_id'] ?? $rule['tenant_id'] ?? $tenantId, $variant['value']);
                }
            }
        }

        return $sql;
    }

    protected function insertValueSql(string $type, mixed $id, mixed $tenantId, mixed $value): string
    {
        $idValue = str_starts_with((string) $id, 'RAW:') ? substr((string) $id, 4) : "'{$id}'";

        $data = [
            'valuable_type' => $type,
            'valuable_id' => 'RAW:'.$idValue,
            'tenant_id' => $tenantId,
            'value' => $value,
        ];

        return $this->insertStatement('setting_values', $data)."\n";
    }

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

            return "'".addslashes(str_replace('\\', '\\\\', (string) $value))."'";
        }, array_values($data));

        return "INSERT INTO `{$table}` (`".implode('`, `', $columns).'`) VALUES ('.implode(', ', $values).');';
    }
}
