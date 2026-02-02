<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Unit\Types;

use Carbon\CarbonImmutable;
use GaiaTools\FulcrumSettings\Tests\TestCase;
use GaiaTools\FulcrumSettings\Types\CarbonImmutableTypeHandler;
use Illuminate\Support\Facades\Config;

class CarbonImmutableTypeHandlerTest extends TestCase
{
    protected CarbonImmutableTypeHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new CarbonImmutableTypeHandler;
    }

    public function test_get_returns_null_for_null_or_empty_value()
    {
        $this->assertNull($this->handler->get(null));
        $this->assertNull($this->handler->get(''));
    }

    public function test_get_parses_string_to_carbon_immutable_instance()
    {
        $dateStr = '2024-03-15T14:30:00+00:00';
        $result = $this->handler->get($dateStr);

        $this->assertInstanceOf(CarbonImmutable::class, $result);
        $this->assertEquals($dateStr, $result->toIso8601String());
    }

    public function test_get_respects_global_output_timezone()
    {
        Config::set('fulcrum.carbon.output_timezone', 'America/New_York');
        $dateStr = '2024-03-15T14:30:00+00:00';
        $result = $this->handler->get($dateStr);

        $this->assertEquals('America/New_York', $result->timezoneName);
        // 14:30 UTC is 10:30 EDT (UTC-4) in March
        $this->assertEquals(10, $result->hour);
    }

    public function test_get_respects_context_timezone_binding()
    {
        app()->instance('fulcrum.context.timezone', 'Europe/London');
        $dateStr = '2024-03-15T14:30:00+00:00';
        $result = $this->handler->get($dateStr);

        $this->assertEquals('Europe/London', $result->timezoneName);
    }

    public function test_set_returns_null_for_null_or_empty_value()
    {
        $this->assertNull($this->handler->set(null));
        $this->assertNull($this->handler->set(''));
    }

    public function test_set_converts_carbon_immutable_instance_to_iso8601_string()
    {
        $carbon = CarbonImmutable::parse('2024-03-15 14:30:00', 'UTC');
        $this->assertEquals($carbon->toIso8601String(), $this->handler->set($carbon));
    }

    public function test_set_parses_string_if_not_carbon_immutable()
    {
        $dateStr = '2024-03-15 14:30:00';
        $result = $this->handler->set($dateStr);
        $this->assertNotNull($result);
        $this->assertEquals(CarbonImmutable::parse($dateStr)->utc()->toIso8601String(), $result);
    }

    public function test_set_converts_to_utc_when_store_utc_is_true()
    {
        Config::set('fulcrum.carbon.store_utc', true);
        $carbon = CarbonImmutable::parse('2024-03-15 14:30:00', 'America/New_York');
        $result = $this->handler->set($carbon);

        $this->assertStringContainsString('+00:00', $result);
        $this->assertEquals(18, CarbonImmutable::parse($result)->hour); // 14:30 EDT is 18:30 UTC
    }

    public function test_set_does_not_convert_to_utc_when_store_utc_is_false()
    {
        Config::set('fulcrum.carbon.store_utc', false);
        $carbon = CarbonImmutable::parse('2024-03-15 14:30:00', 'America/New_York');
        $result = $this->handler->set($carbon);

        $this->assertStringContainsString('-04:00', $result);
        $this->assertEquals(14, CarbonImmutable::parse($result)->hour);
    }

    public function test_validate_returns_true_for_valid_inputs()
    {
        $this->assertTrue($this->handler->validate(null));
        $this->assertTrue($this->handler->validate(''));
        $this->assertTrue($this->handler->validate(CarbonImmutable::now()));
        $this->assertTrue($this->handler->validate('2024-01-01'));
        $this->assertTrue($this->handler->validate('now'));
    }

    public function test_validate_returns_false_for_invalid_inputs()
    {
        $this->assertFalse($this->handler->validate(['not', 'a', 'date']));
        $this->assertFalse($this->handler->validate('not a date'));
    }

    public function test_get_database_type_returns_carbon_immutable()
    {
        $this->assertEquals('carbon_immutable', $this->handler->getDatabaseType());
    }
}
