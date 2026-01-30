---
title: User Targeting
description: Target settings by user ID, attributes, and properties
---

# User Targeting

User targeting evaluates conditions against the current authenticated user or an explicit scope.

## Common Fields

- `id` -- the authenticated user's primary key
- `email`
- `profile.tier` -- nested properties via dot notation

## Example: Target by User ID

```php
$this->createSetting('feature.new_dashboard')
    ->boolean()
    ->default(false)
    ->rule(fn ($rule) => $rule
        ->name('User 123')
        ->whereNumberEquals('id', 123)
        ->then(true)
    )
    ->save();
```

## Example: Target by Attribute

```php
$this->createSetting('api.rate_limit')
    ->integer()
    ->default(100)
    ->rule(fn ($rule) => $rule
        ->name('Pro plan')
        ->whereEquals('subscription.plan', 'pro')
        ->then(500)
    )
    ->save();
```

## Explicit Scopes

You can pass a scope to resolve for a specific user:

```php
$value = Fulcrum::get('api.rate_limit', scope: $user);
```

## Notes

- Missing attributes cause conditions to fail.
- Explicit `null` values can still match `is_null` operators.
