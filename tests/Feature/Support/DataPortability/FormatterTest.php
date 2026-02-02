<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Feature\Support\DataPortability;

use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\SqlFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\YamlFormatter;
use GaiaTools\FulcrumSettings\Tests\TestCase;

class FormatterTest extends TestCase
{
    public function test_yaml_formatter_format_and_parse()
    {
        $formatter = new YamlFormatter;
        $data = [
            [
                'key' => 'test_setting',
                'type' => 'string',
                'description' => 'A test setting',
                'rules' => [
                    ['priority' => 1, 'name' => 'Rule 1'],
                ],
            ],
        ];

        $yaml = $formatter->format($data);
        $this->assertStringContainsString('test_setting', $yaml);
        $this->assertStringContainsString('Rule 1', $yaml);

        $parsed = $formatter->parse($yaml);
        $this->assertEquals($data, $parsed);
    }

    public function test_yaml_formatter_empty_content()
    {
        $formatter = new YamlFormatter;
        $this->assertEquals([], $formatter->parse(''));
        $this->assertEquals([], $formatter->parse('   '));
    }

    public function test_sql_formatter_format()
    {
        $formatter = new SqlFormatter;
        $data = [
            [
                'key' => 'test_setting',
                'tenant_id' => null,
                'type' => 'string',
                'description' => 'A test setting',
                'masked' => false,
                'immutable' => false,
                'default_value' => 'default',
                'rules' => [
                    [
                        'priority' => 1,
                        'name' => 'Rule 1',
                        'value' => 'rule_value',
                        'conditions' => [
                            ['attribute' => 'user_id', 'operator' => '==', 'value' => '1'],
                        ],
                        'rollout_variants' => [
                            ['name' => 'Variant A', 'weight' => 50, 'value' => 'v_a'],
                        ],
                    ],
                ],
            ],
        ];

        $sql = $formatter->format($data);

        // $this->info($sql);
        $this->assertStringContainsString('INSERT INTO `settings`', $sql);
        $this->assertStringContainsString('test_setting', $sql);
        $this->assertStringContainsString('INSERT INTO `setting_values`', $sql);
        $this->assertStringContainsString('GaiaTools', $sql);
        $this->assertStringContainsString('Fulcrum', $sql);
        $this->assertStringContainsString('Setting', $sql);
        $this->assertStringContainsString('INSERT INTO `setting_rules`', $sql);
        $this->assertStringContainsString('Rule 1', $sql);
        $this->assertStringContainsString('INSERT INTO `setting_rule_conditions`', $sql);
        $this->assertStringContainsString('user_id', $sql);
        $this->assertStringContainsString('INSERT INTO `setting_rule_rollout_variants`', $sql);
        $this->assertStringContainsString('Variant A', $sql);
    }

    public function test_sql_formatter_format_with_booleans()
    {
        $formatter = new SqlFormatter;
        $data = [
            [
                'key' => 'bool_setting',
                'type' => 'boolean',
                'masked' => true,
                'immutable' => false,
                'default_value' => true,
            ],
            [
                'key' => 'bool_setting_false',
                'type' => 'boolean',
                'masked' => false,
                'immutable' => true,
                'default_value' => false,
            ],
        ];

        $sql = $formatter->format($data);

        $this->assertStringContainsString('bool_setting', $sql);
        $this->assertStringContainsString('bool_setting_false', $sql);
        $this->assertStringContainsString(', 1);', $sql);
        $this->assertStringContainsString(', 0);', $sql);
    }

    public function test_sql_formatter_parse_returns_raw_sql()
    {
        $formatter = new SqlFormatter;
        $sql = 'SELECT * FROM settings';
        $this->assertEquals([['__raw_sql' => $sql]], $formatter->parse($sql));
    }
}
