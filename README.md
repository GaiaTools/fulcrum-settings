# Fulcrum Settings

A powerful feature flag and configuration management system for Laravel with rule-based targeting and conditional evaluation.

[![Release][release-shield]][release-url]
[![Quality Gate][quality-gate-shield]][sonar-url]
[![License][license-shield]][license-url]
[![Downloads][downloads-shield]][packagist-url]
![Coverage][coverage-shield]

<!-- Badge URLs -->
[release-shield]: https://img.shields.io/packagist/v/GaiaTools/fulcrum-settings?sort=semver&color=blue
[quality-gate-shield]: https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fsonar.r2websolutions.com%2Fapi%2Fmeasures%2Fcomponent%3Fcomponent%3DGaiaTools_fulcrum-settings_62fe7b2a-26b4-4595-a6eb-d3ae8932b6d1%26metricKeys%3Dalert_status&query=$.component.measures[0].value&label=Quality%20Gate&labelColor=black&color=%23009900
[license-shield]: https://img.shields.io/packagist/l/GaiaTools/fulcrum-settings?label=License&labelColor=black&color=%23009900
[downloads-shield]: https://img.shields.io/packagist/dt/GaiaTools/fulcrum-settings.svg?label=Downloads&labelColor=black&color=%23009900
[coverage-shield]: https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fsonar.r2websolutions.com%2Fapi%2Fmeasures%2Fcomponent%3Fcomponent%3DGaiaTools_fulcrum-settings_62fe7b2a-26b4-4595-a6eb-d3ae8932b6d1%26metricKeys%3Dcoverage&query=$.component.measures[0].value&suffix=%25&label=Coverage&labelColor=black

<!-- Link URLs -->
[release-url]: https://github.com/GaiaTools/fulcrum-settings/releases
[sonar-url]: https://sonar.r2websolutions.com/dashboard?id=GaiaTools_fulcrum-settings_62fe7b2a-26b4-4595-a6eb-d3ae8932b6d1
[license-url]: https://github.com/GaiaTools/fulcrum-settings/blob/main/LICENSE
[packagist-url]: https://packagist.org/packages/GaiaTools/fulcrum-settings

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
