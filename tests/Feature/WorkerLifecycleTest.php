<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Attributes\SettingProperty;
use GaiaTools\FulcrumSettings\Contracts\SettingResolver;
use GaiaTools\FulcrumSettings\Contracts\UserAgentResolver;
use GaiaTools\FulcrumSettings\Facades\Fulcrum;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use GaiaTools\FulcrumSettings\Support\Settings\FulcrumSettings;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobProcessing;
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
    Event::dispatch(new JobProcessing('testing', Mockery::mock(Job::class)->shouldIgnoreMissing()));
    expect(Fulcrum::getFacadeRoot())->not->toBe($resolver)
        ->and(app(SettingResolver::class))->toBe(Fulcrum::getFacadeRoot())
        ->and(app(LifecycleSettings::class))->not->toBe($before)
        ->and(app(UserAgentResolver::class)->resolve()['browser'])->toBe('Firefox');
});

test('inline sync jobs preserve the caller context and instances', function () {
    FulcrumContext::setTenantId('caller');
    $resolver = app(SettingResolver::class);
    $job = new SyncJob(app(), '{}', 'sync', 'default');
    Event::dispatch(new JobProcessing('sync', $job));
    expect(FulcrumContext::getTenantId())->toBe('caller')
        ->and(app(SettingResolver::class))->toBe($resolver);
});

test('Octane hooks reset the sandbox without clearing base application instances', function (string $event) {
    $baseResolver = app(SettingResolver::class);
    $sandbox = clone app();
    FulcrumContext::setTenantId('old');
    Event::dispatch($event, [(object) ['sandbox' => $sandbox]]);
    expect(FulcrumContext::getTenantId())->toBeNull()
        ->and($sandbox->make(SettingResolver::class))->not->toBe($baseResolver)
        ->and(app(SettingResolver::class))->toBe($baseResolver);
})->with(array_map(fn ($name) => 'Laravel\\Octane\\Events\\'.$name, ['RequestReceived', 'RequestTerminated', 'TaskReceived', 'TaskTerminated', 'TickReceived', 'TickTerminated', 'WorkerErrorOccurred']));

class LifecycleSettings extends FulcrumSettings {}

class LoadedLifecycleSettings extends FulcrumSettings
{
    #[SettingProperty(key: 'worker-value')]
    public string $value;
}

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
