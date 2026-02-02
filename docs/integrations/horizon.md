---
title: Laravel Horizon
description: Monitor Fulcrum's queued jobs in Horizon
---

# Laravel Horizon Integration

Fulcrum integrates with Laravel Horizon for better visibility into asynchronous operations.

## Feature Overview

When using Fulcrum with Horizon:

- **Automatic tagging** -- Jobs dispatched by Fulcrum are tagged with `fulcrum` and the operation type.
- **Monitoring** -- Track import/export progress in the Horizon dashboard.
- **Error tracking** -- Failures are linked to Fulcrum operations.

## Configuration

Horizon integration is enabled automatically if the Horizon package is installed. No additional configuration is required.

## Operation Tags

Fulcrum adds these tags:

- `fulcrum`
- `fulcrum-export`
- `fulcrum-import`
- `setting-key:{key}` (if restricted to specific keys)

## Example

```bash
php artisan fulcrum:export --filename=all_settings.json --queue
```

Filter by `fulcrum-export` in Horizon to see job progress.

## Related Reading

- [Data Portability](../guides/data-portability)
- [Queues and Jobs](queues-and-jobs)
