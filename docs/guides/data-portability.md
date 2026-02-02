---
title: Data Portability
description: Import and export settings across environments
---

# Data Portability

Fulcrum provides tools for importing and exporting settings in multiple formats.

## Supported Formats

- JSON
- YAML
- CSV
- XML
- SQL (insert statements)

## Artisan Commands

### Exporting Settings

```bash
# Export settings to a CSV file (default format)
php artisan fulcrum:export --filename=settings.csv

# Export in a specific format
php artisan fulcrum:export --format=json --filename=settings.json

# Export to a specific directory
php artisan fulcrum:export --directory=exports --filename=settings.json

# Decrypt masked values during export
php artisan fulcrum:export --decrypt --filename=secrets.json

# Compress the export file
php artisan fulcrum:export --gzip --filename=backup.csv.gz

# Queue the export as a background job
php artisan fulcrum:export --queue --filename=large_export.json
```

### Importing Settings

```bash
# Import settings from a file (auto-detects format)
php artisan fulcrum:import settings.json

# Import with a specific mode (insert, upsert)
php artisan fulcrum:import settings.json --mode=upsert

# Truncate tables before importing
php artisan fulcrum:import settings.json --truncate

# Perform a dry run without saving data
php artisan fulcrum:import settings.json --dry-run

# Queue the import as a background job
php artisan fulcrum:import settings.json --queue
```

## Using the Managers

```php
use GaiaTools\FulcrumSettings\Support\DataPortability\ExportManager;
use GaiaTools\FulcrumSettings\Support\DataPortability\ImportManager;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\JsonFormatter;

$exportPath = app(ExportManager::class)->export(new JsonFormatter(), [
    'filename' => 'settings.json',
]);

app(ImportManager::class)->import(new JsonFormatter(), storage_path('settings.json'));
```

## HTTP Endpoints

Fulcrum includes optional HTTP endpoints for data portability.

```php
// config/fulcrum.php
'portability' => [
    'routes' => [
        'enabled' => true,
        'middleware' => ['auth:sanctum', 'admin'],
    ],
],
```

When enabled:
- `GET /fulcrum/portability/export`
- `POST /fulcrum/portability/import`
