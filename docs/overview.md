# Overview

Laravel Fulcrum is a powerful feature flag and configuration management system for Laravel. It combines database-defined settings with typed settings classes and advanced targeting rules.

## Why Fulcrum?

Most configuration packages in the Laravel ecosystem either focus on simple settings (like `spatie/laravel-settings`) or feature flags (like `laravel/pennant`). Fulcrum is designed to bridge this gap, providing a unified system for both.

### Key Value Proposition

- **Unified System**: Manage both static configuration and dynamic feature flags in one place.
- **Rule-Based Targeting**: Go beyond simple boolean flags. Use complex rules to target users based on attributes, segments, geography, or custom context.
- **Type Safety**: Use strongly-typed settings classes as accessors for full IDE autocomplete and runtime validation.
- **Multi-Tenancy**: Built-in support for scoped settings in multi-tenant applications.
- **Masking & Security**: Store sensitive values encrypted in the database with controlled retrieval via Gate policies.
- **Developer First**: Fluent API, helpful migration tools, and deep integration with Laravel's core features.

## Core Concepts

### Settings
Settings are individual configuration items. They can be simple values (strings, integers, booleans) or complex value objects (Money, Address).

### Targeting Rules
Rules allow you to define when a setting value should change based on the evaluation context. For example, you can enable a feature only for "Beta Users" or change a "discount" setting based on the user's country.

### Context
Evaluation context provides the data used by rules. This typically includes the current user, their tenant, and any other attributes relevant to your business logic.

### Settings Classes
Inspired by Spatie's Laravel Settings, Fulcrum lets you map database-defined settings to PHP classes for type safety and organization.

## What's Next?

- [Quick Start](quick-start) - Get up and running in 5 minutes.
- [Installation](installation) - Learn how to install Fulcrum in your project.
- [Comparison Table](comparison) - See how Fulcrum compares to other solutions.
