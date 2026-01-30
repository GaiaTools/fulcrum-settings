<?php

use GaiaTools\FulcrumSettings\Enums\ComparisonOperator;
use GaiaTools\FulcrumSettings\Support\Builders\RuleConditionBuilder;

test('it can add a condition with operator as string', function () {
    $builder = new RuleConditionBuilder;
    $builder->where('user.level', 'number_gt', 10);

    $conditions = $builder->getConditions();
    expect($conditions)->toHaveCount(1);
    expect($conditions[0]['attribute'])->toBe('user.level');
    expect($conditions[0]['operator'])->toBe(ComparisonOperator::NUMBER_GT);
    expect($conditions[0]['value'])->toBe(10);
});

test('it can add a condition with operator as enum', function () {
    $builder = new RuleConditionBuilder;
    $builder->where('user.level', ComparisonOperator::NUMBER_LT, 5);

    $conditions = $builder->getConditions();
    expect($conditions[0]['operator'])->toBe(ComparisonOperator::NUMBER_LT);
});

test('it handles shorthand for boolean operators', function () {
    $builder = new RuleConditionBuilder;
    $builder->where('user.active', 'is_true');

    $conditions = $builder->getConditions();
    expect($conditions[0]['attribute'])->toBe('user.active');
    expect($conditions[0]['operator'])->toBe(ComparisonOperator::IS_TRUE);
    expect($conditions[0]['value'])->toBeNull();
});
