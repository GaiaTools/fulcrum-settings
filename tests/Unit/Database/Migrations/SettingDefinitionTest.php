<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Unit\Database\Migrations;

use GaiaTools\FulcrumSettings\Database\Migrations\SettingDefinition;
use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\Exceptions\InvalidSettingValueException;
use GaiaTools\FulcrumSettings\Exceptions\InvalidTypeHandlerException;
use GaiaTools\FulcrumSettings\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SettingDefinitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sets_basic_properties()
    {
        $definition = new SettingDefinition('test.key');
        $definition->type(SettingType::STRING)
            ->default('default_val')
            ->description('desc')
            ->masked()
            ->immutable()
            ->forTenant('tenant_1');

        \GaiaTools\FulcrumSettings\Support\FulcrumContext::force(true);
        try {
            $setting = $definition->save();
        } finally {
            \GaiaTools\FulcrumSettings\Support\FulcrumContext::force(false);
        }

        $this->assertEquals('test.key', $setting->key);
        $this->assertEquals(SettingType::STRING, $setting->type);
        $this->assertEquals('desc', $setting->description);
        $this->assertTrue($setting->masked);
        $this->assertTrue($setting->immutable);
        $this->assertEquals('tenant_1', $setting->tenant_id);
        $this->assertEquals('default_val', $setting->defaultValue->value);
    }

    public function test_it_supports_convenience_type_methods()
    {
        $types = [
            'boolean' => SettingType::BOOLEAN,
            'string' => SettingType::STRING,
            'integer' => SettingType::INTEGER,
            'float' => SettingType::FLOAT,
            'json' => SettingType::JSON,
            'array' => SettingType::JSON,
        ];

        foreach ($types as $method => $expectedType) {
            $definition = new SettingDefinition("test.{$method}");
            $this->assertEquals("test.{$method}", $definition->getKey());
            $definition->$method();
            $setting = $definition->save();
            $this->assertEquals($expectedType, $setting->type);
        }
    }

    public function test_it_validates_default_value()
    {
        $this->expectException(InvalidSettingValueException::class);

        $definition = new SettingDefinition('test.invalid');
        $definition->boolean()->default(['array is invalid for boolean']);

        $definition->save();
    }

    public function test_it_throws_exception_for_unregistered_type_in_setting_definition()
    {
        $definition = new SettingDefinition('test-key');

        $this->expectException(InvalidTypeHandlerException::class);

        $definition->type('unregistered-type');
    }

    public function test_it_can_add_rules()
    {
        $definition = new SettingDefinition('test.rules');
        $definition->string()->rule(function ($rule) {
            $rule->name('beta_users')->then('beta_value');
        });

        $setting = $definition->save();

        $this->assertCount(1, $setting->rules);
        $this->assertEquals('beta_users', $setting->rules[0]->name);
        $this->assertEquals('beta_value', $setting->rules[0]->value->value);
    }

    public function test_it_can_add_multiple_rules()
    {
        $definition = new SettingDefinition('test.multiple_rules');
        $definition->string()->rules([
            function ($rule) {
                $rule->name('r1')->priority(1)->then('v1');
            },
            function ($rule) {
                $rule->name('r2')->priority(2)->then('v2');
            },
        ]);

        $setting = $definition->save();

        $this->assertCount(2, $setting->rules);
        $this->assertEquals('r1', $setting->rules[0]->name);
        $this->assertEquals('r2', $setting->rules[1]->name);
    }
}
