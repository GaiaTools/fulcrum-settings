<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Enums\ComparisonOperator;

describe('ComparisonOperator', function () {
    it('identifies string operators correctly', function () {
        expect(ComparisonOperator::EQUALS->isStringOperator())->toBeTrue();
        expect(ComparisonOperator::NOT_EQUALS->isStringOperator())->toBeTrue();
        expect(ComparisonOperator::CONTAINS_ANY->isStringOperator())->toBeTrue();
        expect(ComparisonOperator::NOT_CONTAINS_ANY->isStringOperator())->toBeTrue();
        expect(ComparisonOperator::STARTS_WITH_ANY->isStringOperator())->toBeTrue();
        expect(ComparisonOperator::ENDS_WITH_ANY->isStringOperator())->toBeTrue();
        expect(ComparisonOperator::MATCHES_REGEX->isStringOperator())->toBeTrue();

        expect(ComparisonOperator::NUMBER_EQUALS->isStringOperator())->toBeFalse();
        expect(ComparisonOperator::IN_SEGMENT->isStringOperator())->toBeFalse();
    });

    it('identifies numeric operators correctly', function () {
        expect(ComparisonOperator::NUMBER_EQUALS->isNumericOperator())->toBeTrue();
        expect(ComparisonOperator::NUMBER_NOT_EQUALS->isNumericOperator())->toBeTrue();
        expect(ComparisonOperator::NUMBER_GT->isNumericOperator())->toBeTrue();
        expect(ComparisonOperator::NUMBER_GTE->isNumericOperator())->toBeTrue();
        expect(ComparisonOperator::NUMBER_LT->isNumericOperator())->toBeTrue();
        expect(ComparisonOperator::NUMBER_LTE->isNumericOperator())->toBeTrue();
        expect(ComparisonOperator::NUMBER_BETWEEN->isNumericOperator())->toBeTrue();

        expect(ComparisonOperator::EQUALS->isNumericOperator())->toBeFalse();
    });

    it('identifies date operators correctly', function () {
        expect(ComparisonOperator::DATE_EQUALS->isDateOperator())->toBeTrue();
        expect(ComparisonOperator::DATE_NOT_EQUALS->isDateOperator())->toBeTrue();
        expect(ComparisonOperator::DATE_GT->isDateOperator())->toBeTrue();
        expect(ComparisonOperator::DATE_GTE->isDateOperator())->toBeTrue();
        expect(ComparisonOperator::DATE_LT->isDateOperator())->toBeTrue();
        expect(ComparisonOperator::DATE_LTE->isDateOperator())->toBeTrue();
        expect(ComparisonOperator::DATE_BETWEEN->isDateOperator())->toBeTrue();

        expect(ComparisonOperator::NUMBER_EQUALS->isDateOperator())->toBeFalse();
    });

    it('identifies version operators correctly', function () {
        expect(ComparisonOperator::VERSION_EQUALS->isVersionOperator())->toBeTrue();
        expect(ComparisonOperator::VERSION_NOT_EQUALS->isVersionOperator())->toBeTrue();
        expect(ComparisonOperator::VERSION_GT->isVersionOperator())->toBeTrue();
        expect(ComparisonOperator::VERSION_GTE->isVersionOperator())->toBeTrue();
        expect(ComparisonOperator::VERSION_LT->isVersionOperator())->toBeTrue();
        expect(ComparisonOperator::VERSION_LTE->isVersionOperator())->toBeTrue();
        expect(ComparisonOperator::VERSION_BETWEEN->isVersionOperator())->toBeTrue();

        expect(ComparisonOperator::DATE_EQUALS->isVersionOperator())->toBeFalse();
    });

    it('identifies segment operators correctly', function () {
        expect(ComparisonOperator::IN_SEGMENT->isSegmentOperator())->toBeTrue();
        expect(ComparisonOperator::NOT_IN_SEGMENT->isSegmentOperator())->toBeTrue();

        expect(ComparisonOperator::EQUALS->isSegmentOperator())->toBeFalse();
    });

    it('identifies boolean operators correctly', function () {
        expect(ComparisonOperator::IS_TRUE->isBooleanOperator())->toBeTrue();
        expect(ComparisonOperator::IS_FALSE->isBooleanOperator())->toBeTrue();

        expect(ComparisonOperator::EQUALS->isBooleanOperator())->toBeFalse();
    });

    it('identifies null operators correctly', function () {
        expect(ComparisonOperator::IS_NULL->isNullOperator())->toBeTrue();
        expect(ComparisonOperator::IS_NOT_NULL->isNullOperator())->toBeTrue();

        expect(ComparisonOperator::EQUALS->isNullOperator())->toBeFalse();
    });

    it('knows which operators require a value', function () {
        expect(ComparisonOperator::EQUALS->requiresValue())->toBeTrue();
        expect(ComparisonOperator::NUMBER_GT->requiresValue())->toBeTrue();
        expect(ComparisonOperator::IN_SEGMENT->requiresValue())->toBeTrue();

        expect(ComparisonOperator::IS_TRUE->requiresValue())->toBeFalse();
        expect(ComparisonOperator::IS_FALSE->requiresValue())->toBeFalse();
        expect(ComparisonOperator::IS_NULL->requiresValue())->toBeFalse();
        expect(ComparisonOperator::IS_NOT_NULL->requiresValue())->toBeFalse();
    });

    it('knows which operators require an array value', function () {
        expect(ComparisonOperator::CONTAINS_ANY->requiresArrayValue())->toBeTrue();
        expect(ComparisonOperator::NOT_CONTAINS_ANY->requiresArrayValue())->toBeTrue();
        expect(ComparisonOperator::STARTS_WITH_ANY->requiresArrayValue())->toBeTrue();
        expect(ComparisonOperator::ENDS_WITH_ANY->requiresArrayValue())->toBeTrue();
        expect(ComparisonOperator::NUMBER_BETWEEN->requiresArrayValue())->toBeTrue();
        expect(ComparisonOperator::DATE_BETWEEN->requiresArrayValue())->toBeTrue();
        expect(ComparisonOperator::VERSION_BETWEEN->requiresArrayValue())->toBeTrue();

        expect(ComparisonOperator::EQUALS->requiresArrayValue())->toBeFalse();
        expect(ComparisonOperator::NUMBER_GT->requiresArrayValue())->toBeFalse();
    });
});
