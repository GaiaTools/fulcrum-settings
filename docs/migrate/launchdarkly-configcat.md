---
title: Migrating from LaunchDarkly or ConfigCat
description: Map SaaS feature flags to Fulcrum settings and targeting rules
---

# Migrating from LaunchDarkly or ConfigCat

This guide explains how to move feature flags from SaaS platforms to Fulcrum's database-backed settings and rule engine.

If you are moving away from external vendors, Fulcrum is the self-hosted alternative that keeps evaluation inside your Laravel app, with no per-request SDK costs and full control over data residency.

## Key Differences

- **Storage**: Fulcrum stores flags in your database (no external API calls).
- **Definition**: Flags are defined via migrations or database tools, not SaaS dashboards.
- **Evaluation**: Fulcrum evaluates rules locally using your context data.

## Feature Parity Comparison

| Capability | LaunchDarkly / ConfigCat | Fulcrum |
| --- | --- | --- |
| Boolean flags | Yes | Yes |
| Multivariate flags | Yes | Yes |
| Percentage rollouts | Yes | Yes |
| Targeting rules | Yes | Yes |
| Segments | Yes | Yes (context attributes + rule sets) |
| Webhooks / SDK streaming | Yes | Not needed (local evaluation) |
| Dashboard management | Yes | Optional (DB + migrations today) |
| Non-Laravel SDKs | Yes | No (Laravel-native) |

## Step 1: Inventory Flags

Export a list of flags and their variations from your SaaS tool and decide:

- The Fulcrum key (use a stable, dot-delimited key).
- The default value (maps to Fulcrum's default value).
- Any rollout rules or targeting rules.

## Step 2: Create Fulcrum Settings

Use a setting migration to define each flag and its default value:

```php
use GaiaTools\FulcrumSettings\Database\Migrations\SettingMigration;

return new class extends SettingMigration
{
    public function up(): void
    {
        $this->createSetting('feature.new_checkout')
            ->boolean()
            ->default(false)
            ->description('Enable new checkout flow')
            ->save();
    }
};
```

## Step 3: Map Targeting Rules

Translate your SaaS targeting rules into Fulcrum rules. Examples:

| SaaS Rule | Fulcrum Equivalent |
| --- | --- |
| User ID in list | `whereNumberEquals('id', 123)` or `whereEquals('id', 'user-123')` |
| Email ends with `@company.com` | `whereEndsWithAny('email', ['@company.com'])` |
| Percentage rollout | `rollout()` with variants |
| Segment/role targeting | `whereInSegment('segment', 'beta')` |
| Country in list | `whereIn('country', ['US', 'CA'])` |
| Plan equals | `whereEquals('plan', 'pro')` |
| Date after | `whereDateAfter('trial_ends_at', now())` |

Example rule:

```php
use GaiaTools\FulcrumSettings\Database\Migrations\SettingMigration;

return new class extends SettingMigration
{
    public function up(): void
    {
        $this->addRule('feature.new_checkout', function ($rule) {
            $rule->name('Beta users')
                ->whereInSegment('segment', 'beta')
                ->then(true);
        });
    }
};
```

## Step 4: Update Runtime Calls

Replace your SDK calls with Fulcrum's facade:

```php
use GaiaTools\FulcrumSettings\Facades\Fulcrum;

if (Fulcrum::isActive('feature.new_checkout', scope: $user)) {
    // enable feature
}
```

For non-boolean flags, use `Fulcrum::get()` with a default value.

### SDK Call Transformations

Common mappings from SaaS SDKs to Fulcrum:

**LaunchDarkly (PHP/JS style)**
```php
// LaunchDarkly
$value = $ldClient->variation('feature.new_checkout', $user, false);

// Fulcrum
$value = Fulcrum::get('feature.new_checkout', default: false, scope: $user);
```

```js
// LaunchDarkly JS
const enabled = client.variation('feature.new_checkout', user, false);
```
```php
// Fulcrum (server-side)
$enabled = Fulcrum::isActive('feature.new_checkout', scope: $user);
```

**ConfigCat (PHP style)**
```php
// ConfigCat
$value = $client->getValue('feature.new_checkout', false, $user);

// Fulcrum
$value = Fulcrum::get('feature.new_checkout', default: false, scope: $user);
```

## Step 5: Validate Context Attributes

Fulcrum rules match against the data in the evaluation context. Make sure your context includes all attributes your rules need (for example `email`, `plan`, or custom fields).

Use `FulcrumContext::set()` or pass a scope object/array when resolving settings.

## Step 6: Estimate Cost Savings

A simple comparison:

```
SaaS Cost (monthly) = seats + flag evaluation requests + add-ons
Fulcrum Cost (monthly) = database + cache + any extra infrastructure
Savings = SaaS Cost - Fulcrum Cost
```

Plug in your current numbers to quantify savings.

### Cost Savings Worksheet

```text
Seats:              $____
Evaluations/mo:     $____
Add-ons:            $____
SaaS Total:         $____

DB + Cache:         $____
Infra overhead:     $____
Fulcrum Total:      $____

Estimated Savings:  $____
```

## Notes on Feature Parity

Fulcrum supports:
- boolean and multivariate flags
- percentage rollouts and variants
- contextual targeting (user, segment, geo, device, time)
- multi-tenancy

SaaS dashboards and non-Laravel SDKs are not provided; Fulcrum is Laravel-native and managed inside your application.
