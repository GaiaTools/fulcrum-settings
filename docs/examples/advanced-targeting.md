# Example: Advanced Targeting Rules

This example demonstrates how to use multiple conditions, percentage rollouts, and priorities in your targeting rules.

## Scenario
We are running a Black Friday promotion. We want to:
1. Give a **20% discount** to "VIP" users in the "US".
2. Give a **15% discount** to 50% of our "Beta" users (as an experiment).
3. Give a **10% discount** to everyone else during the sale period.
4. Default to **0% discount** otherwise.

## 1. Define the Setting

```php
use GaiaTools\FulcrumSettings\Database\Migrations\SettingMigration;

return new class extends SettingMigration
{
    public function up(): void
    {
        $this->createSetting('discount_percent')
            ->integer()
            ->default(0)
            ->save();
    }
};
```

## 2. Define the Rules (High Priority First)

### Rule 1: VIP users in the US (Priority 1)
```php
use GaiaTools\FulcrumSettings\Database\Migrations\SettingMigration;

return new class extends SettingMigration
{
    public function up(): void
    {
        $this->addRule('discount_percent', function ($rule) {
            $rule->name('VIP users in US')
                ->whereInSegment('segment', 'vip')
                ->whereEquals('country', 'US')
                ->then(20)
                ->priority(1);
        });
    }
};
```

### Rule 2: 50% of Beta Users (Priority 2)
```php
use GaiaTools\FulcrumSettings\Database\Migrations\SettingMigration;

return new class extends SettingMigration
{
    public function up(): void
    {
        $this->addRule('discount_percent', function ($rule) {
            $rule->name('Beta users 50% rollout')
                ->whereInSegment('segment', 'beta')
                ->rollout(fn ($rollout) => $rollout->variant('enabled', 50, 15))
                ->priority(2);
        });
    }
};
```

### Rule 3: Everyone during the sale (Priority 3)
```php
use GaiaTools\FulcrumSettings\Database\Migrations\SettingMigration;

return new class extends SettingMigration
{
    public function up(): void
    {
        $this->addRule('discount_percent', function ($rule) {
            $rule->name('Black Friday window')
                ->between('2025-11-28 00:00:00', '2025-12-01 23:59:59')
                ->then(10)
                ->priority(3);
        });
    }
};
```

## 3. How Evaluation Works

When `Fulcrum::get('discount_percent')` is called:

1. **Check Rule 1**: Is the user a VIP AND in the US? If yes, return **20**.
2. **Check Rule 2**: Is the user in the Beta segment? If yes, apply 50% rollout logic. If they fall into the 50% bucket, return **15**.
3. **Check Rule 3**: Is the current time within the Black Friday window? If yes, return **10**.
4. **Default**: If no rules match, return the default value **0**.

## 4. Testing the Rules

You can test how rules will evaluate for different contexts without changing your session:

```php
use GaiaTools\FulcrumSettings\Facades\Fulcrum;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use Illuminate\Support\Carbon;

// Test for a VIP user in the US (segment rules require a user scope)
FulcrumContext::set('country', 'US');
$val = Fulcrum::forUser($vipUser)->get('discount_percent'); // 20

// Test the sale window by freezing time
Carbon::setTestNow('2025-11-29 12:00:00');
$val = Fulcrum::get('discount_percent'); // 10
Carbon::setTestNow(); // reset
```

## Summary
By combining priorities and multiple conditions, you can create very sophisticated targeting logic that is evaluated efficiently on every request.

## Next Steps
- [Targeting Rules](../targeting-rules) - Deep dive into available operators.
- [Integrations: Carbon](../integrations/carbon-integration) - Learn more about time-based rules.
