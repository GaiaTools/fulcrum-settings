---
title: Exceptions
description: All exceptions thrown by Fulcrum and when they occur
---

# Exceptions

## `FulcrumException`

Base exception type for Fulcrum errors.

## `SettingNotFoundException`

Thrown when a requested setting does not exist and no default is provided.

## `DuplicateSettingException`

Thrown when creating a setting whose key already exists (optionally within a tenant). Extends `RuntimeException`.

## `InvalidImportDataException`

Thrown during import when the source data is malformed or fails validation. Extends `RuntimeException`.

## `MissingConditionTypeHandlerException`

Thrown when a condition type has no registered handler. Extends `RuntimeException`.

## `InvalidConditionTypeHandlerException`

Thrown when a registered condition type handler class does not exist or is misconfigured. Extends `InvalidArgumentException`.

## `InvalidSettingValueException`

Thrown when a value fails validation or type handling.

## `InvalidTypeHandlerException`

Thrown when a registered type handler is invalid or misconfigured.

## `MissingTypeHandlerException`

Thrown when a setting type cannot be resolved to a handler.

## `ImmutableSettingException`

Thrown when attempting to modify or delete an immutable setting without force override.

## `PennantException`

Thrown when Pennant integration is misconfigured or disabled.
