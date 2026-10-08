---
title: Queues and Jobs
description: Run Fulcrum imports and exports asynchronously
---

# Queues and Jobs

Fulcrum leverages Laravel's queue system for long-running operations like importing and exporting large datasets.

## Asynchronous Operations

For large data sets, use queued jobs to avoid blocking requests.

### Queued Exports

Dispatch the job directly:

```php
use GaiaTools\FulcrumSettings\Jobs\ExportSettingsJob;

dispatch(new ExportSettingsJob('json', [
    'directory' => 'exports',
    'filename' => 'settings_v1.json',
]));
```

### Queued Imports

```php
use GaiaTools\FulcrumSettings\Jobs\ImportSettingsJob;

dispatch(new ImportSettingsJob('imports/new_config.json', 'json'));
```

## Artisan Integration

The `fulcrum:export` and `fulcrum:import` commands support a `--queue` flag:

```bash
php artisan fulcrum:export --filename=all_settings.json --queue
```

## Job Completion

Fulcrum fires standard events on completion. Listen for `SettingsLoaded` or `SettingsSaved` to trigger notifications.

## Queue Configuration

```php
// config/fulcrum.php
'queue' => [
    'connection' => 'redis',
    'queues' => [
        'imports' => 'fulcrum-imports',
        'exports' => 'fulcrum-exports',
    ],
],
```

## Worker context isolation

Asynchronous jobs start with clean Fulcrum context and services. Set the tenant and
user in each job; they are not inherited from the previous job or automatically
transported from the dispatching request. See [Worker Lifecycle](../advanced/worker-lifecycle.md)
for cleanup behavior, inline `sync` jobs, and Octane integration.
