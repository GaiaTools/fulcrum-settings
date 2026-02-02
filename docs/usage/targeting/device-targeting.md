---
title: Device Targeting
description: Target settings by device, browser, or operating system
---

# Device Targeting

Fulcrum can evaluate rules against user agent data using the configured `UserAgentResolver`.

## Configure the Resolver

```php
// config/fulcrum.php
'user_agent_resolver' => GaiaTools\FulcrumSettings\Drivers\DefaultUserAgentResolver::class,
```

## Example Rules

```php
$this->createSetting('feature.chrome_experiment')
    ->boolean()
    ->default(false)
    ->rule(fn ($rule) => $rule
        ->name('Chrome users')
        ->whenType('user_agent', 'browser', 'equals', 'Chrome')
        ->then(true)
    )
    ->save();
```

```php
$this->createSetting('layout.density')
    ->string()
    ->default('comfortable')
    ->rule(fn ($rule) => $rule
        ->name('Mobile devices')
        ->whenType('user_agent', 'device', 'equals', 'Mobile')
        ->then('compact')
    )
    ->save();
```

## Available Keys

The default resolver exposes keys like `browser`, `os`, `device`, and `is_mobile` when using the `user_agent` type.
