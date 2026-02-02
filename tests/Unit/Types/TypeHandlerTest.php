<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\Types\BooleanTypeHandler;
use GaiaTools\FulcrumSettings\Types\FloatTypeHandler;
use GaiaTools\FulcrumSettings\Types\IntegerTypeHandler;
use GaiaTools\FulcrumSettings\Types\JsonTypeHandler;
use GaiaTools\FulcrumSettings\Types\StringTypeHandler;

describe('StringTypeHandler', function () {
    beforeEach(fn () => $this->handler = new StringTypeHandler);

    test('get converts value to string', function () {
        expect($this->handler->get('hello'))->toBe('hello');
        expect($this->handler->get(123))->toBe('123');
        expect($this->handler->get(true))->toBe('1');
    });

    test('set converts value to string', function () {
        expect($this->handler->set('hello'))->toBe('hello');
        expect($this->handler->set(123))->toBe('123');
    });

    test('validate checks if value is a string', function () {
        expect($this->handler->validate('hello'))->toBeTrue();
        expect($this->handler->validate(123))->toBeFalse();
        expect($this->handler->validate([]))->toBeFalse();
    });

    test('getDatabaseType returns string value', function () {
        expect($this->handler->getDatabaseType())->toBe(SettingType::STRING->value);
    });
});

describe('BooleanTypeHandler', function () {
    beforeEach(fn () => $this->handler = new BooleanTypeHandler);

    test('get converts various values to boolean', function () {
        expect($this->handler->get(null))->toBeFalse();
        expect($this->handler->get(true))->toBeTrue();
        expect($this->handler->get(false))->toBeFalse();
        expect($this->handler->get('1'))->toBeTrue();
        expect($this->handler->get('true'))->toBeTrue();
        expect($this->handler->get('0'))->toBeFalse();
        expect($this->handler->get('false'))->toBeFalse();
        expect($this->handler->get('anything'))->toBeFalse();
    });

    test('set converts boolean to string', function () {
        expect($this->handler->set(true))->toBe('1');
        expect($this->handler->set(false))->toBe('0');
    });

    test('validate returns true for scalars and false for others', function () {
        expect($this->handler->validate(true))->toBeTrue();
        expect($this->handler->validate(1))->toBeTrue();
        expect($this->handler->validate(1.5))->toBeTrue();
        expect($this->handler->validate('true'))->toBeTrue();
        expect($this->handler->validate(null))->toBeTrue();
        expect($this->handler->validate([]))->toBeFalse();
        expect($this->handler->validate(new stdClass))->toBeFalse();
    });

    test('getDatabaseType returns string', function () {
        expect($this->handler->getDatabaseType())->toBe('string');
    });
});

describe('FloatTypeHandler', function () {
    beforeEach(fn () => $this->handler = new FloatTypeHandler);

    test('get converts values to float', function () {
        expect($this->handler->get('1.5'))->toBe(1.5);
        expect($this->handler->get(1))->toBe(1.0);
        expect($this->handler->get(2.3))->toBe(2.3);
    });

    test('set converts values to string float', function () {
        expect($this->handler->set(1.5))->toBe('1.5');
        expect($this->handler->set(1))->toBe('1');
    });

    test('validate checks if value is numeric', function () {
        expect($this->handler->validate(1.5))->toBeTrue();
        expect($this->handler->validate('1.5'))->toBeTrue();
        expect($this->handler->validate('abc'))->toBeFalse();
    });

    test('getDatabaseType returns string value', function () {
        expect($this->handler->getDatabaseType())->toBe(SettingType::STRING->value);
    });
});

describe('IntegerTypeHandler', function () {
    beforeEach(fn () => $this->handler = new IntegerTypeHandler);

    test('get converts values to integer', function () {
        expect($this->handler->get('123'))->toBe(123);
        expect($this->handler->get(123.5))->toBe(123);
    });

    test('set converts values to string integer', function () {
        expect($this->handler->set(123))->toBe('123');
    });

    test('validate checks if value is an integer or numeric string', function () {
        expect($this->handler->validate(123))->toBeTrue();
        expect($this->handler->validate('123'))->toBeTrue();
        expect($this->handler->validate('-123'))->toBeTrue();
        expect($this->handler->validate('123.5'))->toBeFalse();
        expect($this->handler->validate('abc'))->toBeFalse();
        expect($this->handler->validate([]))->toBeFalse();
    });

    test('getDatabaseType returns integer value', function () {
        expect($this->handler->getDatabaseType())->toBe(SettingType::INTEGER->value);
    });
});

describe('JsonTypeHandler', function () {
    beforeEach(fn () => $this->handler = new JsonTypeHandler);

    test('get decodes json string or returns value if already array/object', function () {
        $data = ['key' => 'value'];
        $json = json_encode($data);

        expect($this->handler->get($json))->toBe($data);
        expect($this->handler->get($data))->toBe($data);
        expect($this->handler->get('not a json'))->toBe('not a json');
        expect($this->handler->get(''))->toBe('');
        expect($this->handler->get(null))->toBeNull();
    });

    test('set encodes value to json', function () {
        $data = ['key' => 'value'];
        expect($this->handler->set($data))->toBe(json_encode($data));
    });

    test('validate checks if value is array, object or valid json string', function () {
        expect($this->handler->validate(['a' => 1]))->toBeTrue();
        expect($this->handler->validate(new stdClass))->toBeTrue();
        expect($this->handler->validate('{"a":1}'))->toBeTrue();
        expect($this->handler->validate('invalid json'))->toBeFalse();
        expect($this->handler->validate(123))->toBeFalse();
    });

    test('getDatabaseType returns json value', function () {
        expect($this->handler->getDatabaseType())->toBe(SettingType::JSON->value);
    });
});

describe('ArrayTypeHandler', function () {
    beforeEach(fn () => $this->handler = new \GaiaTools\FulcrumSettings\Types\ArrayTypeHandler);

    test('get decodes json string or returns value if already array', function () {
        $data = ['a' => 1, 'b' => 2];
        $json = json_encode($data);

        expect($this->handler->get($json))->toBe($data);
        expect($this->handler->get($data))->toBe($data);
        expect($this->handler->get('invalid json'))->toBe([]);
    });

    test('set encodes array to json', function () {
        $data = ['a' => 1];
        expect($this->handler->set($data))->toBe(json_encode($data));
    });

    test('validate checks if value is an array', function () {
        expect($this->handler->validate(['a' => 1]))->toBeTrue();
        expect($this->handler->validate([]))->toBeTrue();
        expect($this->handler->validate('{"a":1}'))->toBeFalse();
        expect($this->handler->validate(123))->toBeFalse();
        expect($this->handler->validate(null))->toBeFalse();
    });

    test('getDatabaseType returns string', function () {
        expect($this->handler->getDatabaseType())->toBe('string');
    });
});
