---
title: Testing
description: Strategies for testing settings, rules, and rollouts
---

# Testing

Testing Fulcrum integrations is mostly about controlling the evaluation context and database state.

## Seed Settings in Tests

Use migrations or factories to seed settings before assertions.

```php
$this->artisan('make:setting-migration create_test_flags');
```

In practice, most projects use dedicated test migrations or direct model creation in `setUp()`.

## Control the Context

Use explicit scopes and context to ensure deterministic tests:

```php
use GaiaTools\FulcrumSettings\Support\FulcrumContext;

FulcrumContext::set('country', 'US');
$value = Fulcrum::get('discount_percent');
```

```php
$value = Fulcrum::get('feature.new_dashboard', scope: $user);
```

## Rollout Determinism

Provide a stable scope identifier in rollout tests so bucket assignment does not change.

```php
$value = Fulcrum::get('feature.experiment', scope: 'user_123');
```

## Database Reset

Because settings are stored in the database, ensure your test database is refreshed between runs.
