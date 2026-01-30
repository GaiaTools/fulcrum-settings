---
title: Laravel Telescope
description: Debug Fulcrum setting resolution with Telescope
---

# Laravel Telescope

Fulcrum can record detailed setting resolution logs in Telescope.

## Enable the Watcher

Add the watcher to `config/telescope.php`:

```php
'watchers' => [
    \GaiaTools\FulcrumSettings\Support\Telescope\SettingResolutionWatcher::class => [
        'enabled' => env('TELESCOPE_FULCRUM_WATCHER', true),
        'include_scope' => false,
    ],
],
```

Ensure Fulcrum's Telescope integration is enabled:

```php
// config/fulcrum.php
'telescope' => [
    'enabled' => env('FULCRUM_TELESCOPE_ENABLED', true),
],
```

## What You Get

- Resolved keys and values
- Matched rules and conditions
- Scope and context payloads
