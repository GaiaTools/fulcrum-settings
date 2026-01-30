# Comparison Table: Fulcrum vs. Others

Choosing the right configuration and feature flag tool is crucial. Here is how Laravel Fulcrum compares to other popular solutions in the Laravel ecosystem and beyond.

| Feature | Laravel Fulcrum | Spatie Laravel Settings | Laravel Pennant | LaunchDarkly / ConfigCat |
| :--- | :---: | :---: | :---: | :---: |
| **Type-Safe Classes** | ✅ Yes | ✅ Yes | ❌ No | ❌ No (via SDK only) |
| **Database Storage** | ✅ Yes | ✅ Yes | ✅ Yes | ☁️ Cloud Only |
| **Feature Flags** | ✅ Yes | ❌ No | ✅ Yes | ✅ Yes |
| **Complex Targeting Rules** | ✅ Yes | ❌ No | ⚠️ Basic | ✅ Yes |
| **Multi-Tenancy Scoping** | ✅ Yes | ❌ No | ❌ No | ⚠️ Tagging only |
| **Contextual Evaluation** | ✅ Yes | ❌ No | ✅ Yes | ✅ Yes |
| **Self-Hosted** | ✅ Yes | ✅ Yes | ✅ Yes | ❌ No |
| **Cost** | 🆓 Free/OSS | 🆓 Free/OSS | 🆓 Free/OSS | 💰 Paid/Subscription |
| **External Dependencies** | 🚫 None | 🚫 None | 🚫 None | ☁️ External SaaS |
| **Custom Type Handlers** | ✅ Yes | ✅ Yes | ❌ No | ❌ No |
| **Import/Export Tools** | ✅ Yes | ❌ No | ❌ No | ⚠️ Limited |

## Detailed Comparison

### vs. Spatie Laravel Settings
Spatie's package is excellent for simple, static application settings. Fulcrum takes this further by adding:
- **Dynamic Rules**: Change setting values based on the current user or context.
- **Feature Flags**: Native support for boolean flags with gradual rollouts.
- **Multi-Tenancy**: Built-in support for tenant-scoped overrides.

### vs. Laravel Pennant
Pennant is a lightweight feature flag package. Fulcrum expands on its capabilities:
- **Rich Targeting**: More complex rule evaluation beyond simple closures.
- **Settings + Flags**: A unified API for both configuration and flags.
- **Organization**: Settings classes provide better organization than defining flags in service providers.

### vs. SaaS (LaunchDarkly, ConfigCat)
SaaS tools are powerful but come with overhead and costs. Fulcrum offers a "best of both worlds":
- **Performance**: No external API calls to resolve flags.
- **Privacy**: Your configuration data stays in your database.
- **Cost**: No per-seat or per-request pricing.
- **Developer Experience**: Native Laravel integration with full IDE support.

## Which one should I choose?

- **Choose Spatie Laravel Settings** if you only need simple, static configuration that rarely changes and doesn't depend on the user.
- **Choose Laravel Pennant** if you only need very basic feature flags and don't want the extra features of a full settings system.
- **Choose SaaS Tools** if you need a non-technical UI for product managers to manage flags across multiple platforms (beyond just Laravel).
- **Choose Laravel Fulcrum** if you want a powerful, unified, type-safe system for both settings and feature flags, with advanced targeting and multi-tenancy support, all while keeping everything within your Laravel app.
