<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Exceptions\FulcrumException;
use GaiaTools\FulcrumSettings\Exceptions\ImmutableSettingException;
use GaiaTools\FulcrumSettings\Exceptions\InvalidSettingValueException;
use GaiaTools\FulcrumSettings\Exceptions\InvalidTypeHandlerException;
use GaiaTools\FulcrumSettings\Exceptions\MissingTypeHandlerException;
use GaiaTools\FulcrumSettings\Exceptions\PennantException;
use GaiaTools\FulcrumSettings\Exceptions\SettingNotFoundException;

it('instantiates FulcrumException', function () {
    $exception = new FulcrumException('Test message');
    expect($exception->getMessage())->toBe('Test message');
});

it('instantiates ImmutableSettingException', function () {
    $exception = new ImmutableSettingException('Test message');
    expect($exception->getMessage())->toBe('Test message');
});

it('instantiates InvalidSettingValueException via static methods', function () {
    $exception = InvalidSettingValueException::forType('string', 123);
    expect($exception->getMessage())->toBe('Value of type [int] is not valid for setting type [string].');

    $exception = InvalidSettingValueException::forSetting('test.key', 'int', 'string');
    expect($exception->getMessage())->toBe('Value of type [string] is not valid for setting [test.key] of type [int].');
});

it('instantiates InvalidTypeHandlerException via static methods', function () {
    $exception = InvalidTypeHandlerException::classNotFound('string', 'NonExistentClass');
    expect($exception->getMessage())->toBe('Type handler class [NonExistentClass] for type [string] does not exist.');

    $exception = InvalidTypeHandlerException::invalidImplementation('string', 'InvalidClass');
    expect($exception->getMessage())->toBe('Type handler [InvalidClass] for type [string] must implement SettingTypeHandler interface.');

    $exception = InvalidTypeHandlerException::notRegistered('string');
    expect($exception->getMessage())->toBe('Type handler for type [string] is not registered.');
});

it('instantiates MissingTypeHandlerException via static methods', function () {
    $exception = MissingTypeHandlerException::forType('string');
    expect($exception->getMessage())->toBe("No type handler registered for [string]. Register it in config/fulcrum.php under 'types'.");

    $exception = MissingTypeHandlerException::forProperty('SomeClass', 'someProperty', 'string');
    expect($exception->getMessage())->toBe("No type handler registered for property [SomeClass::\$someProperty] of type [string]. Register it in config/fulcrum.php under 'types'.");
});

it('instantiates PennantException via static methods', function () {
    $exception = PennantException::unsupportedOperation('some-op');
    expect($exception->getMessage())->toBe('The [some-op] operation is not supported by the Fulcrum driver.');

    $exception = PennantException::featureNotFound('some-feature');
    expect($exception->getMessage())->toBe('The feature [some-feature] was not found in the Fulcrum database.');

    $exception = PennantException::invalidScope(['foo']);
    expect($exception->getMessage())->toBe('The provided scope of type [array] is invalid for the Fulcrum driver.');
});

it('instantiates SettingNotFoundException', function () {
    $exception = new SettingNotFoundException('test.key');
    expect($exception->getMessage())->toBe('Setting [test.key] not found.');

    $exception = new SettingNotFoundException('test.key', 'tenant-1');
    expect($exception->getMessage())->toBe('Setting [test.key] for tenant [tenant-1] not found.');
});
