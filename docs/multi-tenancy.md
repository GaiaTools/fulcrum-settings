# Multi-Tenancy

Laravel Fulcrum has first-class support for multi-tenant applications, allowing you to scope settings and rules to specific tenants.

## Enabling Multi-Tenancy

Multi-tenancy is disabled by default. To enable it, update your `config/fulcrum.php` file:

```php
'multi_tenancy' => [
    'enabled' => true,
    'tenant_resolver' => function() {
        return auth()->user()?->tenant_id;
    },
],
```

The `tenant_resolver` should return the ID of the current tenant or `null`.

## Scoping Settings

When multi-tenancy is enabled, you can define settings that are specific to a tenant.

### In Migrations

You can set a tenant-specific value during initialization:

```php
// Global default
$this->createSetting('max_projects')
    ->integer()
    ->default(5)
    ->save();

// Tenant-specific override
$this->createSetting('max_projects')
    ->integer()
    ->default(10)
    ->forTenant('tenant-123')
    ->save();
```

### Via the Facade

When retrieving a setting, Fulcrum will automatically use the resolved tenant ID to look for overrides.

```php
// If current tenant is 'tenant-123', this returns 10. Otherwise, returns 5.
$limit = Fulcrum::get('max_projects');
```

You can also explicitly specify a tenant:

```php
$limit = Fulcrum::forTenant('another-tenant')->get('max_projects');
```

## Scoping Targeting Rules

Targeting rules can also be scoped to tenants. A rule scoped to a tenant will only be evaluated for that tenant.

```php
use GaiaTools\FulcrumSettings\Database\Migrations\SettingMigration;

return new class extends SettingMigration
{
    public function up(): void
    {
        $this->createSetting('new_feature')
            ->boolean()
            ->default(false)
            ->forTenant('tenant-123')
            ->rule(fn ($rule) => $rule
                ->name('Premium users')
                ->whereInSegment('segment', 'premium')
                ->then(true)
            )
            ->save();
    }
};
```

## Global vs. Tenant Settings

1. **Tenant-Specific Value**: If a value exists for the specific key and tenant ID, it is used.
2. **Global Value**: If no tenant-specific value exists, the global value (where `tenant_id` is null) is used as a fallback.

## Implementation Details

Fulcrum uses a `tenant_id` column in the `settings`, `setting_values`, `setting_rules`, `setting_rule_conditions`, and `setting_rule_rollout_variants` tables to manage scoping. When multi-tenancy is enabled, a global scope is applied to these models to filter by the current tenant ID (unless customized in configuration).

## Next Steps

- [Example: Multi-Tenancy](examples/multi-tenancy) - See a complete multi-tenant setup.
- [Usage Guide](usage) - Learn more about the evaluation context.
