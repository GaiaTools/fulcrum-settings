---
title: Laravel Modules
description: Manage module activation and contextual access with Fulcrum
---

# Laravel Modules

Fulcrum optionally integrates with `nwidart/laravel-modules` **13.x on Laravel 13**. Installing Fulcrum does not replace your activator. Core Fulcrum functionality continues to work without Laravel Modules installed.

There are two independent controls:

| Control | Setting behavior | Application behavior |
| --- | --- | --- |
| Activation | Global boolean without targeting rules | Determines whether Laravel registers a module's providers, routes, and listeners at startup |
| Access | Normal Fulcrum rules, tenant overrides, and rollouts | Determines whether the current context can use a loaded module |

A module must be loaded to serve test users. Keep activation enabled and make its access setting default to false with a rule returning true for testers. Rules stay in Fulcrum; they are never copied into activation storage.

## Configure module mappings

Add this section to `config/fulcrum.php`, or edit the section in a newly published configuration:

```php
'modules' => [
    'denied_status' => 404, // 403 is also supported; one setting for all modules

    'mappings' => [
        'Reports' => [
            'activation_setting' => 'module.reports.loaded',
            'access_setting' => 'module.reports.enabled',
        ],
        'ReportExports' => [
            'activation_setting' => 'module.report_exports.loaded',
            'access_setting' => 'module.report_exports.enabled',
            'requires' => ['Reports'],
        ],
    ],

    'aliases' => [
        'reports.enabled' => 'Reports',
        'report_exports.enabled' => 'ReportExports',
    ],
],
```

Names must match Laravel Modules' module names exactly. Setting keys are explicit and may follow your application's conventions. Activation keys must be unique and cannot also be access keys. `requires` checks activation dependencies between managed modules; it does not change provider boot order or replace your module architecture.

For access only, omit `activation_setting` or use the shorthand:

```php
'mappings' => [
    'Reports' => 'module.reports.enabled',
],
```

Activation-only mappings can omit `access_setting`. An access check with no mapping, no access key, a missing setting, or a result other than boolean `true` denies access. When Laravel Modules is registered, an absent or disabled module also denies access.

## Protect routes and application operations

Use the shared middleware on module routes, after authentication and tenant context have been established:

```php
Route::middleware(['auth', 'fulcrum.module:Reports'])->group(function () {
    // Reports routes...
});
```

With the alias above, `reports.enabled` expands to the same `fulcrum.module:Reports` middleware:

```php
Route::middleware(['auth', 'reports.enabled'])->group(function () {
    // Reports routes...
});
```

Choose alias names that do not collide with application middleware names or groups. Aliases reuse the same middleware class and evaluation logic.

Use the shared access service for navigation, jobs, listeners, and other operations:

```php
use GaiaTools\FulcrumSettings\Support\Modules\ModuleAccess;

if (app(ModuleAccess::class)->allows('Reports', $user)) {
    // Perform an operation available to this user.
}
```

The scope argument is passed directly to Fulcrum; it can also be a supported custom scope. Set tenant context using your existing Fulcrum integration. HTTP middleware passes the request's user automatically. Establish the intended context explicitly in queued work.

Access decisions follow normal Fulcrum caching and lifecycle behavior. HTTP protection does not automatically suppress registered listeners, scheduled tasks, or direct service calls; apply the access service at the appropriate operation boundary. Existing authentication and authorization checks still apply.

## Enable Fulcrum-managed activation

Install and configure Laravel Modules 13 following its installation instructions, then add the activator in `config/modules.php`:

```php
use GaiaTools\FulcrumSettings\Support\Modules\FulcrumModuleActivator;

// Keep the existing file activator configuration.
'activators' => [
    'file' => [
        'class' => \Nwidart\Modules\Activators\FileActivator::class,
        'statuses-file' => base_path('modules_statuses.json'),
    ],
    'fulcrum' => ['class' => FulcrumModuleActivator::class],
],

'activator' => 'fulcrum',
```

Only modules with an `activation_setting` are managed by Fulcrum. Others continue using the configured file activator and its `modules_statuses.json`. Access checks also work with the standard activator; selecting the Fulcrum activator is optional.

The activator initializes its integration before reading activation state, even when Laravel Modules registers before Fulcrum. It reads the published decisions once and freezes them for that application instance. Filesystem activation performs no database queries during startup.

Create activation settings as unmasked global booleans with boolean defaults. Activation settings cannot contain targeting rules, including scheduled or rollout rules. Use separate access settings for targeting. Missing activation settings resolve to disabled during sync.

For example, inside a Fulcrum `SettingMigration`:

```php
$this->createSetting('module.reports.loaded')->boolean()->default(true)->save();
$this->createSetting('module.reports.enabled')->boolean()->default(false)->save();
```

Add your tester or tenant rules to `module.reports.enabled` using normal Fulcrum APIs or administrative tooling.

## Publish activation changes

```bash
php artisan fulcrum:modules:status
php artisan fulcrum:modules:sync --dry-run
php artisan fulcrum:modules:sync
```

`fulcrum:modules:status` compares desired settings, published activation, and the current process's enabled statuses. Other running processes can retain older decisions.

Sync verifies that managed modules are installed, reads global defaults independently of the current tenant, validates dependencies, and publishes the entire activation document atomically. A dry run performs validation without writing. Invalid settings, lock failures, or storage failures return a nonzero exit code and do not intentionally replace the last good document.

With the Fulcrum activator selected, `module:enable` and `module:disable` update the managed module's **desired global activation setting**. They do not change the running process or publish automatically. For managed modules, disable means unavailable to everyone after application of the change, including testers. Unmanaged modules retain their normal file activator behavior. Managed module deletion and activation reset require explicit configuration/settings cleanup.

After publishing:

1. Ensure every application server reads the same published state, or distribute the filesystem document to each server.
2. Refresh your Laravel Modules provider manifest and route/event caches in **new application processes** using your deployment's normal cache-building workflow.
3. Restart long-running queue workers, Octane workers, and other application processes.

A setting change needs no code deployment. Runtime access-rule changes do not need activation sync or worker restarts solely for this integration; normal Fulcrum cache behavior applies.

## Choose activation storage

These options belong under `fulcrum.modules.storage`; omitted options use package defaults:

| Driver | Configuration | Startup dependency |
| --- | --- | --- |
| `filesystem` (default) | `path`: defaults to `storage/framework/fulcrum/module-activation.json` | Readable local file |
| `cache` | `cache_store`: Laravel cache store name or null for the default; `cache_key`: defaults to `fulcrum:module-activation` | Available configured cache |
| `database` | `connection`: database connection name or null for the default; `table`: defaults to `fulcrum_module_activation`; `key`: defaults to `default` | Available database and activation table |

For a read-only application filesystem, publish a file during deployment or use cache/database storage. Set `fulcrum.modules.lock.store` to a writable shared cache store such as Redis when using shared activation storage. It defaults to `file`. All publishers must use the same lock store/key for a shared destination. The lock lease defaults to 60 seconds and the wait to 10 seconds; size the lease to exceed the maximum sync duration.

Cache documents are stored without expiration, but eviction or cache clearing can still remove them. Use a dedicated persistent cache store/key and rerun sync after removal.

For database storage, publish and run the optional migration first:

```bash
php artisan vendor:publish --provider="GaiaTools\FulcrumSettings\Providers\FulcrumModulesServiceProvider" --tag=fulcrum-module-migrations
php artisan migrate
```

The migration uses the configured activation connection/table. It is not automatically installed for applications using other drivers. Provision this table and the existing Fulcrum settings tables before syncing.

A custom driver may be a class implementing `GaiaTools\FulcrumSettings\Contracts\Modules\ActivationStore`, or an application binding for that interface. Implement `read()` to return a document or null and `write()` to atomically replace it; throw on failure.

## Missing, stale, or unreadable activation

Missing activation data disables managed modules. Corrupt, outdated, or unreadable data is reported and disables managed modules without deliberately refusing application startup. The application must tolerate those modules being absent. An existing process retains the activation decisions it already loaded.

Published data contains a format version and a fingerprint of activation mappings/dependencies. Changing those definitions requires sync before starting new serving processes; mismatched data disables managed modules. Changing only access keys, aliases, or targeting rules does not invalidate published activation. Unspecified entries in an otherwise valid document default to disabled.

Storage is only the published list of loading decisions. Fulcrum's settings and rules remain the source of desired configuration and contextual access decisions.
