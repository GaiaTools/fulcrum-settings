<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\Types\FloatTypeHandler;

describe('FloatTypeHandler', function () {
    beforeEach(fn () => $this->handler = new FloatTypeHandler);

    test('get converts values to float', function () {
        expect($this->handler->get('1.5'))->toBe(1.5);
        expect($this->handler->get(1))->toBe(1.0);
        expect($this->handler->get(2.3))->toBe(2.3);
        expect($this->handler->get(null))->toBe(0.0);
    });

    test('set converts values to string float', function () {
        expect($this->handler->set(1.5))->toBe('1.5');
        expect($this->handler->set(1))->toBe('1');
        expect($this->handler->set('2.5'))->toBe('2.5');
    });

    test('validate checks if value is numeric', function () {
        expect($this->handler->validate(1.5))->toBeTrue();
        expect($this->handler->validate('1.5'))->toBeTrue();
        expect($this->handler->validate('1'))->toBeTrue();
        expect($this->handler->validate(1))->toBeTrue();
        expect($this->handler->validate('abc'))->toBeFalse();
        expect($this->handler->validate([]))->toBeFalse();
        expect($this->handler->validate(null))->toBeFalse();
    });

    test('getDatabaseType returns string value', function () {
        expect($this->handler->getDatabaseType())->toBe(SettingType::STRING->value);
    });
});
