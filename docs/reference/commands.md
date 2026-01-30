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
| `--type=` | Setting type (string, integer, float, boolean, json, carbon) |
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
