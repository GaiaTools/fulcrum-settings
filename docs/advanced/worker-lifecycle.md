---
title: Worker Lifecycle
---

# Worker Lifecycle

Fulcrum automatically starts asynchronous queue jobs and Octane operations with a
clean resolution context. No application middleware or job trait is required.

Before a queue job starts, and after it completes or throws, Fulcrum clears its
ambient tenant, group, custom targeting attributes, reveal mode, and force mode.
It releases resolved setting services, settings objects, and condition handlers,
clears its facade reference, and forgets Laravel's resolved authentication guards.
Each job must establish its own tenant and user. Context is not automatically
serialized into a queued job.

```php
public function handle(): void
{
    FulcrumContext::setTenantId($this->tenantId);
    // Resolve the job's user explicitly if its rules depend on a user.
    $value = Fulcrum::forUser($this->user)->resolve('feature');
}
```

The `sync` queue runs inline inside the caller's lifecycle. Its jobs retain the
caller's context and services; they do not trigger worker-boundary cleanup. Use
explicit resolver clones for a different tenant or user inside an inline job.

For Octane, cleanup runs at request, task, and tick boundaries, and on worker
errors. Resolved instances are released from the operation's sandbox, as exposed
by [Octane's lifecycle events](https://github.com/laravel/octane/blob/2.x/src/Events/RequestReceived.php).
Octane is optional; installing Fulcrum does not require it.

Stateful Fulcrum services and configured/discovered settings classes use Laravel
scoped bindings. Configuration registries and shared cached results survive
cleanup. Lifecycle cleanup neither flushes the result cache nor rotates its
generation.

Custom services should use scoped container factories. Do not retain a resolver,
settings object, user, or request in an application singleton across operations.
Already-held object references cannot be revoked by clearing container instances.
Application code remains responsible for its own state and database transactions.
