<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Attributes\SettingProperty;
use GaiaTools\FulcrumSettings\Contracts\SettingResolver;
use GaiaTools\FulcrumSettings\Contracts\UserAgentResolver;
use GaiaTools\FulcrumSettings\Facades\Fulcrum;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Providers\FulcrumLifecycleServiceProvider;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use GaiaTools\FulcrumSettings\Support\Lifecycle\FulcrumLifecycle;
use GaiaTools\FulcrumSettings\Support\Settings\FulcrumSettings;
use GaiaTools\FulcrumSettings\Support\TypeRegistry;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Event;

function lifecycleJob(Closure $handle): Job
{
    $job = Mockery::mock(Job::class)->shouldIgnoreMissing();
    $job->shouldReceive('payload')->andReturn([]);
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('fire')->once()->andReturnUsing($handle);

    return $job;
}

test('successive worker jobs cannot inherit tenant user reveal force or targeting context', function (bool $fail) {
    $global = Setting::create(['key' => 'worker-value', 'type' => 'string']);
    $global->defaultValue()->create(['value' => 'public']);
    FulcrumContext::setTenantId('a');
    $tenant = Setting::create(['key' => 'worker-value', 'type' => 'string', 'tenant_id' => 'a']);
    $tenant->defaultValue()->create(['value' => 'tenant-a']);
    FulcrumContext::clear();
    app()->scoped(LoadedLifecycleSettings::class);
    $worker = app('queue.worker');
    $first = lifecycleJob(function () use ($fail) {
        FulcrumContext::setTenantId('a');
        auth()->setUser((new User)->forceFill(['id' => 7]));
        expect(Fulcrum::resolve('worker-value'))->toBe('tenant-a')
            ->and(app(LoadedLifecycleSettings::class)->value)->toBe('tenant-a');
        FulcrumContext::setGroup('private');
        FulcrumContext::set('plan', 'premium');
        FulcrumContext::reveal();
        FulcrumContext::set('reveal', true);
        expect(FulcrumContext::shouldReveal())->toBeTrue()
            ->and(FulcrumContext::get('reveal'))->toBeTrue();
        FulcrumContext::force();
        if ($fail) {
            throw new RuntimeException('job failed');
        }
    });
    if ($fail) {
        expect(fn () => $worker->process('testing', $first, new WorkerOptions(maxTries: 0)))->toThrow(RuntimeException::class, 'job failed');
    } else {
        $worker->process('testing', $first, new WorkerOptions(maxTries: 0));
    }
    expect(FulcrumContext::getTenantId())->toBeNull()
        ->and(FulcrumContext::getGroup())->toBeNull()
        ->and(FulcrumContext::all())->toBe([])
        ->and(FulcrumContext::shouldReveal())->toBeFalse()
        ->and(FulcrumContext::shouldForce())->toBeFalse()
        ->and(auth()->user())->toBeNull();
    // Also clear contamination introduced while the worker is between jobs.
    FulcrumContext::setTenantId('a');
    $second = lifecycleJob(function () {
        expect(Fulcrum::resolve('worker-value'))->toBe('public')
            ->and(app(LoadedLifecycleSettings::class)->value)->toBe('public')
            ->and(auth()->user())->toBeNull();
    });
    Event::dispatch(new Looping('testing', 'default'));
    $worker->process('testing', $second, new WorkerOptions(maxTries: 0));
})->with([false, true]);

test('worker boundaries release facade resolvers settings objects and retained requests', function () {
    app()->scoped(LifecycleSettings::class);
    $before = app(LifecycleSettings::class);
    $resolver = Fulcrum::getFacadeRoot();
    $oldRequest = Request::create('/');
    $oldRequest->headers->set('User-Agent', 'Chrome/130.0');
    app()->instance('request', $oldRequest);
    $oldAgent = app(UserAgentResolver::class);
    expect($oldAgent->resolve()['browser'])->toBe('Chrome');
    $newRequest = Request::create('/');
    $newRequest->headers->set('User-Agent', 'Firefox/130.0');
    app()->instance('request', $newRequest);
    Event::dispatch(new Looping('testing', 'default'));
    expect(Fulcrum::getFacadeRoot())->not->toBe($resolver)
        ->and(app(SettingResolver::class))->toBe(Fulcrum::getFacadeRoot())
        ->and(app(LifecycleSettings::class))->not->toBe($before)
        ->and(app(UserAgentResolver::class)->resolve()['browser'])->toBe('Firefox');
});

test('sync backed job events preserve the current context and instances', function (string $driver) {
    FulcrumContext::setTenantId('caller');
    $resolver = app(SettingResolver::class);
    $job = new SyncJob(app(), '{}', $driver, 'default');
    Event::dispatch(new JobAttempted($driver, $job));
    expect(FulcrumContext::getTenantId())->toBe('caller')
        ->and(app(SettingResolver::class))->toBe($resolver);
})->with(['sync', 'deferred', 'background']);

test('Octane hooks reset the sandbox without clearing base application instances', function (string $event) {
    $baseResolver = app(SettingResolver::class);
    $sandbox = clone app();
    FulcrumContext::setTenantId('old');
    Event::dispatch($event, [(object) ['sandbox' => $sandbox]]);
    expect(FulcrumContext::getTenantId())->toBeNull()
        ->and($sandbox->make(SettingResolver::class))->not->toBe($baseResolver)
        ->and(app(SettingResolver::class))->toBe($baseResolver);
})->with(array_map(fn ($name) => 'Laravel\\Octane\\Events\\'.$name, ['RequestReceived', 'RequestTerminated', 'TaskReceived', 'TaskTerminated', 'TickReceived', 'TickTerminated', 'WorkerErrorOccurred']));

test('user targeted flags do not inherit the previous jobs authenticated user', function () {
    $setting = Setting::create(['key' => 'worker-user', 'type' => 'boolean']);
    $setting->defaultValue()->create(['value' => false]);
    $rule = $setting->rules()->create(['name' => 'user', 'priority' => 1]);
    $rule->value()->create(['value' => true]);
    $rule->conditions()->create(['type' => 'user', 'attribute' => 'id', 'operator' => 'equals', 'value' => 7]);
    $worker = app('queue.worker');
    $worker->process('testing', lifecycleJob(function () {
        auth()->setUser((new User)->forceFill(['id' => 7]));
        expect(Fulcrum::isActive('worker-user'))->toBeTrue();
    }), new WorkerOptions(maxTries: 0));
    $worker->process('testing', lifecycleJob(function () {
        expect(Fulcrum::isActive('worker-user'))->toBeFalse();
    }), new WorkerOptions(maxTries: 0));
});

test('a cold sandbox cleans its own resolved settings instances', function () {
    app()->forgetInstance(FulcrumLifecycle::class);
    app()->singleton(LifecycleSettings::class);
    $sandbox = clone app();
    $before = $sandbox->make(LifecycleSettings::class);
    Event::dispatch('Laravel\\Octane\\Events\\RequestTerminated', [(object) ['sandbox' => $sandbox]]);
    expect($sandbox->make(LifecycleSettings::class))->not->toBe($before);
});

test('tenancy listeners and completion observers retain their job context', function (bool $fail) {
    Event::listen(JobProcessing::class, fn () => FulcrumContext::setTenantId('assigned'));
    // Intentionally register duplicate package listeners to simulate its provider
    // booting after the application's tenancy listener.
    (new FulcrumLifecycleServiceProvider(app()))->boot();
    $observed = [];
    Event::listen([JobProcessed::class, JobExceptionOccurred::class], function () use (&$observed) {
        $observed[] = FulcrumContext::getTenantId();
    });
    $job = lifecycleJob(function () use ($fail) {
        expect(FulcrumContext::getTenantId())->toBe('assigned');
        if ($fail) {
            throw new RuntimeException('expected failure');
        }
    });
    $worker = app('queue.worker');
    if ($fail) {
        expect(fn () => $worker->process('testing', $job, new WorkerOptions(maxTries: 0)))->toThrow(RuntimeException::class);
    } else {
        $worker->process('testing', $job, new WorkerOptions(maxTries: 0));
    }
    expect($observed)->toBe(['assigned'])
        ->and(FulcrumContext::getTenantId())->toBeNull();
})->with([false, true]);

test('authentication cleanup can be disabled without disabling Fulcrum context cleanup', function () {
    expect(config('fulcrum.lifecycle.reset_authentication'))->toBeTrue();
    config(['fulcrum.lifecycle.reset_authentication' => false]);
    $user = (new User)->forceFill(['id' => 7]);
    auth()->setUser($user);
    FulcrumContext::setTenantId('old');
    Event::dispatch(new Looping('testing', 'default'));
    expect(auth()->user())->toBe($user)
        ->and(FulcrumContext::getTenantId())->toBeNull();
});

test('cleanup forgets abstract binding keys and their separately shared concrete implementations', function () {
    app()->singleton(AbstractLifecycleSettings::class, ConcreteLifecycleSettings::class);
    app()->singleton(ConcreteLifecycleSettings::class);
    app()->bind(LifecycleSettings::class);
    $before = app(AbstractLifecycleSettings::class);
    expect(app(ConcreteLifecycleSettings::class))->toBe($before);
    $registry = app(TypeRegistry::class);
    Event::dispatch(new Looping('testing', 'default'));
    expect(app(AbstractLifecycleSettings::class))->not->toBe($before)
        ->and(app(ConcreteLifecycleSettings::class))->not->toBe($before)
        ->and(app(TypeRegistry::class))->toBe($registry);
});

test('Octane authentication cleanup uses the sandbox configuration', function () {
    $user = (new User)->forceFill(['id' => 7]);
    auth()->setUser($user);
    $sandbox = clone app();
    $configuration = clone app('config');
    $configuration->set('fulcrum.lifecycle.reset_authentication', false);
    $sandbox->instance('config', $configuration);
    FulcrumContext::setTenantId('old');
    Event::dispatch('Laravel\\Octane\\Events\\TaskTerminated', [(object) ['sandbox' => $sandbox]]);
    expect(auth()->user())->toBe($user)
        ->and(FulcrumContext::getTenantId())->toBeNull()
        ->and(config('fulcrum.lifecycle.reset_authentication'))->toBeTrue();
});

test('legacy queue completion events clean direct worker operations', function (bool $fail) {
    $dispatcher = new Dispatcher(app());
    app()->instance('events', $dispatcher);
    app()->forgetInstance('queue.worker');
    (new LegacyLifecycleServiceProvider(app()))->boot();
    expect($dispatcher->hasListeners(JobAttempted::class))->toBeFalse();
    $worker = app('queue.worker');
    $job = lifecycleJob(function () use ($fail) {
        FulcrumContext::setTenantId('legacy');
        FulcrumContext::reveal();
        FulcrumContext::set('reveal', true);
        expect(FulcrumContext::shouldReveal())->toBeTrue()
            ->and(FulcrumContext::get('reveal'))->toBeTrue();
        auth()->setUser((new User)->forceFill(['id' => 7]));
        if ($fail) {
            throw new RuntimeException('retryable failure');
        }
    });
    if ($fail) {
        expect(fn () => $worker->process('testing', $job, new WorkerOptions(maxTries: 0)))->toThrow(RuntimeException::class);
    } else {
        $worker->process('testing', $job, new WorkerOptions(maxTries: 0));
    }
    expect(FulcrumContext::getTenantId())->toBeNull()
        ->and(FulcrumContext::shouldReveal())->toBeFalse()
        ->and(FulcrumContext::get('reveal'))->toBeNull()
        ->and(auth()->user())->toBeNull();
})->with([false, true]);

test('legacy terminal failure cleanup responds to JobFailed dispatched by the mock', function () {
    $dispatcher = new Dispatcher(app());
    app()->instance('events', $dispatcher);
    app()->forgetInstance('queue.worker');
    (new LegacyLifecycleServiceProvider(app()))->boot();
    $job = lifecycleJob(function () {
        FulcrumContext::setTenantId('failed');
        throw new RuntimeException('terminal failure');
    });
    $job->shouldReceive('fail')->once()->andReturnUsing(function ($exception) use ($dispatcher, $job) {
        expect(FulcrumContext::getTenantId())->toBe('failed');
        $dispatcher->dispatch(new JobFailed('testing', $job, $exception));
        expect(FulcrumContext::getTenantId())->toBeNull();
    });
    expect(fn () => app('queue.worker')->process('testing', $job, new WorkerOptions(maxTries: 1)))->toThrow(RuntimeException::class);
});

test('unresolved shared bindings do not trigger autoloading during cleanup', function () {
    $lookups = [];
    $autoload = function ($class) use (&$lookups) {
        $lookups[] = $class;
    };
    app()->singleton('UnusedLifecycleBinding', fn () => new stdClass);
    spl_autoload_register($autoload);
    try {
        app(FulcrumLifecycle::class)->reset(app());
        expect($lookups)->not->toContain('UnusedLifecycleBinding');
    } finally {
        spl_autoload_unregister($autoload);
    }
});

class LegacyLifecycleServiceProvider extends FulcrumLifecycleServiceProvider
{
    protected function hasJobAttemptedEvent(): bool
    {
        return false;
    }
}

class LifecycleSettings extends FulcrumSettings {}

class LoadedLifecycleSettings extends FulcrumSettings
{
    #[SettingProperty(key: 'worker-value')]
    public string $value;
}

abstract class AbstractLifecycleSettings extends FulcrumSettings {}

class ConcreteLifecycleSettings extends AbstractLifecycleSettings {}
