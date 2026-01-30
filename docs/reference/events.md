---
title: Events
description: Event classes and payloads emitted by Fulcrum
---

# Events

Fulcrum emits events during resolution, loading, saving, and rollouts.

## `SettingResolved`

**Class**: `GaiaTools\FulcrumSettings\Events\SettingResolved`

**Properties:**
- `key` (string)
- `value` (mixed)
- `setting` (Setting|null)
- `matchedRule` (SettingRule|null)
- `rulesEvaluated` (int)
- `source` (string)
- `tenantId` (string|null)
- `userId` (mixed)
- `scope` (array|null)
- `durationMs` (float)

## `SettingsLoaded`

**Class**: `GaiaTools\FulcrumSettings\Events\SettingsLoaded`

**Properties:**
- `settings` (array)

## `SettingsSaved`

**Class**: `GaiaTools\FulcrumSettings\Events\SettingsSaved`

**Properties:**
- `settings` (array)

## `LoadingSettings`

**Class**: `GaiaTools\FulcrumSettings\Events\LoadingSettings`

## `SavingSettings`

**Class**: `GaiaTools\FulcrumSettings\Events\SavingSettings`

**Properties:**
- `settings` (array)

## `VariantAssigned`

**Class**: `GaiaTools\FulcrumSettings\Events\VariantAssigned`

**Properties:**
- `settingKey` (string)
- `ruleName` (string)
- `variantName` (string)
- `value` (mixed)
- `identifier` (string)
- `bucket` (int)
- `setting` (Setting|null)
- `rule` (SettingRule|null)
- `variant` (SettingRuleRolloutVariant|null)
- `tenantId` (string|null)
- `context` (array)
