# Data Portability

Laravel Fulcrum provides robust tools for importing and exporting your settings and rules in various formats.

## Supported Formats

Fulcrum supports the following formats for both import and export:

- **JSON**
- **YAML**
- **CSV**
- **XML**
- **SQL** (Insert statements)

## Artisan Commands

The easiest way to manage data is through the provided Artisan commands.

### Exporting Settings

```bash
# Export settings to a CSV file (default format)
php artisan fulcrum:export --filename=settings.csv

# Export in a specific format (json, xml, yaml, sql, csv)
php artisan fulcrum:export --format=json --filename=settings.json

# Export to a specific directory
php artisan fulcrum:export --directory=exports --filename=settings.json

# Decrypt sensitive (masked) values during export
php artisan fulcrum:export --decrypt --filename=secrets.json

# Compress the export file
php artisan fulcrum:export --gzip --filename=backup.csv.gz

# Queue the export as a background job
php artisan fulcrum:export --queue --filename=large_export.json
```

### Importing Settings

```bash
# Import settings from a file (auto-detects format from extension)
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

## Using the Facade

You can also trigger imports and exports from your code.

```php
use GaiaTools\FulcrumSettings\Facades\Fulcrum;

// Export
$data = Fulcrum::export('json');

// Import
Fulcrum::import($jsonData, 'json');
```

## Queued Operations

For large datasets, you can run imports and exports as background jobs.

```php
// Dispatch a job to export settings
Fulcrum::queueExport('exports/all_settings.json');

// Dispatch a job to import settings
Fulcrum::queueImport('imports/new_config.json');
```

## HTTP Endpoints

Fulcrum includes optional HTTP endpoints for data portability, which can be protected by middleware.

```php
// config/fulcrum.php
'portability' => [
    'endpoints' => [
        'enabled' => true,
        'middleware' => ['auth:sanctum', 'admin'],
    ],
],
```

Once enabled, you can use:
- `GET /fulcrum/export`
- `POST /fulcrum/import`

## Next Steps

- [Example: Data Portability](examples/data-portability) - See examples of migration and backup workflows.
- [Integrations: Queues and Jobs](integrations/queues-and-jobs) - Learn more about asynchronous operations.
