<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Feature\Support\DataPortability;

use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\JsonFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\SqlFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\YamlFormatter;
use GaiaTools\FulcrumSettings\Tests\TestCase;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\PostgresConnection;
use Illuminate\Database\SqlServerConnection;
use Illuminate\Support\Facades\DB;

class FormatterTest extends TestCase
{
    public function test_json_and_yaml_parsers_keep_only_named_record_fields(): void
    {
        foreach ([new JsonFormatter, new YamlFormatter] as $formatter) {
            $content = $formatter->format([
                ['key' => 'test_setting', 'type' => 'string', 0 => 'invalid field'],
            ]);

            $this->assertSame([
                ['key' => 'test_setting', 'type' => 'string'],
            ], $formatter->parse($content));
        }
    }

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
        $this->assertStringContainsString('insert into "settings"', $sql);
        $this->assertStringContainsString('test_setting', $sql);
        $this->assertStringContainsString('insert into "setting_values"', $sql);
        $this->assertStringContainsString('GaiaTools', $sql);
        $this->assertStringContainsString('Fulcrum', $sql);
        $this->assertStringContainsString('Setting', $sql);
        $this->assertStringContainsString('insert into "setting_rules"', $sql);
        $this->assertStringContainsString('Rule 1', $sql);
        $this->assertStringContainsString('insert into "setting_rule_conditions"', $sql);
        $this->assertStringContainsString('user_id', $sql);
        $this->assertStringContainsString('insert into "setting_rule_rollout_variants"', $sql);
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
        $this->assertStringContainsString(", 'true');", $sql);
        $this->assertStringContainsString(", 'false');", $sql);
    }

    public function test_sql_formatter_parse_returns_raw_sql()
    {
        $formatter = new SqlFormatter;
        $sql = 'SELECT * FROM settings';
        $this->assertEquals([['__raw_sql' => $sql]], $formatter->parse($sql));
    }

    public function test_sql_export_uses_the_selected_driver_grammar(): void
    {
        $pdo = DB::connection()->getPdo();
        foreach ([
            'mysql' => [MySqlConnection::class, '`settings`', '1'],
            'pgsql' => [PostgresConnection::class, '"settings"', 'true'],
            'sqlsrv' => [SqlServerConnection::class, '[settings]', '1'],
        ] as $driver => [$class, $identifier, $boolean]) {
            // Compile each real driver grammar using a local PDO for literal quoting.
            // This checks SQL generation, not execution on those database servers.
            $connection = new $class($pdo, ':memory:', '', ['driver' => $driver]);
            DB::extend('portability_grammar', fn () => $connection);
            config(['database.connections.dialect' => ['driver' => 'portability_grammar']]);
            DB::purge('dialect');
            $sql = (new SqlFormatter)->usingConnection('dialect')->format([
                ['key' => 'dialect', 'type' => 'boolean', 'masked' => true, 'immutable' => false],
            ]);
            $this->assertStringContainsString('insert into '.$identifier, $sql);
            $this->assertStringContainsString(', '.$boolean.', ', $sql);
            $this->assertStringNotContainsString('FOREIGN_KEY_CHECKS', $sql);
        }
    }

    public function test_sql_formatter_skips_malformed_nested_records_and_quotes_stringables(): void
    {
        $sql = (new SqlFormatter)->format([
            ['key' => 'mixed', 'type' => 'json', 'default_value' => ['nested' => true], 'rules' => [
                null,
                ['conditions' => [false], 'rollout_variants' => [false, [
                    'name' => new class implements \Stringable
                    {
                        public function __toString(): string
                        {
                            return "variant's";
                        }
                    },
                    'weight' => 50,
                ]]],
            ]],
        ]);
        $this->assertStringContainsString("variant''s", $sql);
        $this->assertStringContainsString('{"nested":true}', $sql);
    }

    public function test_sql_formatter_rejects_unencodable_values(): void
    {
        $this->expectException(\JsonException::class);
        (new SqlFormatter)->format([['key' => 'invalid', 'default_value' => NAN]]);
    }
}
