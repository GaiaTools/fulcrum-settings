# Laravel Modules integration

Use for Fulcrum integration with `nwidart/laravel-modules` **13.x on Laravel 13**. Core Fulcrum supports Laravel 11/12/13 without this optional dependency. Installing Fulcrum does not change the application's selected activator.

## Choose the controls needed

- **Contextual access only:** retain the existing Laravel Modules activator and map modules to access settings. Fulcrum evaluates normal rules, tenant overrides, and rollouts at runtime.
- **Managed loading as well:** explicitly select `GaiaTools\FulcrumSettings\Support\Modules\FulcrumModuleActivator` in `config/modules.php`. Provide separate global activation keys. The activator reads published decisions once per application instance.

For tester-only availability, the module must be loaded. Set the access default to false and add rules returning true for eligible users/tenants. Do not copy rules into published activation or try to register/unregister providers per user.

## Configuration and route protection

Under `modules` in `config/fulcrum.php`:

```php
'modules' => [
    'denied_status' => 404,
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
    'aliases' => ['reports.enabled' => 'Reports'],
],
```

Use exact installed module names. A string mapping (`'Reports' => 'module.reports.enabled'`) is access-only shorthand. Activation-only mappings may omit the access key. Activation keys must be unique and cannot also be access keys. `requires` validates dependencies between managed modules and needs an activation key; it does not order provider booting.

The HTTP denial status is one top-level option for all modules: 404 by default, optionally 403. Do not add per-module denial options.

```php
Route::middleware(['auth', 'fulcrum.module:Reports'])->group(function () {
    // Module routes.
});

// Equivalent with the configured alias:
Route::middleware(['auth', 'reports.enabled'])->group(function () {
    // Module routes.
});
```

Aliases are middleware groups expanding to `fulcrum.module:<ModuleName>`, not separate middleware classes. Avoid collisions with application aliases/groups. Establish authentication and tenant context before this middleware.

`GaiaTools\FulcrumSettings\Support\Modules\ModuleAccess::allows(string $module, mixed $scope = null): bool` provides the same contextual check outside HTTP:

```php
$allowed = app(\GaiaTools\FulcrumSettings\Support\Modules\ModuleAccess::class)
    ->allows('Reports', $user);
```

Access requires boolean true; unknown mappings, missing settings, and other values deny. With a registered Laravel Modules repository, absent or disabled modules also deny. Protect relevant jobs, listeners, scheduled operations, and service calls separately; route middleware does not suppress them. Establish the intended user/tenant context in queued work. Keep existing authentication and authorization checks.

## Custom activator

In `config/modules.php`, keep the existing `file` entry and add:

```php
'fulcrum' => [
    'class' => \GaiaTools\FulcrumSettings\Support\Modules\FulcrumModuleActivator::class,
],
```

Set Laravel Modules' top-level `'activator' => 'fulcrum'`. Only modules with `activation_setting` are managed. Others retain `FileActivator` behavior and its configured `statuses-file` (normally `modules_statuses.json`). Early initialization is handled by the custom activator; do not defer switching drivers until application boot.

Activation settings must be unmasked global booleans with boolean defaults and no targeting rules. Sync ignores tenant overrides. A missing setting becomes false. Create initial settings with the existing migration builder:

```php
$this->createSetting('module.reports.loaded')->boolean()->default(true)->save();
$this->createSetting('module.reports.enabled')->boolean()->default(false)->save();
```

Add normal targeting rules only to the access setting.

## Publication storage and locks

Under `fulcrum.modules.storage`:

| Key | Default | Meaning |
| --- | --- | --- |
| `driver` | `filesystem` | `filesystem`, `cache`, `database`, or a custom store class |
| `path` | `storage_path('framework/fulcrum/module-activation.json')` | Filesystem publication |
| `cache_store` | null | Laravel cache store, or default store |
| `cache_key` | `fulcrum:module-activation` | Cache publication key |
| `connection` | null | Database connection, or default connection |
| `table` | `fulcrum_module_activation` | Database publication table |
| `key` | `default` | Database publication row key |

Under `fulcrum.modules.lock`: `store` defaults to `file`, `key` to `fulcrum:module-activation:publish`, `seconds` to 60, and `wait` to 10. Use an atomic-lock-capable, writable shared store for shared publication. All publishers of one destination need the same lock. Set the lease above the maximum sync duration.

A read-only application can consume a deployment-provided file or use cache/database storage. Also select a writable lock store. Cache documents have no expiration but can still be evicted or cleared; use a persistent dedicated store and republish after loss.

Database storage needs the optional migration:

```bash
php artisan vendor:publish --provider="GaiaTools\FulcrumSettings\Providers\FulcrumModulesServiceProvider" --tag=fulcrum-module-migrations
php artisan migrate
```

The migration uses the configured activation connection/table and is not automatically loaded for other drivers.

Custom stores implement `GaiaTools\FulcrumSettings\Contracts\Modules\ActivationStore`. Return an opaque document or null from `read()` and atomically replace it with `write(array $state): void`; throw on failure and preserve the previous publication. Select the class through `storage.driver`, or bind the interface before resolution. The document contains a format version, activation-definition fingerprint, and module booleans; no rules or user context.

## Commands and applying changes

```bash
php artisan fulcrum:modules:status
php artisan fulcrum:modules:sync --dry-run
php artisan fulcrum:modules:sync
```

Status compares desired, published, and current-process activation. Sync validates installed modules, global boolean settings, and dependencies, then atomically publishes under the configured lock. A dry run validates without writing. Validation, lock, and publication failures return nonzero.

With the custom activator, standard `module:enable`/`module:disable` update desired global activation for managed modules; they do not publish or reload the process. Disable affects everyone after application, including testers. Unmanaged modules keep normal file behavior. Managed deletion/reset require explicit cleanup rather than silently deleting settings.

After publication, distribute filesystem state if applicable, refresh module/route/event caches in new processes, and restart long-running workers. Activation changes need no code deployment. Access-rule changes need no activation sync and follow normal Fulcrum caching/lifecycle behavior.

Missing publication disables managed modules. Invalid, unreadable, or outdated publication is reported and disables them without deliberately refusing startup. Applications must tolerate absent modules. Existing processes retain loaded decisions. Changing activation mappings/dependencies requires a fresh sync; access keys, aliases, and rules do not invalidate publication.
