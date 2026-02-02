---
title: Best Practices
description: Recommended patterns for naming, rules, and rollout workflows
---

# Best Practices

These guidelines help keep settings predictable and maintainable as your application grows.

## Naming Conventions

- Use dot-delimited namespaces: `feature.new_dashboard`, `billing.tax_rate`.
- Keep keys stable over time to preserve history and avoid data churn.
- Use descriptive names for rules (for example, `Beta users`, `Holiday promo`).

## Prefer Migrations for Production Changes

Migrations provide versioned, reviewable changes and are safer than ad-hoc runtime updates.

- Use `make:setting-migration` to define new settings.
- Use `modifySetting()` or `addRule()` for updates.
- Reserve `fulcrum:set` for local dev or emergency overrides.

## Keep Rules Focused

- Avoid packing unrelated logic into a single setting.
- Use priority to keep common matches first (lower number = higher priority).
- Prefer explicit conditions over broad catch-alls to reduce surprises.

## Use Scope and Context Deliberately

- Pass stable scopes (user ID or tenant ID) for rollouts.
- When using `FulcrumContext`, set only the attributes needed for rules.
- For Pennant integrations, ensure your scope provides the attributes your rules expect.

## Multi-Tenancy Hygiene

- Keep tenant-specific values minimal; default to global values where possible.
- Use `forTenant()` for explicit overrides when running background tasks.
- Avoid expensive tenant resolvers in the request lifecycle.

## Masking and Immutability

- Mark secrets (API keys, tokens) as `masked` and require Gate permissions to reveal.
- Use `immutable` for critical defaults to prevent accidental changes.
- Only override immutability via `--force` when you are confident in the change.

## Testing Strategy

- Seed settings in test migrations or `setUp()`.
- Use stable scopes in rollout tests to keep buckets deterministic.
- Assert against both default values and rule overrides for critical settings.

## Migration Workflow (Dev -> Staging -> Prod)

1. Implement changes in a migration and review it in code.
2. Apply in staging and validate behavior with real data.
3. Promote the migration to production during a controlled release.
4. Only then remove legacy settings or packages.
