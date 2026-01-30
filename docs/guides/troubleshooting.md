---
title: Troubleshooting
description: Common issues and fixes when using Fulcrum
---

# Troubleshooting

Common issues and their solutions when working with Fulcrum.

## Configuration & Setup

### Settings table not found

**Issue**: You receive a `QueryException` stating that the `settings` table does not exist.

**Solution**: Ensure you have run the migrations: `php artisan migrate`.

### Custom type not resolved

**Issue**: A setting using a custom type returns a string or raw value instead of your object.

**Solution**:

- Ensure the custom type is registered in your service provider.
- Check that the type name in the migration matches the registered name.
- Verify that your handler's `get()` method is correctly implemented.

## Rule Evaluation

### Rule not matching as expected

**Issue**: A rule that should match is being ignored, or a different rule is winning.

**Solution**:

- **Check Priority**: Rules are evaluated in ascending order of their `priority` (lower numbers first).
- **Check Context**: Use `FulcrumContext::all()` to verify the data used for evaluation.
- **Operator Mismatch**: Ensure you are using the correct operator (for example `number_gte` vs `number_gt`).
- **Debugging**: Enable Telescope to inspect resolutions.

### Rollout buckets not consistent

**Issue**: A user gets different values for a percentage rollout on different requests.

**Solution**:

- Ensure you have a consistent identifier for the user (typically the `id` property).
- If evaluating for guests, provide a stable scope ID.

## Performance

### Slow setting resolution

**Issue**: Accessing settings adds significant latency to requests.

**Solution**:

- Enable caching in `config/fulcrum.php`.
- Reduce large rule sets.

## Cache Invalidation

### Cached value does not update

**Issue**: A setting returns an old value even after you change it.

**Solution**:

- Fulcrum's cached resolver does not automatically invalidate scoped cache keys.
- Clear the cache store manually (`php artisan cache:clear`) or change the cache prefix to bust keys.
- Use shorter cache TTLs when settings change frequently.

## Migration Edge Cases

### Spatie settings table not found

**Issue**: `fulcrum:migrate-spatie` cannot find the table.

**Solution**:

- The Fulcrum migration renames an existing Spatie `settings` table to `spatie_settings` if it detects Spatie's schema.
- Run `php artisan fulcrum:migrate-spatie --table=spatie_settings` if the table was renamed.

### Invalid JSON payloads

**Issue**: Individual settings fail to migrate with JSON errors.

**Solution**:

- The migration expects `payload` to be valid JSON; fix invalid rows before re-running.
- Use `--force` to overwrite settings if a partial migration already ran.

## Multi-Tenancy

### Tenant overrides not applying

**Issue**: You've set a tenant-specific value but the global default is always returned.

**Solution**:

- Verify `multi_tenancy.enabled` is `true`.
- Ensure your `tenant_resolver` returns the correct ID.
- Confirm the `tenant_id` matches what the resolver returns.

## Getting Help

If you're still stuck:

- Check GitHub issues for similar problems.
- Review the [Targeting Rules](../targeting-rules).
