<?php

use GaiaTools\FulcrumSettings\Contracts\GeoResolver;
use GaiaTools\FulcrumSettings\Contracts\HolidayResolver;
use GaiaTools\FulcrumSettings\Contracts\SegmentDriver;
use GaiaTools\FulcrumSettings\Contracts\UserAgentResolver;
use GaiaTools\FulcrumSettings\Enums\ComparisonOperator;
use GaiaTools\FulcrumSettings\Enums\ConditionType;
use GaiaTools\FulcrumSettings\Models\SettingRule;
use GaiaTools\FulcrumSettings\Models\SettingRuleCondition;
use GaiaTools\FulcrumSettings\Services\RuleEvaluator;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->segmentDriver = Mockery::mock(SegmentDriver::class);
    $this->geoResolver = Mockery::mock(GeoResolver::class);
    $this->uaResolver = Mockery::mock(UserAgentResolver::class);
    $this->holidayResolver = Mockery::mock(HolidayResolver::class);
    $this->app->instance(GeoResolver::class, $this->geoResolver);
    $this->app->instance(UserAgentResolver::class, $this->uaResolver);
    $this->evaluator = new RuleEvaluator(
        $this->segmentDriver,
        $this->holidayResolver
    );
    FulcrumContext::clear();
});

test('it evaluates string equals', function () {
    $condition = new SettingRuleCondition([
        'attribute' => 'user.name',
        'operator' => ComparisonOperator::EQUALS,
        'value' => 'John',
    ]);

    expect($this->evaluator->evaluateCondition($condition, ['user' => ['name' => 'John']]))->toBeTrue();
    expect($this->evaluator->evaluateCondition($condition, ['user' => ['name' => 'Jane']]))->toBeFalse();
});

test('it evaluates numeric comparisons', function () {
    $condition = new SettingRuleCondition([
        'attribute' => 'user.age',
        'operator' => ComparisonOperator::NUMBER_GT,
        'value' => 18,
    ]);

    expect($this->evaluator->evaluateCondition($condition, ['user' => ['age' => 20]]))->toBeTrue();
    expect($this->evaluator->evaluateCondition($condition, ['user' => ['age' => 18]]))->toBeFalse();
    expect($this->evaluator->evaluateCondition($condition, ['user' => ['age' => 15]]))->toBeFalse();
});

test('it evaluates numeric between', function () {
    $condition = new SettingRuleCondition([
        'attribute' => 'user.age',
        'operator' => ComparisonOperator::NUMBER_BETWEEN,
        'value' => [18, 30],
    ]);

    expect($this->evaluator->evaluateCondition($condition, ['user' => ['age' => 20]]))->toBeTrue();
    expect($this->evaluator->evaluateCondition($condition, ['user' => ['age' => 18]]))->toBeTrue();
    expect($this->evaluator->evaluateCondition($condition, ['user' => ['age' => 30]]))->toBeTrue();
    expect($this->evaluator->evaluateCondition($condition, ['user' => ['age' => 31]]))->toBeFalse();
});

test('it evaluates date comparisons', function () {
    $now = Carbon::now();
    $condition = new SettingRuleCondition([
        'attribute' => 'user.created_at',
        'operator' => ComparisonOperator::DATE_GT,
        'value' => $now->subDay()->toDateTimeString(),
    ]);

    expect($this->evaluator->evaluateCondition($condition, ['user' => ['created_at' => Carbon::now()->toDateTimeString()]]))->toBeTrue();
    expect($this->evaluator->evaluateCondition($condition, ['user' => ['created_at' => Carbon::now()->subDays(2)->toDateTimeString()]]))->toBeFalse();
});

test('it evaluates version comparisons', function () {
    $condition = new SettingRuleCondition([
        'attribute' => 'app.version',
        'operator' => ComparisonOperator::VERSION_GTE,
        'value' => '1.2.0',
    ]);

    expect($this->evaluator->evaluateCondition($condition, ['app' => ['version' => '1.2.0']]))->toBeTrue();
    expect($this->evaluator->evaluateCondition($condition, ['app' => ['version' => '1.2.1']]))->toBeTrue();
    expect($this->evaluator->evaluateCondition($condition, ['app' => ['version' => '1.1.9']]))->toBeFalse();
});

test('it evaluates segment comparisons', function () {
    $user = Mockery::mock(Authenticatable::class);
    $this->evaluator->setUser($user);

    $condition = new SettingRuleCondition([
        'attribute' => 'user.segment',
        'operator' => ComparisonOperator::IN_SEGMENT,
        'value' => 'vip',
    ]);

    $this->segmentDriver->shouldReceive('isInSegment')->with($user, 'vip')->twice()->andReturn(true, false);

    expect($this->evaluator->evaluateCondition($condition, []))->toBeTrue();
    expect($this->evaluator->evaluateCondition($condition, []))->toBeFalse();
});

test('it evaluates boolean comparisons', function () {
    $conditionTrue = new SettingRuleCondition([
        'attribute' => 'user.is_active',
        'operator' => ComparisonOperator::IS_TRUE,
    ]);

    expect($this->evaluator->evaluateCondition($conditionTrue, ['user' => ['is_active' => true]]))->toBeTrue();
    expect($this->evaluator->evaluateCondition($conditionTrue, ['user' => ['is_active' => false]]))->toBeFalse();

    $conditionFalse = new SettingRuleCondition([
        'attribute' => 'user.is_active',
        'operator' => ComparisonOperator::IS_FALSE,
    ]);

    expect($this->evaluator->evaluateCondition($conditionFalse, ['user' => ['is_active' => false]]))->toBeTrue();
    expect($this->evaluator->evaluateCondition($conditionFalse, ['user' => ['is_active' => true]]))->toBeFalse();
});

test('it evaluates null comparisons', function () {
    $condition = new SettingRuleCondition([
        'attribute' => 'user.deleted_at',
        'operator' => ComparisonOperator::IS_NULL,
    ]);

    expect($this->evaluator->evaluateCondition($condition, ['user' => ['deleted_at' => null]]))->toBeTrue();
    expect($this->evaluator->evaluateCondition($condition, ['user' => ['deleted_at' => '2024-01-01']]))->toBeFalse();
});

test('it extracts nested attributes', function () {
    $scope = [
        'user' => [
            'profile' => [
                'city' => 'New York',
            ],
        ],
    ];

    // Using reflection to test protected method or just via evaluateCondition
    $condition = new SettingRuleCondition([
        'attribute' => 'user.profile.city',
        'operator' => ComparisonOperator::EQUALS,
        'value' => 'New York',
    ]);

    expect($this->evaluator->evaluateCondition($condition, $scope))->toBeTrue();
});

test('it evaluates a whole rule', function () {
    $rule = Mockery::mock(SettingRule::class);
    $condition1 = new SettingRuleCondition(['attribute' => 'a', 'operator' => ComparisonOperator::IS_TRUE]);
    $condition2 = new SettingRuleCondition(['attribute' => 'b', 'operator' => ComparisonOperator::IS_TRUE]);

    $rule->shouldReceive('getAttribute')->with('conditions')->andReturn(collect([$condition1, $condition2]));

    expect($this->evaluator->evaluateRule($rule, ['a' => true, 'b' => true]))->toBeTrue();
    expect($this->evaluator->evaluateRule($rule, ['a' => true, 'b' => false]))->toBeFalse();
});

test('it evaluates an empty rule as true', function () {
    $rule = Mockery::mock(SettingRule::class);
    $rule->shouldReceive('getAttribute')->with('conditions')->andReturn(collect([]));

    expect($this->evaluator->evaluateRule($rule, []))->toBeTrue();
});

test('it extracts attributes from various scope types', function () {
    // Scalar scope
    $condition = new SettingRuleCondition([
        'attribute' => 'scope',
        'operator' => ComparisonOperator::EQUALS,
        'value' => 'premium',
    ]);
    expect($this->evaluator->evaluateCondition($condition, 'premium'))->toBeTrue();

    // Object scope
    $scopeObj = (object) ['role' => 'admin'];
    $conditionRole = new SettingRuleCondition([
        'attribute' => 'role',
        'operator' => ComparisonOperator::EQUALS,
        'value' => 'admin',
    ]);
    expect($this->evaluator->evaluateCondition($conditionRole, $scopeObj))->toBeTrue();

    // Authenticatable scope
    $user = Mockery::mock(Authenticatable::class);
    $user->shouldReceive('getAuthIdentifier')->andReturn('user-123');
    if (method_exists($user, 'getEmailForPasswordReset')) {
        $user->shouldReceive('getEmailForPasswordReset')->andReturn('user@example.com');
    }

    $conditionId = new SettingRuleCondition([
        'attribute' => 'id',
        'operator' => ComparisonOperator::EQUALS,
        'value' => 'user-123',
    ]);
    expect($this->evaluator->evaluateCondition($conditionId, $user))->toBeTrue();

    // Test email extraction if method exists on mock
    // Note: Mockery::mock(Authenticatable::class) might not have getEmailForPasswordReset by default
    // as it's not in the interface, but in CanResetPassword trait usually.
});

test('it uses the authenticated user as default scope', function () {
    $user = Mockery::mock(Authenticatable::class);
    $user->shouldReceive('getAuthIdentifier')->andReturn('user-123');
    $this->evaluator->setUser($user);

    $condition = new SettingRuleCondition([
        'attribute' => 'id',
        'operator' => ComparisonOperator::EQUALS,
        'value' => 'user-123',
    ]);

    expect($this->evaluator->evaluateCondition($condition))->toBeTrue();
});

test('it handles null scope and null user', function () {
    $condition = new SettingRuleCondition([
        'attribute' => 'id',
        'operator' => ComparisonOperator::EQUALS,
        'value' => 'user-123',
    ]);

    expect($this->evaluator->evaluateCondition($condition))->toBeFalse();
});

test('it covers all string operators', function () {
    $scope = ['attr' => 'hello world'];

    // NOT_EQUALS
    $c = new SettingRuleCondition(['attribute' => 'attr', 'operator' => ComparisonOperator::NOT_EQUALS, 'value' => 'bye']);
    expect($this->evaluator->evaluateCondition($c, $scope))->toBeTrue();

    // CONTAINS_ANY
    $c = new SettingRuleCondition(['attribute' => 'attr', 'operator' => ComparisonOperator::CONTAINS_ANY, 'value' => ['hello', 'universe']]);
    expect($this->evaluator->evaluateCondition($c, $scope))->toBeTrue();

    // NOT_CONTAINS_ANY
    $c = new SettingRuleCondition(['attribute' => 'attr', 'operator' => ComparisonOperator::NOT_CONTAINS_ANY, 'value' => ['bye', 'universe']]);
    expect($this->evaluator->evaluateCondition($c, $scope))->toBeTrue();

    // STARTS_WITH_ANY
    $c = new SettingRuleCondition(['attribute' => 'attr', 'operator' => ComparisonOperator::STARTS_WITH_ANY, 'value' => ['hel', 'abc']]);
    expect($this->evaluator->evaluateCondition($c, $scope))->toBeTrue();

    $c = new SettingRuleCondition(['attribute' => 'attr', 'operator' => ComparisonOperator::STARTS_WITH_ANY, 'value' => ['abc', 'def']]);
    expect($this->evaluator->evaluateCondition($c, $scope))->toBeFalse();

    // ENDS_WITH_ANY
    $c = new SettingRuleCondition(['attribute' => 'attr', 'operator' => ComparisonOperator::ENDS_WITH_ANY, 'value' => ['rld', 'abc']]);
    expect($this->evaluator->evaluateCondition($c, $scope))->toBeTrue();

    $c = new SettingRuleCondition(['attribute' => 'attr', 'operator' => ComparisonOperator::ENDS_WITH_ANY, 'value' => ['abc', 'def']]);
    expect($this->evaluator->evaluateCondition($c, $scope))->toBeFalse();

    // UA without prefix when type is user_agent
    $c = new SettingRuleCondition([
        'type' => ConditionType::USERAGENT->value,
        'attribute' => 'browser',
        'operator' => ComparisonOperator::EQUALS,
        'value' => 'Chrome',
    ]);
    $this->uaResolver->shouldReceive('resolve')->andReturn(['browser' => 'Chrome']);
    expect($this->evaluator->evaluateCondition($c, []))->toBeTrue();

    // MATCHES_REGEX
    $c = new SettingRuleCondition(['attribute' => 'attr', 'operator' => ComparisonOperator::MATCHES_REGEX, 'value' => '/^hello/']);
    expect($this->evaluator->evaluateCondition($c, $scope))->toBeTrue();
});

test('it covers all numeric operators', function () {
    $scope = ['val' => 10];

    $ops = [
        [ComparisonOperator::NUMBER_EQUALS, 10, true],
        [ComparisonOperator::NUMBER_NOT_EQUALS, 11, true],
        [ComparisonOperator::NUMBER_GT, 9, true],
        [ComparisonOperator::NUMBER_GTE, 10, true],
        [ComparisonOperator::NUMBER_LT, 11, true],
        [ComparisonOperator::NUMBER_LTE, 10, true],
    ];

    foreach ($ops as [$op, $val, $expected]) {
        $c = new SettingRuleCondition(['attribute' => 'val', 'operator' => $op, 'value' => $val]);
        expect($this->evaluator->evaluateCondition($c, $scope))->toBe($expected);
    }
});

test('it covers all date operators', function () {
    $date = Carbon::parse('2024-01-01');
    $scope = ['date' => $date->toDateTimeString()];

    $ops = [
        [ComparisonOperator::DATE_EQUALS, '2024-01-01', true],
        [ComparisonOperator::DATE_NOT_EQUALS, '2024-01-02', true],
        [ComparisonOperator::DATE_GT, '2023-12-31', true],
        [ComparisonOperator::DATE_GTE, '2024-01-01', true],
        [ComparisonOperator::DATE_LT, '2024-01-02', true],
        [ComparisonOperator::DATE_LTE, '2024-01-01', true],
        [ComparisonOperator::DATE_BETWEEN, ['2023-12-31', '2024-01-02'], true],
    ];

    foreach ($ops as [$op, $val, $expected]) {
        $c = new SettingRuleCondition(['attribute' => 'date', 'operator' => $op, 'value' => $val]);
        expect($this->evaluator->evaluateCondition($c, $scope))->toBe($expected);
    }
});

test('it handles invalid dates gracefully', function () {
    $c = new SettingRuleCondition(['attribute' => 'date', 'operator' => ComparisonOperator::DATE_EQUALS, 'value' => '2024-01-01']);
    expect($this->evaluator->evaluateCondition($c, ['date' => 'not-a-date']))->toBeFalse();
});

test('it covers all version operators', function () {
    $scope = ['v' => '2.0.0'];

    $ops = [
        [ComparisonOperator::VERSION_EQUALS, '2.0.0', true],
        [ComparisonOperator::VERSION_NOT_EQUALS, '1.9.9', true],
        [ComparisonOperator::VERSION_GT, '1.9.9', true],
        [ComparisonOperator::VERSION_GTE, '2.0.0', true],
        [ComparisonOperator::VERSION_LT, '2.0.1', true],
        [ComparisonOperator::VERSION_LTE, '2.0.0', true],
        [ComparisonOperator::VERSION_BETWEEN, ['1.0.0', '3.0.0'], true],
    ];

    foreach ($ops as [$op, $val, $expected]) {
        $c = new SettingRuleCondition(['attribute' => 'v', 'operator' => $op, 'value' => $val]);
        expect($this->evaluator->evaluateCondition($c, $scope))->toBe($expected);
    }
});

test('it covers all segment operators', function () {
    $user = Mockery::mock(Authenticatable::class);
    $this->evaluator->setUser($user);

    $this->segmentDriver->shouldReceive('isInSegment')->with($user, 'vip')->andReturn(true);

    $c = new SettingRuleCondition(['attribute' => 'user.segment', 'operator' => ComparisonOperator::IN_SEGMENT, 'value' => 'vip']);
    expect($this->evaluator->evaluateCondition($c))->toBeTrue();

    $c = new SettingRuleCondition(['attribute' => 'user.segment', 'operator' => ComparisonOperator::NOT_IN_SEGMENT, 'value' => 'vip']);
    expect($this->evaluator->evaluateCondition($c))->toBeFalse();
});

test('it returns false for segment comparison without user', function () {
    $c = new SettingRuleCondition(['attribute' => 'user.segment', 'operator' => ComparisonOperator::IN_SEGMENT, 'value' => 'vip']);
    expect($this->evaluator->evaluateCondition($c))->toBeFalse();
});

test('it handles edge cases in between helpers', function () {
    $scope = ['val' => 10];

    // Invalid range count for numeric between
    $c = new SettingRuleCondition(['attribute' => 'val', 'operator' => ComparisonOperator::NUMBER_BETWEEN, 'value' => [10]]);
    expect($this->evaluator->evaluateCondition($c, $scope))->toBeFalse();

    // Invalid range count for date between
    $c = new SettingRuleCondition(['attribute' => 'date', 'operator' => ComparisonOperator::DATE_BETWEEN, 'value' => ['2024-01-01']]);
    expect($this->evaluator->evaluateCondition($c, ['date' => '2024-01-02']))->toBeFalse();

    // Invalid range count for version between
    $c = new SettingRuleCondition(['attribute' => 'v', 'operator' => ComparisonOperator::VERSION_BETWEEN, 'value' => ['1.0.0']]);
    expect($this->evaluator->evaluateCondition($c, ['v' => '1.1.0']))->toBeFalse();
});

test('it handles invalid date range gracefully', function () {
    $c = new SettingRuleCondition(['attribute' => 'date', 'operator' => ComparisonOperator::DATE_BETWEEN, 'value' => ['not-a-date', '2024-01-01']]);
    expect($this->evaluator->evaluateCondition($c, ['date' => '2024-01-01']))->toBeFalse();
});

test('it covers IS_NOT_NULL', function () {
    $c = new SettingRuleCondition(['attribute' => 'attr', 'operator' => ComparisonOperator::IS_NOT_NULL]);
    expect($this->evaluator->evaluateCondition($c, ['attr' => 'value']))->toBeTrue();
    expect($this->evaluator->evaluateCondition($c, ['attr' => null]))->toBeFalse();
});

test('it handles email extraction if method exists', function () {
    $user = Mockery::mock(Authenticatable::class, \Illuminate\Contracts\Auth\CanResetPassword::class);
    $user->shouldReceive('getEmailForPasswordReset')->andReturn('test@example.com');

    $c = new SettingRuleCondition(['attribute' => 'email', 'operator' => ComparisonOperator::EQUALS, 'value' => 'test@example.com']);
    expect($this->evaluator->evaluateCondition($c, $user))->toBeTrue();
});

test('it handles fallback and defaults in RuleEvaluator', function () {
    // extractAttributeValue object fallback
    $scope = new class
    {
        public $existing = 'yes';
    };
    $condition = new SettingRuleCondition(['attribute' => 'non_existent', 'operator' => ComparisonOperator::IS_NULL]);
    expect($this->evaluator->evaluateCondition($condition, $scope))->toBeFalse();

    $scopeWithNull = ['non_existent' => null];
    expect($this->evaluator->evaluateCondition($condition, $scopeWithNull))->toBeTrue();

    // evaluateCondition default fallback (though hard to reach with current Enum)
    // We can mock the operator to return false for all is*Operator checks if we want,
    // but usually default => false is just a safety.

    // evaluateRule early return false
    $rule = Mockery::mock(SettingRule::class);
    $c1 = new SettingRuleCondition(['attribute' => 'a', 'operator' => ComparisonOperator::IS_TRUE]);
    $rule->shouldReceive('getAttribute')->with('conditions')->andReturn(collect([$c1]));
    expect($this->evaluator->evaluateRule($rule, ['a' => false]))->toBeFalse();
});

test('it extracts special "now" attribute', function () {
    $condition = new SettingRuleCondition([
        'type' => 'date_time',
        'attribute' => 'now',
        'operator' => ComparisonOperator::DATE_GTE,
        'value' => Carbon::now()->subMinute()->toDateTimeString(),
    ]);

    expect($this->evaluator->evaluateCondition($condition))->toBeTrue();
});

test('it extracts geo attributes', function () {
    $this->geoResolver->shouldReceive('resolve')->andReturn(['country' => 'US', 'city' => 'New York']);

    $condition = new SettingRuleCondition([
        'type' => 'geocoding',
        'attribute' => 'country',
        'operator' => ComparisonOperator::EQUALS,
        'value' => 'US',
    ]);

    expect($this->evaluator->evaluateCondition($condition))->toBeTrue();
});

test('it extracts user agent attributes without prefixes', function () {
    $this->uaResolver->shouldReceive('resolve')->andReturn([
        'browser' => 'Chrome',
        'device' => 'Mobile',
        'os' => 'iOS',
    ]);

    expect($this->evaluator->evaluateCondition(new SettingRuleCondition([
        'type' => ConditionType::USERAGENT->value,
        'attribute' => 'browser',
        'operator' => ComparisonOperator::EQUALS,
        'value' => 'Chrome',
    ])))->toBeTrue();

    expect($this->evaluator->evaluateCondition(new SettingRuleCondition([
        'type' => ConditionType::USERAGENT->value,
        'attribute' => 'device',
        'operator' => ComparisonOperator::EQUALS,
        'value' => 'Mobile',
    ])))->toBeTrue();

    expect($this->evaluator->evaluateCondition(new SettingRuleCondition([
        'type' => ConditionType::USERAGENT->value,
        'attribute' => 'os',
        'operator' => ComparisonOperator::EQUALS,
        'value' => 'iOS',
    ])))->toBeTrue();
});

test('it extracts attributes from FulcrumContext', function () {
    FulcrumContext::set('custom_attr', 'custom_value');

    $condition = new SettingRuleCondition([
        'attribute' => 'custom_attr',
        'operator' => ComparisonOperator::EQUALS,
        'value' => 'custom_value',
    ]);

    expect($this->evaluator->evaluateCondition($condition))->toBeTrue();
});

test('it evaluates TIME_BETWEEN including overnight', function () {
    // Normal range
    $condition = new SettingRuleCondition([
        'type' => 'date_time',
        'attribute' => 'now',
        'operator' => ComparisonOperator::TIME_BETWEEN,
        'value' => ['09:00:00', '17:00:00'],
    ]);

    Carbon::setTestNow(Carbon::parse('2024-01-01 12:00:00'));
    expect($this->evaluator->evaluateCondition($condition))->toBeTrue();

    Carbon::setTestNow(Carbon::parse('2024-01-01 18:00:00'));
    expect($this->evaluator->evaluateCondition($condition))->toBeFalse();

    // Overnight range
    $overnightCondition = new SettingRuleCondition([
        'type' => 'date_time',
        'attribute' => 'now',
        'operator' => ComparisonOperator::TIME_BETWEEN,
        'value' => ['22:00:00', '06:00:00'],
    ]);

    Carbon::setTestNow(Carbon::parse('2024-01-01 23:00:00'));
    expect($this->evaluator->evaluateCondition($overnightCondition))->toBeTrue();

    Carbon::setTestNow(Carbon::parse('2024-01-02 01:00:00'));
    expect($this->evaluator->evaluateCondition($overnightCondition))->toBeTrue();

    Carbon::setTestNow(Carbon::parse('2024-01-01 12:00:00'));
    expect($this->evaluator->evaluateCondition($overnightCondition))->toBeFalse();

    Carbon::setTestNow(); // Reset
});

test('it handles invalid time range for TIME_BETWEEN', function () {
    $condition = new SettingRuleCondition([
        'type' => 'date_time',
        'attribute' => 'now',
        'operator' => ComparisonOperator::TIME_BETWEEN,
        'value' => ['09:00:00'], // Invalid: only one element
    ]);

    expect($this->evaluator->evaluateCondition($condition))->toBeFalse();
});

test('it handles missing CronExpression class', function () {
    $evaluator = Mockery::mock(RuleEvaluator::class, [
        $this->segmentDriver,
        $this->holidayResolver,
    ])->makePartial();
    $evaluator->shouldAllowMockingProtectedMethods();

    $evaluator->shouldReceive('isCronExpressionClassAvailable')
        ->andReturn(false);

    $condition = new SettingRuleCondition([
        'type' => 'date_time',
        'attribute' => 'now',
        'operator' => ComparisonOperator::SCHEDULE_CRON,
        'value' => '0 0 * * *',
    ]);

    expect($evaluator->evaluateCondition($condition))->toBeFalse();
});

test('it evaluates DAY_OF_WEEK', function () {
    $condition = new SettingRuleCondition([
        'type' => 'date_time',
        'attribute' => 'now',
        'operator' => ComparisonOperator::DAY_OF_WEEK,
        'value' => ['Monday', 'Wednesday'],
    ]);

    Carbon::setTestNow(Carbon::parse('2024-01-01')); // Monday
    expect($this->evaluator->evaluateCondition($condition))->toBeTrue();

    Carbon::setTestNow(Carbon::parse('2024-01-02')); // Tuesday
    expect($this->evaluator->evaluateCondition($condition))->toBeFalse();

    Carbon::setTestNow(); // Reset
});

test('it evaluates IS_BUSINESS_DAY and IS_HOLIDAY', function () {
    $bizCondition = new SettingRuleCondition([
        'type' => 'date_time',
        'attribute' => 'now',
        'operator' => ComparisonOperator::IS_BUSINESS_DAY,
    ]);

    Carbon::setTestNow(Carbon::parse('2024-01-01')); // Monday
    expect($this->evaluator->evaluateCondition($bizCondition))->toBeTrue();

    Carbon::setTestNow(Carbon::parse('2024-01-06')); // Saturday
    expect($this->evaluator->evaluateCondition($bizCondition))->toBeFalse();

    $holidayCondition = new SettingRuleCondition([
        'type' => 'date_time',
        'attribute' => 'now',
        'operator' => ComparisonOperator::IS_HOLIDAY,
    ]);
    $evaluatorWithoutHolidays = new RuleEvaluator(
        $this->segmentDriver
    );
    expect($evaluatorWithoutHolidays->evaluateCondition($holidayCondition))->toBeFalse();

    $this->holidayResolver->shouldReceive('isHoliday')->andReturn(true);
    expect($this->evaluator->evaluateCondition($holidayCondition))->toBeTrue();

    Carbon::setTestNow(); // Reset
});

test('it evaluates SCHEDULE_CRON', function () {
    if (! class_exists(\Cron\CronExpression::class)) {
        $this->markTestSkipped('CronExpression class not found');
    }

    $condition = new SettingRuleCondition([
        'type' => 'date_time',
        'attribute' => 'now',
        'operator' => ComparisonOperator::SCHEDULE_CRON,
        'value' => '0 0 * * *', // Daily at midnight
    ]);

    Carbon::setTestNow(Carbon::parse('2024-01-01 00:00:00'));
    expect($this->evaluator->evaluateCondition($condition))->toBeTrue();

    Carbon::setTestNow(Carbon::parse('2024-01-01 00:01:00'));
    expect($this->evaluator->evaluateCondition($condition))->toBeFalse();

    Carbon::setTestNow(); // Reset
});
