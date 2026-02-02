# Fulcrum Settings

A powerful feature flag and configuration management system for Laravel with rule-based targeting and conditional evaluation.

[![Laravel Horizon Compatible](https://img.shields.io/badge/Laravel%20Horizon-Compatible-brightgreen.svg)](docs/integrations/horizon)

## Documentation

- [Documentation Home](docs/index)
- [Overview](docs/overview)
- [Quick Start](docs/quick-start)
- [Installation](docs/installation)
- [Usage Guide](docs/usage)
- [Settings via Migrations](docs/migrations)
- [Comparison Table](docs/comparison)
- [Use Cases](docs/use-cases)
- [Class-Based Settings](docs/class-based-settings)
- [Targeting Rules](docs/targeting-rules)
- [Setting Types & Custom Handlers](docs/custom-types)
- [Multi-tenancy](docs/multi-tenancy)
- [Data Portability (Import/Export)](docs/data-portability)
- [Events and Observability](docs/integrations/events)
- [Extensibility (Drivers)](docs/integrations/extensibility)
- [Laravel Horizon Integration](docs/integrations/horizon)
- [Carbon/DateTime Integration](docs/integrations/carbon-integration)
- [Queues and Jobs](docs/integrations/queues-and-jobs)
- [Spatie Settings Migration](docs/migrate/spatie)
- [Laravel Pennant Migration](docs/migrate/pennant)
- [Troubleshooting](docs/troubleshooting)
- [API Reference](docs/api-reference)

## Examples

- [Basic Feature Flags](docs/examples/feature-flags)
- [Advanced Targeting Rules](docs/examples/advanced-targeting)
- [Multi-Tenancy Setup](docs/examples/multi-tenancy)
- [Custom Type (Money)](docs/examples/custom-types)
- [Data Portability (Import/Export)](docs/examples/data-portability)

## Features
- **Rule-based Evaluation**: Complex targeting based on user attributes, segments, geo-location, and more.
- **Carbon Integration**: First-class support for `Carbon` dates and time-based scheduling rules.
- **Horizon Integration**: Built-in support for Laravel Horizon with proper tagging and observability.
- **Data Portability**: Import and export settings in various formats (JSON, CSV, XML, YAML, SQL).
- **Asynchronous Operations**: Large imports and exports can be queued for better performance.

## Quick Start

```php
use GaiaTools\FulcrumSettings\Facades\Fulcrum;

// Check a feature flag
if (Fulcrum::isActive('new_dashboard')) {
    // ...
}

// Get a setting value
$value = Fulcrum::get('discount_percentage', default: 0);
```

For more details, see the [Full Documentation](docs/index).
