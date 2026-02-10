---
name: fulcrum-settings
description: >
  Laravel Fulcrum Settings — rule-based feature flags and dynamic configuration for Laravel.
  Use this skill whenever the user mentions Fulcrum, feature flags, targeting rules, percentage rollouts,
  A/B testing variants, settings migrations, class-based settings, FulcrumSettings, the Fulcrum facade,
  setting types/handlers, masked settings, multi-tenancy settings, or any configuration management
  that involves conditional/contextual evaluation in a Laravel app. Also trigger when the user asks
  about creating setting migrations, defining rules or conditions, rollout strategies, segment-based
  targeting, geo-targeting, time-based rules, or importing/exporting settings. If the user references
  `gaiatools/fulcrum-settings`, `GaiaTools\FulcrumSettings`, or any Fulcrum artisan commands
  (`fulcrum:set`, `fulcrum:get`, `fulcrum:list`, `fulcrum:export`, `fulcrum:import`,
  `make:setting-migration`, `fulcrum:migrate-spatie`), use this skill.
---

# Fulcrum Settings Skill

Laravel Fulcrum is a feature flag and dynamic configuration package that unifies static settings
and rule-based feature flags behind a single, type-safe API with a powerful rules engine.

**Package**: `gaiatools/fulcrum-settings`
**Namespace**: `GaiaTools\FulcrumSettings`
**Requirements**: PHP 8.3+, Laravel 11.x / 12.x

## Table of Contents

1. [Core Concepts](#core-concepts)
2. [Facade API Quick Reference](#facade-api)
3. [Setting Migrations](#setting-migrations)
4. [Targeting Rules](#targeting-rules)
5. [Settings Classes](#settings-classes)
6. [Multi-Tenancy](#multi-tenancy)
7. [Masked Settings & Security](#masked-settings)
8. [Custom Types](#custom-types)
9. [Testing](#testing)
10. [Artisan Commands](#artisan-commands)

For detailed references, read these as needed:
- `references/targeting-rules.md` — Full operator list, condition types, rollout strategies
- `references/migrations-api.md` — Complete migration builder API and modification helpers
- `references/configuration.md` — All `config/fulcrum.php` options
- `references/contracts-events.md` — Interfaces, events, and exceptions

---

## Core Concepts

- **Settings** — Key-value pairs stored in the database with a type (string, boolean, integer, etc.)
- **Targeting Rules** — Conditions that override the default value based on evaluation context (user attributes, geo, time, segments)
- **Context** — The evaluation scope, typically the authenticated user, but can include tenant, geo, user agent, or custom attributes
- **Settings Classes** — Typed PHP accessors (like Spatie settings) that map properties to database keys via `#[SettingProperty]`
- **Rollouts** — Percentage-based feature delivery using consistent hashing for stable bucketing

### Resolution Order

1. Evaluate rules in ascending priority order (lowest number = highest priority)
2. First matching rule wins and returns its value
3. If no rules match, return the setting's default value
4. If no setting exists, return the `$default` parameter (or `null`)

---

## Facade API

```php
use GaiaTools\FulcrumSettings\Facades\Fulcrum;

// Get a setting value (with optional default and scope)
$value = Fulcrum::get('api.rate_limit', default: 100, scope: $user);

// Boolean convenience
if (Fulcrum::isActive('feature.new_dashboard')) { /* ... */ }

// Resolve without default (returns null if missing)
$value = Fulcrum::resolve('feature.experiment', $user);

// Set a default value programmatically
Fulcrum::set('maintenance_mode', true);

// Scoped resolution
$value = Fulcrum::forUser($user)->get('theme');
$value = Fulcrum::forTenant('tenant-123')->get('max_projects');

// Reveal masked (encrypted) settings
$apiKey = Fulcrum::reveal()->get('stripe_api_key');
```

### Context Overrides

```php
use GaiaTools\FulcrumSettings\Support\FulcrumContext;

FulcrumContext::set('browser', 'chrome');
$value = Fulcrum::isActive('modern_ui');
```

---

## Setting Migrations

Generate a migration stub:

```bash
php artisan make:setting-migration create_feature_flag
```

### Create a Setting

```php
use GaiaTools\FulcrumSettings\Database\Migrations\SettingMigration;

return new class extends SettingMigration
{
    public function up(): void
    {
        $this->createSetting('feature.new_dashboard')
            ->description('Enable the new dashboard UI')
            ->boolean()
            ->default(false)
            ->save();
    }

    public function down(): void
    {
        $this->deleteSetting('feature.new_dashboard');
    }
};
```

### Supported type helpers

`string()`, `integer()`, `float()`, `boolean()`, `array()`, `json()`, `type('custom_type_name')`

### Additional options

- `masked()` — Encrypt and mask the value
- `immutable()` — Prevent modification unless forced
- `forTenant($tenantId)` — Tenant-specific value

### Inline Rules

```php
$this->createSetting('discount_percent')
    ->integer()
    ->default(5)
    ->rule(fn ($rule) => $rule
        ->name('Premium Customers')
        ->priority(10)
        ->whereInSegment('segment', 'premium')
        ->then(20)
    )
    ->save();
```

### Modify Existing Settings

```php
$this->modifySetting('feature.new_dashboard')
    ->updateDescription('Updated description')
    ->updateDefault(true)
    ->apply();
```

### Manage Rules on Existing Settings

```php
$this->addRule('feature.new_dashboard', function ($rule) {
    $rule->name('Internal Testers')
        ->whereEquals('is_internal', true)
        ->then(true);
});

$this->modifyRule('setting_key', 'Rule Name', function ($rule) {
    $rule->priority(5);
});

$this->deleteRule('setting_key', 'Rule Name');
$this->clearRules('setting_key');
```

### Upsert (idempotent create)

```php
$this->upsert('emergency_banner', function ($setting) {
    $setting->string()->default('')->description('Site-wide emergency message');
});
```

> For the full migration builder API including condition/variant management,
> read `references/migrations-api.md`.

---

## Targeting Rules

Rules consist of **conditions** (AND-combined by default), a **value** to return on match, and a **priority**.

### Condition Types

| Type | Resolves From | Example Fields |
|------|--------------|----------------|
| `user` (default) | Authenticated user / explicit scope | `id`, `email`, `subscription.plan` |
| `geocoding` | Configured geo resolver | `country`, `city`, `region` |
| `user_agent` | User-agent resolver | `browser`, `os`, `device` |
| `date_time` | Configured clock/timezone | `now`, `day_of_week` |

### Common Condition Helpers

```php
$rule->whereEquals('country', 'US')
     ->whereNotEquals('status', 'banned')
     ->whereInSegment('segment', 'beta')
     ->whereContainsAny('tags', ['vip', 'premium'])
     ->whereNumberGreaterThan('order_count', 5)
     ->whereDateBefore('trial_end', '2025-06-01')
     ->whereVersionGreaterThanOrEqual('app_version', '2.0.0')
     ->when('custom_field', 'matches_regex', '/^admin/')
     ->whenType('geocoding', 'country', 'equals', 'UK')   // explicit condition type
     ->between('2025-12-01', '2025-12-31')                 // time window shorthand
     ->then($value);
```

### Percentage Rollouts

```php
$rule->name('10% rollout')
    ->rollout(fn ($r) => $r->variant('enabled', 10, true));

// Gradual helper
$rule->rollout(fn ($r) => $r->gradual(10, true));
```

### A/B Test Variants

```php
$rule->name('Button color test')
    ->rollout(fn ($r) => $r
        ->variant('control', 50, 'blue')
        ->variant('experiment_a', 25, 'green')
        ->variant('experiment_b', 25, 'red')
    );

// 50/50 helper
$rule->rollout(fn ($r) => $r->fiftyFifty('old_value', 'new_value'));
```

Rollout bucketing uses the authenticated user's ID by default. Override with the `scope` parameter or a custom `identifier_resolver` in config.

> For the full operator reference, read `references/targeting-rules.md`.

---

## Settings Classes

Typed accessors that map properties to database-defined settings via `#[SettingProperty]`.

### Define the Class

```php
namespace App\Settings;

use GaiaTools\FulcrumSettings\Attributes\SettingProperty;
use GaiaTools\FulcrumSettings\Support\Settings\FulcrumSettings;

class GeneralSettings extends FulcrumSettings
{
    #[SettingProperty(key: 'general.site_name')]
    protected string $siteName;

    #[SettingProperty(key: 'general.maintenance_mode')]
    protected bool $maintenanceMode;

    #[SettingProperty(key: 'general.pagination_limit', default: 15)]
    protected int $paginationLimit;
}
```

### Use via DI

```php
public function index(GeneralSettings $settings)
{
    return view('welcome', ['siteName' => $settings->siteName]);
}
```

### Save Changes (dirty-aware)

```php
$settings->maintenanceMode = true;
$settings->save();
```

### Attribute Options

| Option | Type | Purpose |
|--------|------|---------|
| `key` | string | Maps to the database setting key |
| `default` | mixed | Local fallback if setting is null |
| `cast` | string | Type handler name when PHP type isn't enough |
| `rules` | array | Laravel validation rules for save |
| `readOnly` | bool | Prevent writes |
| `lazy` | bool | Defer resolution until first access |
| `tenantScoped` | bool | Resolve within tenant context |

### Auto-Discovery

Settings classes in `app/Settings/` are auto-discovered by default. Configure in `config/fulcrum.php` under `settings.discovery`.

---

## Multi-Tenancy

Enable in `config/fulcrum.php`:

```php
'multi_tenancy' => [
    'enabled' => true,
    'tenant_resolver' => fn () => auth()->user()?->tenant_id,
],
```

### Tenant-Specific Values

```php
// In migration
$this->createSetting('max_projects')
    ->integer()->default(5)->forTenant('tenant-123')->save();

// Via facade
Fulcrum::forTenant('tenant-123')->set('max_projects', 20);

// Resolution is automatic based on the resolved tenant
$limit = Fulcrum::get('max_projects'); // tenant-aware
```

### Fallback: tenant-specific → global default.

---

## Masked Settings

For sensitive values (API keys, secrets):

```php
// Migration
$this->createSetting('stripe_api_key')->string()->masked()->save();

// Reading (returns MaskedValue placeholder '********')
$key = Fulcrum::get('stripe_api_key');

// Revealing (requires Gate authorization in web context)
$key = Fulcrum::reveal()->get('stripe_api_key');
```

Gate ability: `viewSettingValue` (configurable). CLI/Tinker auto-reveals without Gate checks.

---

## Custom Types

Extend Fulcrum with value objects by creating a `SettingTypeHandler`:

```php
use GaiaTools\FulcrumSettings\Types\SettingTypeHandler;

class MoneyHandler extends SettingTypeHandler
{
    public function get(mixed $value): Money { /* deserialize */ }
    public function set(mixed $value): string { /* serialize */ }
    public function validate(mixed $value): bool { /* type check */ }
}
```

Register in `config/fulcrum.php`:

```php
'types' => [
    'money' => App\Settings\Types\MoneyHandler::class,
],
```

Use with `->type('money')` in migrations or `cast: 'money'` in `#[SettingProperty]`.

---

## Testing

```php
use GaiaTools\FulcrumSettings\Facades\Fulcrum;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;

// Control context
FulcrumContext::set('country', 'US');
$value = Fulcrum::get('discount_percent');

// Explicit scope for deterministic rollouts
$value = Fulcrum::get('feature.experiment', scope: 'user_123');

// Freeze time for date-based rules
Carbon::setTestNow('2025-11-29 12:00:00');
$value = Fulcrum::get('discount_percent');
Carbon::setTestNow();
```

Ensure test database is refreshed between runs (settings are database-stored).

---

## Artisan Commands

| Command | Purpose |
|---------|---------|
| `make:setting-migration {name}` | Generate a setting migration stub |
| `fulcrum:set {key} {value?}` | Set a value (omit value for interactive wizard) |
| `fulcrum:get {key}` | Resolve a setting value |
| `fulcrum:list` | List all settings |
| `fulcrum:export` | Export settings (json, yaml, csv, xml, sql) |
| `fulcrum:import {path}` | Import settings from file |
| `fulcrum:migrate-spatie` | Migrate from spatie/laravel-settings |

Common flags: `--tenant=`, `--reveal`, `--scope=`, `--force`, `--masked`, `--type=`, `--dry-run`

> For full command options, read `references/configuration.md`.

---

## Installation Checklist

```bash
composer require gaiatools/fulcrum-settings
php artisan vendor:publish --provider="GaiaTools\FulcrumSettings\FulcrumSettingsServiceProvider" --tag="config"
php artisan migrate
```

Then configure drivers (segment, geo, tenant) in `config/fulcrum.php` as needed.
