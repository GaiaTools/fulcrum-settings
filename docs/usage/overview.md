---
title: Usage Overview
description: When to define settings in the database, use settings classes, and runtime APIs
---

# Usage Overview

Fulcrum uses a single source of truth for settings: the database. Migrations, CLI commands, and imports create or update those definitions. Settings classes are typed accessors that map to one or more database settings and make them easier to use in code.

## Choose Your Approach

### Database Definitions (Migrations/CLI)

Best when settings are managed by operations teams, change frequently, or must be tenant-specific. See [Database Migrations](migrations).

### Settings Classes (Accessors)

Best when you want typed access, IDE autocomplete, and a single place to group related setting keys. See [Settings Classes](settings-classes).

## Runtime Resolution

Use the `Fulcrum` facade or dependency injection to resolve values in code. See [Resolving Values](resolving).

## Targeting

If settings need dynamic values based on context, use targeting rules. See [Targeting Overview](targeting/).
