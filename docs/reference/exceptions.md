---
title: Exceptions
description: All exceptions thrown by Fulcrum and when they occur
---

# Exceptions

## `FulcrumException`

Base exception type for Fulcrum errors.

## `SettingNotFoundException`

Thrown when a requested setting does not exist and no default is provided.

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
