# Example: Data Portability Workflows

Learn how to use Fulcrum's import and export features for common developer workflows.

## Workflow 1: Backup and Disaster Recovery

Regularly backing up your settings ensures you can recover quickly from accidental changes.

### Automated Daily Backups
You can create a scheduled command in `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('fulcrum:export --format=json --directory=storage/backups --filename=settings_' . now()->format('Y-m-d') . '.json')
    ->daily();
```

## Workflow 2: Environment Syncing

Syncing configuration from Production to Staging or Local environments.

### 1. Export from Production
```bash
php artisan fulcrum:export --format=json --filename=prod_settings.json
```

### 2. Import to Staging
```bash
php artisan fulcrum:import prod_settings.json --force
```

## Workflow 3: Large Scale Migrations

When you need to update thousands of settings or rules at once.

### 1. Export to CSV
CSV is often easier to edit in bulk using spreadsheet software.
```bash
php artisan fulcrum:export --format=csv --filename=settings_to_edit.csv
```

### 2. Edit in Excel/Google Sheets
Update the values, add new rules, etc.

### 3. Re-Import
```bash
php artisan fulcrum:import edited_settings.csv
```

## Workflow 4: Version Controlling Configuration

For teams that want to keep certain configuration in their Git repository.

### 1. Export to YAML
YAML is human-readable and works well with Git diffs.
```bash
php artisan fulcrum:export --format=yaml --directory=$(pwd)/config --filename=settings.yaml
```

### 2. Commit to Git
```bash
git add config/settings.yaml
git commit -m "chore: update application configuration"
```

### 3. Apply on Deployment
In your deployment script:
```bash
php artisan fulcrum:import config/settings.yaml --force
```

## Summary
Fulcrum's support for multiple formats and queued operations makes it easy to integrate configuration management into your existing CI/CD and DevOps workflows.

## Next Steps
- [Data Portability](../data-portability) - Deep dive into import/export features.
- [Integrations: Queues and Jobs](../integrations/queues-and-jobs) - Learn about asynchronous operations.
