<?php

declare(strict_types=1);

use Carbon\Carbon;
use GaiaTools\FulcrumSettings\Types\CarbonTypeHandler;
use Illuminate\Support\Facades\Config;

describe('CarbonTypeHandler', function () {
    beforeEach(fn () => $this->handler = new CarbonTypeHandler);

    test('get returns null for null value', function () {
        expect($this->handler->get(null))->toBeNull();
        expect($this->handler->get(''))->toBeNull();
    });

    test('get parses string to carbon instance', function () {
        $dateStr = '2024-03-15T14:30:00+00:00';
        $result = $this->handler->get($dateStr);

        expect($result)->toBeInstanceOf(Carbon::class);
        expect($result->toIso8601String())->toBe($dateStr);
    });

    test('get respects global output timezone', function () {
        Config::set('fulcrum.carbon.output_timezone', 'America/New_York');
        $dateStr = '2024-03-15T14:30:00+00:00';
        $result = $this->handler->get($dateStr);

        expect($result->timezoneName)->toBe('America/New_York');
        expect($result->hour)->toBe(10); // 14:30 UTC is 10:30 EST (DST starts March 10 in 2024)
    });

    test('get respects context timezone binding', function () {
        app()->instance('fulcrum.context.timezone', 'Europe/London');
        $dateStr = '2024-03-15T14:30:00+00:00';
        $result = $this->handler->get($dateStr);

        expect($result->timezoneName)->toBe('Europe/London');
        app()->forgetInstance('fulcrum.context.timezone');
    });

    test('set returns null for null value', function () {
        expect($this->handler->set(null))->toBeNull();
        expect($this->handler->set(''))->toBeNull();
    });

    test('set converts carbon instance to ISO8601 string', function () {
        $carbon = Carbon::parse('2024-03-15 14:30:00', 'UTC');
        expect($this->handler->set($carbon))->toBe($carbon->toIso8601String());
    });

    test('set converts carbon instance to UTC when store_utc is true', function () {
        Config::set('fulcrum.carbon.store_utc', true);
        $carbon = Carbon::parse('2024-03-15 14:30:00', 'America/New_York');
        $result = $this->handler->set($carbon);

        expect($result)->toContain('+00:00');
        expect(Carbon::parse($result)->hour)->toBe(18); // 14:30 EDT is 18:30 UTC
    });

    test('validate returns true for valid inputs', function () {
        expect($this->handler->validate(null))->toBeTrue();
        expect($this->handler->validate(''))->toBeTrue();
        expect($this->handler->validate(Carbon::now()))->toBeTrue();
        expect($this->handler->validate('2024-01-01'))->toBeTrue();
        expect($this->handler->validate('now'))->toBeTrue();
    });

    test('validate returns false for invalid inputs', function () {
        expect($this->handler->validate(['not', 'a', 'date']))->toBeFalse();
        expect($this->handler->validate('not a date'))->toBeFalse();
    });

    test('set converts string date to ISO8601 string', function () {
        $dateStr = '2024-03-15 14:30:00';
        $result = $this->handler->set($dateStr);
        expect($result)->toBe(Carbon::parse($dateStr)->utc()->toIso8601String());
    });

    test('set respects store_utc false', function () {
        Config::set('fulcrum.carbon.store_utc', false);
        $carbon = Carbon::parse('2024-03-15 14:30:00', 'America/New_York');
        $result = $this->handler->set($carbon);

        expect($result)->toBe($carbon->toIso8601String());
        expect($result)->toContain('-04:00'); // EDT
    });

    test('getDatabaseType returns carbon', function () {
        expect($this->handler->getDatabaseType())->toBe('carbon');
    });
});
