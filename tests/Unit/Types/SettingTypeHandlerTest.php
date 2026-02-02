<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Types\SettingTypeHandler;

class ConcreteSettingTypeHandler extends SettingTypeHandler
{
    protected function encode(mixed $value): string
    {
        return (string) $value;
    }

    protected function decode(mixed $value): mixed
    {
        return $value;
    }

    public function validate(mixed $value): bool
    {
        return is_scalar($value);
    }

    public function getDatabaseType(): string
    {
        return 'string';
    }
}

describe('Abstract SettingTypeHandler', function () {
    beforeEach(fn () => $this->handler = new ConcreteSettingTypeHandler);

    test('encodeForStorage validates and encodes', function () {
        expect($this->handler->encodeForStorage('test'))->toBe('test');
    });

    test('encodeForStorage throws exception on invalid input', function () {
        $this->handler->encodeForStorage([]);
    })->throws(InvalidArgumentException::class, 'Invalid input [array] for type handler [ConcreteSettingTypeHandler].');

    test('decodeFromStorage decodes', function () {
        expect($this->handler->decodeFromStorage('test'))->toBe('test');
    });

    test('isValidInput proxies to validate', function () {
        expect($this->handler->isValidInput('test'))->toBeTrue();
        expect($this->handler->isValidInput([]))->toBeFalse();
    });

    test('get proxies to decodeFromStorage', function () {
        expect($this->handler->get('test'))->toBe('test');
    });

    test('set proxies to encodeForStorage', function () {
        expect($this->handler->set('test'))->toBe('test');
    });
});
