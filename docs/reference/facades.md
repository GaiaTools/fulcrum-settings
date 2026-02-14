---
title: Facade Methods
description: Fulcrum facade methods and signatures
---

# Facade Methods

The `Fulcrum` facade is a shortcut for the `SettingResolver` service.

`GaiaTools\FulcrumSettings\Facades\Fulcrum`

## Core Methods

### `resolve(string $key, mixed $scope = null): mixed`

Resolve a setting value by evaluating rules. Returns `null` if the setting is not found.

**Parameters**
- `key`: Setting key (for example `feature.new_dashboard`).
- `scope`: Optional scope for rollouts and rule evaluation (user, model, array, or scalar).

**Returns**
- The resolved value, or `null` when no setting exists.

**Example**
```php
$value = Fulcrum::resolve('feature.new_dashboard', $user);
```

### `get(string $key, mixed $default = null, mixed $scope = null): mixed`

Resolve a setting value, falling back to the provided default if not found.

**Parameters**
- `key`: Setting key.
- `default`: Fallback value when the setting does not exist.
- `scope`: Optional scope for rollouts and rule evaluation.

**Returns**
- The resolved value, or the default when missing.

**Example**
```php
$value = Fulcrum::get('feature.new_dashboard', default: false, scope: $user);
```

### `isActive(string $key, mixed $scope = null): bool`

Convenience method for boolean settings.

**Parameters**
- `key`: Setting key.
- `scope`: Optional scope for rollouts and rule evaluation.

**Returns**
- `true` if the resolved value is truthy, otherwise `false`.

**Example**
```php
if (Fulcrum::isActive('feature.new_dashboard', $user)) {
    // ...
}
```

### `set(string $key, mixed $value): void`

Set a setting's default value in the database.

**Parameters**
- `key`: Setting key.
- `value`: New default value.

**Throws**
- `SettingNotFoundException` when the setting does not exist.
- `InvalidSettingValueException` when the value fails type validation.

**Example**
```php
Fulcrum::set('feature.new_dashboard', true);
```

### `reveal(bool $reveal = true): self`

Allow masked values to be revealed for the current request (authorization still applies).

**Parameters**
- `reveal`: Whether to reveal masked values.

**Returns**
- The resolver instance for chaining.

**Example**
```php
$apiKey = Fulcrum::reveal()->get('billing.stripe_api_key');
```

## Context Methods

### `forUser(?Authenticatable $user): self`

Resolve settings as if the given user is authenticated.

**Parameters**
- `user`: The authenticated user (or `null` to clear).

**Returns**
- The resolver instance scoped to the user.

**Example**
```php
$value = Fulcrum::forUser($user)->get('feature.new_dashboard');
```

### `forTenant(?string $tenantId): self`

Resolve settings within a specific tenant scope.

**Parameters**
- `tenantId`: Tenant identifier (or `null` to clear).

**Returns**
- The resolver instance scoped to the tenant.

**Example**
```php
$value = Fulcrum::forTenant('tenant-123')->get('billing.tax_rate');
```

### `forGroup(?string $group): self`

Resolve settings within a specific group. When a group is set, keys without a dot are prefixed with the group (for example `site_name` becomes `general.site_name`).

**Parameters**
- `group`: Group name (or `null` to clear).

**Returns**
- The resolver instance scoped to the group.

**Example**
```php
$value = Fulcrum::forGroup('general')->get('site_name');
```

### `group(string $group): GroupedSettingResolver`

Create a grouped resolver for the given group. Use `all()` to fetch every setting in that group.

**Parameters**
- `group`: Group name (dot-separated for nested groups).

**Returns**
- A grouped resolver instance.

**Example**
```php
$links = Fulcrum::group('my_links')->all();
// ['twitter' => '...', 'facebook' => '...', 'reddit' => '...']
```

Chaining still works with scoping:

```php
$links = Fulcrum::forUser($user)->group('my_links')->all();
$links = Fulcrum::group('my_links')->forUser($user)->all();
```

### `isMultiTenancyEnabled(): bool`

Check if multi-tenancy is enabled.

**Returns**
- `true` when multi-tenancy is enabled in configuration.
