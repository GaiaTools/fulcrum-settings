---
title: Settings
description: What settings are, available types, and how they behave
---

# Settings

A **setting** is a named configuration value that Fulcrum can resolve dynamically based on context. Every setting has a key, a type, and a default value. Rules can override that default when their conditions match.

## Setting Keys

Settings use dot notation to group related values:

- `feature.new_dashboard`
- `billing.max_seats`
- `general.site_name`

Keys are unique across your application, regardless of how the setting is created (migrations, CLI, imports).

## Types

Fulcrum ships with built-in types and lets you register custom types for value objects.

Built-in types:

- `string`
- `boolean`
- `integer`
- `float`
- `json`
- `array`
- `carbon` (for `Carbon` instances)

For custom value objects, see [Custom Type Handlers](../custom-types).

## Default Values

The default value is returned when no rules match. Defaults are stored in the database and can be set via migrations or the CLI.

## Scoped Values

Settings can be scoped to a tenant when multi-tenancy is enabled. Tenant values override global defaults.

## Lifecycle

1. **Defined** in the database via migrations, CLI, or imports
2. **Stored** in Fulcrum tables
3. **Resolved** at runtime using rules and context
4. **Updated** via migrations, CLI, or programmatic APIs

Settings classes do not create settings; they provide typed accessors for existing keys.

## Related Reading

- [Rules & Conditions](../condition-logic)
- [Usage Overview](../usage/overview)
- [Multi-Tenancy](../multi-tenancy)
