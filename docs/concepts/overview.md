---
title: Core Concepts
description: Understanding Fulcrum's architecture and mental model
---

# Core Concepts

Fulcrum manages **settings** whose values can vary based on **rules**. Rules contain **conditions** that match against a **scope** (typically a user). When multiple rules match, **priority** determines which rule is applied.

## The Resolution Flow

```
Resolution Request
  Fulcrum::get('setting.key', $user)
          |
          v
Load Setting
  key: 'setting.key'
  type: 'integer'
  default_value: 100
          |
          v
Evaluate Rules (by priority)
  Rule: "Premium users" (priority: 1)
    Conditions: user.subscription = 'premium'
    Value: 500
    Result: no match
  Rule: "Beta testers" (priority: 2)
    Conditions: segment IN 'beta'
    Value: 250
    Result: match -> return 250
          |
          v
Final Value: 250
```

## Key Entities

| Entity | Purpose |
|--------|---------|
| **Setting** | A named configuration value with a type and default |
| **Rule** | A conditional override with priority |
| **Condition** | A single comparison (field + operator + value) |
| **Rollout Variant** | A weighted value for percentage-based distribution |

## Additional Concepts

### Context and Scope

Rules are evaluated against a **context** (often a user, tenant, or custom data). Context determines which conditions match and therefore which value is returned.

### Defaults and Overrides

Every setting has a default value. Rules and rollouts are **overrides** that only apply when their conditions match the current context.

### Priority and Conflicts

When multiple rules match, **priority** decides which one wins. Higher-priority rules take precedence over lower-priority ones.

### Rollouts and Variants

Rollouts distribute values across a population. Variants allow A/B-style splits where different groups receive different values.

### Types and Validation

Each setting has a declared type. Types provide safety and enable validation so invalid values never reach your application.

### Immutability

Immutable settings are locked against runtime changes. They can still be read and resolved, but their values are intended to be changed only through controlled, versioned updates.

### Masked Values

Some settings are sensitive. Masking protects these values at rest and requires explicit authorization to reveal them.

[Learn about Settings ->](settings)
