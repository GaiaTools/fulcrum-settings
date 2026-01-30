# API Reference

Full API reference for the Laravel Fulcrum package.

## Fulcrum Facade

`GaiaTools\FulcrumSettings\Facades\Fulcrum`

### Core Methods

#### `resolve(string $key, mixed $scope = null): mixed`
Resolve a setting value for the given context. Returns `null` if the setting does not exist.

#### `get(string $key, mixed $default = null): mixed`
Retrieve a setting value for the current context.

#### `isActive(string $key): bool`
Check if a boolean feature flag is active for the current context.

#### `set(string $key, mixed $value): void`
Set a global or tenant-specific value for a setting.
Use `forTenant()` to scope the write to a specific tenant.

### Context Methods

#### `forUser(Model $user): self`
Set the user for the evaluation context.

#### `forTenant(string|int|null $tenantId): self`
Set the tenant ID for the evaluation context.

#### `reveal(bool $reveal = true): self`
Allow masked values to be revealed for the current request.

#### `isMultiTenancyEnabled(): bool`
Check if multi-tenancy is enabled.

## Setting Model

`GaiaTools\FulcrumSettings\Models\Setting`

### Methods

#### `rules(): HasMany`
Relationship to the `SettingRule` model.

#### `defaultValue(): MorphOne`
Relationship to the default `SettingValue` model.

#### `getDefaultValue(): mixed`
Get the default value, honoring masking.

## SettingBuilder

`GaiaTools\FulcrumSettings\Support\Builders\SettingBuilder`

Used to define settings programmatically outside of migrations.

### Methods

#### `description(string $description): self`
Add a description to the setting.

#### `string(): self`, `boolean(): self`, `integer(): self`, `float(): self`, `json(): self`
Set the data type of the setting.

#### `type(string $customType): self`
Set a custom data type.

#### `default(mixed $value): self`
Set the default value.

#### `rule(Closure $callback): self`
Define a rule for the setting.

#### `masked(): self`
Mark the setting as masked (encrypted).

#### `immutable(): self`
Mark the setting as immutable.

#### `save(): Setting`
Persist the setting and its rules.

## Exceptions

#### `SettingNotFoundException`
Thrown when trying to access a non-existent setting and no default is provided.

#### `InvalidSettingValueException`
Thrown when trying to set a value that fails validation or type handling.

#### `ImmutableSettingException`
Thrown when trying to modify a setting marked as immutable.
