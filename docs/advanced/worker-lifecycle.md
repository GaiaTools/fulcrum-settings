---
title: Worker Lifecycle
---

# Worker Lifecycle

Fulcrum clears operation state on Laravel's queue `Looping` event, before job
reservation, and on `JobAttempted`, after successful or failed job processing.
It does not reset on `JobProcessing`: tenancy listeners can establish the current
job's context there without depending on package-provider registration order.
No job trait is required for standard daemon workers.

On older Laravel 11 releases without `JobAttempted`, Fulcrum falls back to
`JobProcessed`, `JobExceptionOccurred`, and `JobFailed`. This also cleans up
retryable exceptions and direct `Worker::process()` calls. Cleanup on those
fallback events depends on listener order: later completion/exception listeners
may see cleared context. Capture any required context earlier in job processing,
or upgrade to a release with `JobAttempted` for cleanup after those observers.
The job's `failed()` handler runs before `JobFailed` cleanup.

Cleanup clears ambient tenant, group, custom targeting attributes, reveal mode,
and force mode. It releases shared setting services, settings objects, and
condition handlers by their registered container binding keys, including
abstract-to-concrete mappings, and clears the Fulcrum facade reference.

On releases with `JobAttempted`, `JobProcessed` and `JobExceptionOccurred`
listeners can still read the job's context for logging and metrics. By
`JobAttempted`, cleanup may already have run; observers requiring context
should use the earlier completion/exception events. Direct `Worker::process()`
callers receive final cleanup but do not emit `Looping`. They must start their
first operation with a clean application scope, or call
`app(FulcrumLifecycle::class)->reset(app())` before processing begins.

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

The `sync` and `deferred` drivers execute in the caller's process. These jobs
retain the caller's context and services; their `SyncJob` events do not trigger
worker-boundary cleanup. Use explicit resolver clones for a different tenant or
user inside an inline job.

Laravel's `background` driver also uses sync job execution, but launches it in a
separate process. The parent application's in-memory context is not transported
to that process; pass tenant/user information explicitly. Its sync job events
likewise do not trigger worker-boundary cleanup.

For Octane, cleanup runs at request, task, and tick boundaries, and on worker
errors. Resolved instances are released from the operation's sandbox, as exposed
by [Octane's lifecycle events](https://github.com/laravel/octane/blob/2.x/src/Events/RequestReceived.php).
Octane is optional; installing Fulcrum does not require it.

Stateful Fulcrum services and configured/discovered settings classes use
Laravel scoped bindings. Cleanup also forgets application-bound singleton
instances of `FulcrumSettings` subclasses, including abstract-to-concrete
bindings. Resolving those bindings in the next operation creates fresh
instances. Configuration registries and shared cached results survive cleanup.
Lifecycle cleanup neither flushes the result cache nor rotates its generation.

Custom services should use scoped container factories. Do not retain a resolver,
settings object, user, or request in an application singleton across operations.
Already-held object references cannot be revoked by clearing container instances.
Application code remains responsible for its own state and database transactions.

## Authentication cleanup

`fulcrum.lifecycle.reset_authentication` defaults to `true`. Cleanup forgets
Laravel's resolved authentication guards, including their cached users. This
changes application-wide authentication state at operation boundaries, rather
than only Fulcrum's own context. On releases with `JobAttempted`, completion and exception observers run before
the final cleanup; older Laravel 11 fallback events have the listener-order
limitation described above.

Applications that manage authentication lifecycle themselves can opt out:

```php
'lifecycle' => [
    'reset_authentication' => false,
],
```

Disabling this flag preserves authentication state; Fulcrum context and service
cleanup remain enabled. The application then owns preventing users from leaking
between operations.

## Octane verification

The regression tests exercise sandbox event payloads, including worker errors,
against the documented Octane event structure. They do not instantiate the actual
optional Octane event classes or run a live Octane server. Full Octane integration
testing remains a follow-up.

The current CI dependency matrix uses Testbench 10/11 and therefore tests Laravel
12/13. Legacy completion events are exercised with an isolated dispatcher and a
provider that selects the fallback path; a complete Laravel 11 dependency matrix
is tracked in [issue #83](https://github.com/GaiaTools/fulcrum-settings/issues/83).
