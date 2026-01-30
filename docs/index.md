---
title: Fulcrum Settings
description: Advanced feature flags and configuration management for Laravel with rule-based targeting
---

# Fulcrum Settings

Rule-based feature flags and dynamic configuration for Laravel applications.

## Why Fulcrum?

Fulcrum unifies static configuration and feature flags in one system, so you can treat every setting as a first-class, type-safe value that can still be targeted at runtime. It is built for teams who want to ship gradual rollouts, A/B tests, and tenant-specific overrides without leaving Laravel.

It bridges the gap between simple settings libraries and standalone flag tools by giving you a single, consistent API with a powerful rules engine, optional masking for secrets, and first-class multi-tenancy support.

## Key Features

- **Rule-based targeting** -- Target users by attributes, segments, geography, device, or time
- **Percentage rollouts** -- Gradual releases with consistent user bucketing
- **A/B testing** -- Multi-variant experiments with weight distribution
- **Class-based settings** -- Type-safe settings with IDE autocompletion
- **Multi-tenancy** -- Tenant-isolated configuration out of the box
- **Pennant integration** -- Drop-in replacement for Laravel Pennant's driver

## Quick Example

```php
use GaiaTools\FulcrumSettings\Facades\Fulcrum;

// Resolve a setting for the current user
$limit = Fulcrum::get('api.rate_limit'); // 100

// With explicit scope
$limit = Fulcrum::get('api.rate_limit', scope: $user); // 500 (premium tier)

// Class-based approach
$settings = app(ApiSettings::class);
$settings->rate_limit; // 500
```

## Requirements

- PHP 8.2+
- Laravel 11.x or 12.x

[Get Started ->](getting-started/overview)
