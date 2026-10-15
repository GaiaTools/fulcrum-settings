---
title: Artisan Commands
description: All Fulcrum CLI commands
---

# Artisan Commands

## fulcrum:list

List all registered settings.

```bash
php artisan fulcrum:list
```

**Options:**
| Option | Description |
|--------|-------------|
| `--tenant=` | Filter by tenant ID |
| `--no-tenants` | List only global (non-tenant) settings |

---

## fulcrum:get

Get a setting's resolved value.

```bash
php artisan fulcrum:get feature.new_dashboard
php artisan fulcrum:get feature.new_dashboard --scope=user_123
```

**Arguments:**
| Argument | Description |
|----------|-------------|
| `key` | The setting key |

**Options:**
| Option | Description |
|--------|-------------|
| `--tenant=` | Resolve for specific tenant ID |
| `--reveal` | Reveal masked values (requires authorization) |
| `--scope=` | Scope/identifier for rollout evaluation |

---

## fulcrum:set

Set a setting's default value or enter interactive mode.

```bash
# Set default value
php artisan fulcrum:set feature.new_dashboard true

# Interactive rule creation
php artisan fulcrum:set feature.new_dashboard
```

**Options:**
| Option | Description |
|--------|-------------|
| `--type=` | Setting type (string, integer, float, boolean, json, array, carbon) |
| `--description=` | Setting description |
| `--masked` | Mark as sensitive/encrypted |
| `--immutable` | Prevent modification (unless forced) |
| `--tenant=` | Set tenant-specific value |
| `--force` | Force update of immutable settings |

::: warning
Use migrations for production changes. This command is intended for development and debugging.
:::

---

## make:setting-migration

Generate a setting migration stub.

```bash
php artisan make:setting-migration create_api_rate_limit_setting
```

**Arguments:**
| Argument | Description |
|----------|-------------|
| `name` | The migration name |

**Options:**
| Option | Description |
|--------|-------------|
| `--path=` | Custom migration output path |

---

## fulcrum:export

Export settings to a file.

```bash
php artisan fulcrum:export --format=json --filename=settings.json
php artisan fulcrum:export --format=csv --filename=settings.csv
```

**Options:**
| Option | Description |
|--------|-------------|
| `--format=` | `csv`, `json`, `xml`, `yaml`, `sql` |
| `--directory=` | Output directory (default `.`) |
| `--filename=` | Output filename |
| `--decrypt` | Decrypt masked values |
| `--gzip` | Compress output with gzip |
| `--dry-run` | Run without writing a file |
| `--connection=` | Database connection |
| `--anonymize` | Anonymize sensitive data |
| `--queue` | Dispatch export as a job |
| `--queue-connection=` | Queue connection for the job |
| `--queue-name=` | Queue name for the job |

---

## fulcrum:import

Import settings from a file.

```bash
php artisan fulcrum:import settings.json
php artisan fulcrum:import settings.json --dry-run
```

**Arguments:**
| Argument | Description |
|----------|-------------|
| `path` | Path to the import file |

**Options:**
| Option | Description |
|--------|-------------|
| `--format=` | `csv`, `json`, `xml`, `yaml`, `sql` |
| `--mode=` | `insert` or `upsert` |
| `--truncate` | Truncate tables before importing |
| `--conflict-handling=` | `fail`, `skip`, or `log` |
| `--dry-run` | Run without writing data |
| `--connection=` | Database connection |
| `--chunk-size=` | Import chunk size (default `1000`) |
| `--queue` | Dispatch import as a job |
| `--queue-connection=` | Queue connection for the job |
| `--queue-name=` | Queue name for the job |

---

## fulcrum:migrate-spatie

Migrate from spatie/laravel-settings.

```bash
php artisan fulcrum:migrate-spatie
```

**Options:**
| Option | Description |
|--------|-------------|
| `--table=` | Source table name |
| `--connection=` | Source connection name |
| `--force` | Overwrite existing Fulcrum settings |

See [Migrating from Spatie](../migrate/spatie) for details.

---

## fulcrum:modules:sync

Validate and publish application-wide module activation from global Fulcrum settings. Available when Laravel Modules is installed. Select the Fulcrum activator in `config/modules.php` to use the published state for module loading.

```bash
php artisan fulcrum:modules:sync --dry-run
php artisan fulcrum:modules:sync
```

| Option | Description |
|--------|-------------|
| `--dry-run` | Validate installed modules, boolean defaults, and activation dependencies without publishing |

Missing settings resolve to disabled. Masked settings, non-boolean defaults, and settings with targeting rules are rejected. Sync reads global settings independently of the current tenant. Validation, locking, or publication failures return a nonzero exit code.

The command publishes a complete activation document to the configured filesystem, cache, or database store. It does not change the modules already loaded by the current process. After publication, refresh module/route/event caches in new application processes, distribute filesystem state when necessary, and restart long-running workers.

Runtime access rules remain in Fulcrum and do not require this command. See [Laravel Modules](../guides/laravel-modules).

## fulcrum:modules:status

Compare each managed module's desired global setting, published activation, and enabled status in the current application process.

```bash
php artisan fulcrum:modules:status
```

The table contains `Module`, `Desired`, `Published`, and `This process`. Uninstalled managed modules are shown as `not installed` in the last column. Other running processes can retain older activation until restarted. Missing or unreadable publication is displayed using the same disabled fallback as the activator.

## Laravel Modules activation commands

With the Fulcrum activator selected, `module:enable` and `module:disable` update managed modules' desired activation settings. Run `fulcrum:modules:sync` and apply the published state afterward. Disabling activation makes the module unavailable to everyone, including testers; use the access setting for contextual targeting.

Unmanaged modules retain file activator behavior. Deleting a managed module requires explicit removal of its mapping and settings first; activation reset is rejected. These commands are supplied by Laravel Modules, not Fulcrum.
