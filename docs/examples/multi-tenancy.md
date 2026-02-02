# Example: Multi-Tenancy Setup

This example shows how to configure and use multi-tenancy in a SaaS application.

## Scenario
We have a SaaS where each "Organization" (tenant) can have its own "Max Projects" limit and "Primary Color" for branding.

## 1. Enable Multi-Tenancy

In `config/fulcrum.php`, enable multi-tenancy and define how to resolve the current organization.

```php
'multi_tenancy' => [
    'enabled' => true,
    'tenant_resolver' => function() {
        return request()->user()?->organization_id;
    },
],
```

## 2. Define Settings with Defaults

Create a migration to define the settings.

```php
public function up(): void
{
    // Global default: 5 projects
    $this->createSetting('max_projects')
        ->integer()
        ->default(5)
        ->save();

    // Global default: Blue
    $this->createSetting('brand_color')
        ->string()
        ->default('#0000FF')
        ->save();
}
```

## 3. Set Tenant-Specific Overrides

You can set these in a seeder or when an organization upgrades their plan.

```php
use GaiaTools\FulcrumSettings\Facades\Fulcrum;

// Organization 123 paid for more projects
Fulcrum::forTenant('123')->set('max_projects', 20);

// Organization 456 wants a red theme
Fulcrum::forTenant('456')->set('brand_color', '#FF0000');
```

## 4. Usage in Code

Fulcrum handles the resolution automatically based on the logged-in user's `organization_id`.

```php
// If logged in user belongs to org '123', returns 20.
// If logged in user belongs to org '456', returns 5.
// If not logged in, returns 5.
$limit = Fulcrum::get('max_projects');
```

## 5. Usage in a Blade Template

```html
<nav style="background-color: {{ \GaiaTools\FulcrumSettings\Facades\Fulcrum::get('brand_color') }}">
    <!-- ... -->
</nav>
```

## 6. Tenant-Specific Rules

You can also target specific users *within* a tenant. For example, enable a "Beta Feature" only for "Admin" users of a specific organization.

```php
use GaiaTools\FulcrumSettings\Database\Migrations\SettingMigration;

return new class extends SettingMigration
{
    public function up(): void
    {
        $this->createSetting('beta_feature')
            ->boolean()
            ->default(false)
            ->forTenant('123')
            ->rule(fn ($rule) => $rule
                ->name('Admin users')
                ->whereInSegment('role', 'admin')
                ->then(true)
            )
            ->save();
    }
};
```

## Summary
Fulcrum's multi-tenancy support allows you to manage thousands of tenants with unique configurations while still maintaining a clean set of global defaults.

## Next Steps
- [Multi-Tenancy](../multi-tenancy) - Deep dive into multi-tenancy concepts.
- [Targeting Rules](../targeting-rules) - Learn how to combine rules with tenant scoping.
