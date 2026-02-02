# Installation

Follow these steps to install and configure Laravel Fulcrum in your project.

## Requirements

- PHP 8.3 or higher
- Laravel 11.x or 12.x

## Step 1: Install the Package

Install the package using Composer:

```bash
composer require gaiatools/fulcrum-settings
```

## Step 2: Publish Configuration (Optional)

You can publish the configuration file to customize Fulcrum's behavior:

```bash
php artisan vendor:publish --provider="GaiaTools\FulcrumSettings\FulcrumSettingsServiceProvider" --tag="config"
```

This will create a `config/fulcrum.php` file in your project.

## Step 3: Run Migrations

Fulcrum uses several database tables to store settings, rules, and values. Run the migrations to set them up:

```bash
php artisan migrate
```

## Step 4: Configure Drivers (Optional)

Fulcrum supports various drivers for segments, geo-resolution, and more. You can configure these in your `config/fulcrum.php` or by binding implementations in your `AppServiceProvider`.

### Segment Driver

By default, Fulcrum leaves segment evaluation disabled. If you use `spatie/laravel-permission`, you can enable the included driver:

```php
// config/fulcrum.php
'segment_driver' => GaiaTools\FulcrumSettings\Drivers\SpatiePermissionSegmentDriver::class,
```

### Tenant Resolver

If your application is multi-tenant, you can define how Fulcrum resolves the current tenant:

```php
// config/fulcrum.php
'multi_tenancy' => [
    'enabled' => true,
    'tenant_resolver' => function() {
        return auth()->user()?->tenant_id;
    },
],
```

## Next Steps

Now that you have Fulcrum installed, you're ready to start using it!

- [Quick Start](quick-start) - Define your first feature flag.
- [Overview](overview) - Learn more about the core concepts.
- [Usage Guide](usage) - Explore the basic API.
