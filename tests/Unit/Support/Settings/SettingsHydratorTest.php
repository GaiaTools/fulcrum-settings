<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Unit\Support\Settings;

use GaiaTools\FulcrumSettings\Attributes\SettingProperty;
use GaiaTools\FulcrumSettings\Contracts\SettingResolver;
use GaiaTools\FulcrumSettings\Contracts\SettingTypeHandler;
use GaiaTools\FulcrumSettings\Support\MaskedValue;
use GaiaTools\FulcrumSettings\Support\Settings\SettingsHydrator;
use GaiaTools\FulcrumSettings\Support\TypeRegistry;
use GaiaTools\FulcrumSettings\Tests\TestCase;
use Mockery;
use ReflectionNamedType;

class SettingsHydratorTest extends TestCase
{
    protected $typeRegistry;

    protected $hydrator;

    protected $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->typeRegistry = Mockery::mock(TypeRegistry::class);
        $this->hydrator = new SettingsHydrator($this->typeRegistry);
        $this->resolver = Mockery::mock(SettingResolver::class);
    }

    public function test_it_hydrates_property_from_resolver()
    {
        $instance = new class
        {
            public string $name;
        };
        $config = new SettingProperty(key: 'test.name', default: 'default');

        $this->resolver->shouldReceive('resolve')
            ->with('test.name', 'context')
            ->once()
            ->andReturn('resolved-value');

        $handler = Mockery::mock(SettingTypeHandler::class);
        $handler->shouldReceive('get')->with('resolved-value')->andReturn('casted-value');
        $this->typeRegistry->shouldReceive('getHandler')->with('string')->andReturn($handler);

        $value = $this->hydrator->hydrate($instance, 'name', $config, $this->resolver, 'context');

        $this->assertEquals('casted-value', $value);
    }

    public function test_it_uses_default_value_when_resolver_returns_null()
    {
        $instance = new class
        {
            public string $name;
        };
        $config = new SettingProperty(key: 'test.name', default: 'default');

        $this->resolver->shouldReceive('resolve')->andReturn(null);

        $handler = Mockery::mock(SettingTypeHandler::class);
        $handler->shouldReceive('get')->with('default')->andReturn('casted-default');
        $this->typeRegistry->shouldReceive('getHandler')->with('string')->andReturn($handler);

        $value = $this->hydrator->hydrate($instance, 'name', $config, $this->resolver, null);

        $this->assertEquals('casted-default', $value);
    }

    public function test_it_returns_value_early_if_it_is_masked_value()
    {
        $instance = new class
        {
            public $name;
        };
        $config = new SettingProperty(key: 'test.name');
        $maskedValue = new MaskedValue('secret');

        $value = $this->hydrator->castValue($instance, 'name', $config, $maskedValue);

        $this->assertSame($maskedValue, $value);
    }

    public function test_it_returns_value_if_type_cannot_be_resolved()
    {
        $instance = new class
        {
            public $untyped;
        };
        $config = new SettingProperty(key: 'test.untyped');

        $value = $this->hydrator->castValue($instance, 'untyped', $config, 'some-value');

        $this->assertEquals('some-value', $value);
    }

    public function test_it_uses_explicit_cast_from_config()
    {
        $instance = new class
        {
            public $name;
        };
        $config = new SettingProperty(key: 'test.name', cast: 'boolean');

        $handler = Mockery::mock(SettingTypeHandler::class);
        $handler->shouldReceive('get')->with('1')->andReturn(true);
        $this->typeRegistry->shouldReceive('getHandler')->with('boolean')->andReturn($handler);

        $value = $this->hydrator->castValue($instance, 'name', $config, '1');

        $this->assertTrue($value);
    }

    public function test_it_handles_intersection_or_union_types_by_returning_null_type_name()
    {
        // ReflectionNamedType is checked. If it's not a named type, it returns null.
        // It's hard to create a property with union types in anonymous classes in some PHP versions without Eval,
        // but we can mock or just use a property without a type (already tested).
        // Let's try to trigger the ! $type instanceof ReflectionNamedType path.

        $instance = new class
        {
            public string|int $multi;
        };
        $config = new SettingProperty(key: 'test.multi');

        $value = $this->hydrator->castValue($instance, 'multi', $config, 'val');

        // In PHP 8.0+, string|int is ReflectionUnionType, not ReflectionNamedType
        $this->assertEquals('val', $value);
    }
}
