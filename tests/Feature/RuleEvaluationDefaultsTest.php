<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Feature;

use GaiaTools\FulcrumSettings\Enums\ComparisonOperator;
use GaiaTools\FulcrumSettings\Enums\SettingType;
use GaiaTools\FulcrumSettings\Facades\Fulcrum;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Models\SettingRule;
use GaiaTools\FulcrumSettings\Tests\TestCase;
use Illuminate\Foundation\Auth\User;

class RuleEvaluationDefaultsTest extends TestCase
{
    public function test_missing_attribute_does_not_match_rule(): void
    {
        $setting = Setting::create([
            'key' => 'rule.missing.attribute',
            'type' => SettingType::STRING,
        ]);
        $setting->defaultValue()->create([
            'valuable_type' => Setting::class,
            'valuable_id' => $setting->id,
            'value' => 'default',
        ]);

        $rule = $setting->rules()->create(['priority' => 1]);
        $rule->conditions()->create([
            'attribute' => 'env',
            'operator' => ComparisonOperator::NOT_EQUALS,
            'value' => 'production',
        ]);
        $rule->value()->create([
            'valuable_type' => SettingRule::class,
            'valuable_id' => $rule->id,
            'value' => 'rule-value',
        ]);

        $this->assertSame('default', Fulcrum::get('rule.missing.attribute'));
    }

    public function test_default_resolution_uses_authenticated_user(): void
    {
        $user = new User();
        $user->forceFill([
            'id' => 10,
            'email' => 'user@company.com',
        ]);
        $this->actingAs($user);

        $setting = Setting::create([
            'key' => 'rule.default.user',
            'type' => SettingType::STRING,
        ]);
        $setting->defaultValue()->create([
            'valuable_type' => Setting::class,
            'valuable_id' => $setting->id,
            'value' => 'default',
        ]);

        $rule = $setting->rules()->create(['priority' => 1]);
        $rule->conditions()->create([
            'attribute' => 'email',
            'operator' => ComparisonOperator::ENDS_WITH_ANY,
            'value' => ['@company.com'],
        ]);
        $rule->value()->create([
            'valuable_type' => SettingRule::class,
            'valuable_id' => $rule->id,
            'value' => 'matched',
        ]);

        $this->assertSame('matched', Fulcrum::get('rule.default.user'));
    }
}
