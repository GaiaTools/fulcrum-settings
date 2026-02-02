<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Contracts\SettingTypeHandler;
use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\Exceptions\InvalidTypeHandlerException;
use GaiaTools\FulcrumSettings\Exceptions\MissingTypeHandlerException;
use GaiaTools\FulcrumSettings\Support\TypeRegistry;

test('type registry can register and check handlers', function () {
    $registry = new TypeRegistry;

    $handler = new class implements SettingTypeHandler
    {
        public function get(mixed $value): mixed
        {
            return $value;
        }

        public function set(mixed $value): mixed
        {
            return $value;
        }

        public function validate(mixed $value): bool
        {
            return true;
        }

        public function getDatabaseType(): string
        {
            return 'string';
        }
    };

    $registry->register('custom', get_class($handler));

    expect($registry->has('custom'))->toBeTrue()
        ->and($registry->has('non-existent'))->toBeFalse();
});

test('type registry throws exception for missing handler', function () {
    $registry = new TypeRegistry;

    expect(fn () => $registry->getHandler('missing'))
        ->toThrow(MissingTypeHandlerException::class);
});

test('type registry throws exception for invalid handler class', function () {
    $registry = new TypeRegistry;

    expect(fn () => $registry->register('invalid', 'NonExistentClass'))
        ->toThrow(InvalidTypeHandlerException::class);
});

test('type registry throws exception for class not implementing SettingTypeHandler', function () {
    $registry = new TypeRegistry;

    expect(fn () => $registry->register('invalid', \stdClass::class))
        ->toThrow(InvalidTypeHandlerException::class);
});

test('getHandler works with SettingType enum', function () {
    $registry = new TypeRegistry;

    $handler = new class implements SettingTypeHandler
    {
        public function get(mixed $value): mixed
        {
            return $value;
        }

        public function set(mixed $value): mixed
        {
            return $value;
        }

        public function validate(mixed $value): bool
        {
            return true;
        }

        public function getDatabaseType(): string
        {
            return 'string';
        }
    };

    $registry->register('string', get_class($handler));

    $resolvedHandler = $registry->getHandler(SettingType::STRING);
    expect($resolvedHandler)->toBeInstanceOf(get_class($handler));
});
