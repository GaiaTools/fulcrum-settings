<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Contracts\CacheContextProvider;
use GaiaTools\FulcrumSettings\Contracts\GeoResolver;
use GaiaTools\FulcrumSettings\Contracts\SettingResolver;
use GaiaTools\FulcrumSettings\Database\Migrations\RuleModifier;
use GaiaTools\FulcrumSettings\Database\Migrations\SettingModifier;
use GaiaTools\FulcrumSettings\Enums\ComparisonOperator;
use GaiaTools\FulcrumSettings\Exceptions\ImmutableSettingException;
use GaiaTools\FulcrumSettings\Facades\Fulcrum;
use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Services\CachedSettingResolver;
use GaiaTools\FulcrumSettings\Support\Cache\CacheInvalidator;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\JsonFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\SqlFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\ImportManager;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['fulcrum.cache.enabled' => true, 'cache.default' => 'array']);
    app()->forgetInstance(SettingResolver::class);
    Fulcrum::clearResolvedInstance(SettingResolver::class);
    $this->setting = Setting::create(['key' => 'cached', 'type' => 'string']);
    $this->setting->defaultValue()->create(['value' => 'old']);
    $this->resolver = app(SettingResolver::class);
});

test('resolver writes invalidate every user and tenant scope without flushing unrelated cache', function () {
    Cache::put('unrelated', 'preserved', 3600);
    $a = $this->resolver->forUser((new User)->forceFill(['id' => 1]));
    $b = $this->resolver->forUser((new User)->forceFill(['id' => 2]));
    expect($a->resolve('cached'))->toBe('old')
        ->and($b->resolve('cached'))->toBe('old')
        ->and($this->resolver->forTenant('a')->resolve('cached'))->toBe('old')
        ->and($this->resolver->forTenant('b')->resolve('cached'))->toBe('old');
    $this->resolver->set('cached', 'new');
    expect($a->resolve('cached'))->toBe('new')
        ->and($b->resolve('cached'))->toBe('new')
        ->and($this->resolver->forTenant('a')->resolve('cached'))->toBe('new')
        ->and($this->resolver->forTenant('b')->resolve('cached'))->toBe('new')
        ->and(Cache::get('unrelated'))->toBe('preserved');
});

test('tenant override changes invalidate cached fallback and leave other tenant values correct', function () {
    $override = Setting::create(['key' => 'cached', 'type' => 'string', 'tenant_id' => 'a']);
    FulcrumContext::setTenantId('a');
    $override->defaultValue()->create(['value' => 'tenant-old']);
    expect($this->resolver->forTenant('a')->resolve('cached'))->toBe('tenant-old')
        ->and($this->resolver->forTenant('b')->resolve('cached'))->toBe('old');
    $this->resolver->forTenant('a')->set('cached', 'tenant-new');
    expect($this->resolver->forTenant('a')->resolve('cached'))->toBe('tenant-new')
        ->and($this->resolver->forTenant('b')->resolve('cached'))->toBe('old');
});

test('direct value updates and deletes invalidate cached results', function () {
    expect($this->resolver->resolve('cached'))->toBe('old');
    $this->setting->defaultValue->update(['value' => 'new']);
    expect($this->resolver->resolve('cached'))->toBe('new');
    $this->setting->defaultValue->delete();
    expect($this->resolver->resolve('cached'))->toBeNull();
});

test('setting rename and deletion invalidate old and new keys', function () {
    expect($this->resolver->resolve('cached'))->toBe('old');
    $this->setting->update(['key' => 'renamed']);
    expect($this->resolver->resolve('cached'))->toBeNull()
        ->and($this->resolver->resolve('renamed'))->toBe('old');
    $this->setting->delete();
    expect($this->resolver->resolve('renamed'))->toBeNull();
});

test('rule creation updates and deletion invalidate matching results', function () {
    expect($this->resolver->resolve('cached'))->toBe('old');
    $rule = $this->setting->rules()->create(['name' => 'target', 'priority' => 1]);
    $rule->value()->create(['value' => 'rule']);
    expect($this->resolver->resolve('cached'))->toBe('rule');
    $rule->update(['starts_at' => now()->addDay()]);
    expect($this->resolver->resolve('cached'))->toBe('old');
    $rule->update(['starts_at' => null]);
    expect($this->resolver->resolve('cached'))->toBe('rule');
    $rule->delete();
    expect($this->resolver->resolve('cached'))->toBe('old');
});

test('condition creation updates and deletion invalidate targeted results', function () {
    $rule = $this->setting->rules()->create(['name' => 'target', 'priority' => 1]);
    $rule->value()->create(['value' => 'rule']);
    expect($this->resolver->resolve('cached', ['plan' => 'basic']))->toBe('rule');
    $condition = $rule->conditions()->create(['attribute' => 'plan', 'operator' => ComparisonOperator::EQUALS, 'value' => 'premium']);
    expect($this->resolver->resolve('cached', ['plan' => 'basic']))->toBe('old');
    $condition->update(['value' => 'basic']);
    expect($this->resolver->resolve('cached', ['plan' => 'basic']))->toBe('rule');
    $condition->delete();
    expect($this->resolver->resolve('cached', ['plan' => 'premium']))->toBe('rule');
});

test('variant and variant value changes invalidate rollout results', function () {
    $rule = $this->setting->rules()->create(['name' => 'rollout', 'priority' => 1]);
    $variant = $rule->rolloutVariants()->create(['name' => 'a', 'weight' => 100000]);
    $variant->value()->create(['value' => 'variant-old']);
    expect($this->resolver->resolve('cached', 'user-1'))->toBe('variant-old');
    $variant->value->update(['value' => 'variant-new']);
    expect($this->resolver->resolve('cached', 'user-1'))->toBe('variant-new');
    $variant->update(['weight' => 0]);
    expect($this->resolver->resolve('cached', 'user-1'))->toBe('old');
    $variant->update(['weight' => 100000]);
    expect($this->resolver->resolve('cached', 'user-1'))->toBe('variant-new');
    $variant->delete();
    expect($this->resolver->resolve('cached', 'user-1'))->toBeNull();
});

test('transaction reads bypass cache and committed writes invalidate existing results', function () {
    expect($this->resolver->resolve('cached'))->toBe('old');
    $generation = CacheInvalidator::configured()->generation();
    DB::beginTransaction();
    try {
        $this->resolver->set('cached', 'new');
        expect(CacheInvalidator::configured()->generation())->toBe($generation)
            ->and($this->resolver->resolve('cached'))->toBe('new');
        DB::commit();
    } finally {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }
    expect(CacheInvalidator::configured()->generation())->not->toBe($generation)
        ->and($this->resolver->resolve('cached'))->toBe('new');
});

test('rolled back writes never publish values or invalidate committed cache entries', function () {
    expect($this->resolver->resolve('cached'))->toBe('old');
    $generation = CacheInvalidator::configured()->generation();
    DB::beginTransaction();
    $this->resolver->set('cached', 'rolled-back');
    expect($this->resolver->resolve('cached'))->toBe('rolled-back');
    DB::rollBack();
    expect(CacheInvalidator::configured()->generation())->toBe($generation)
        ->and($this->resolver->resolve('cached'))->toBe('old');
});

test('failed immutable writes leave existing cache entries valid', function () {
    FulcrumContext::force();
    $this->setting->update(['immutable' => true]);
    FulcrumContext::force(false);
    expect($this->resolver->resolve('cached'))->toBe('old');
    $generation = CacheInvalidator::configured()->generation();
    expect(fn () => $this->resolver->set('cached', 'new'))->toThrow(ImmutableSettingException::class);
    expect(CacheInvalidator::configured()->generation())->toBe($generation)
        ->and($this->resolver->resolve('cached'))->toBe('old');
});

test('migration bulk rule and condition removal invalidate cached results', function () {
    $rule = $this->setting->rules()->create(['name' => 'target', 'priority' => 1]);
    $rule->value()->create(['value' => 'rule']);
    $rule->conditions()->create(['attribute' => 'plan', 'operator' => ComparisonOperator::EQUALS, 'value' => 'premium']);
    expect($this->resolver->resolve('cached', ['plan' => 'basic']))->toBe('old');
    (new RuleModifier($rule))->clearConditions()->apply();
    expect($this->resolver->resolve('cached', ['plan' => 'basic']))->toBe('rule');
    (new SettingModifier($this->setting))->clearRules()->apply();
    expect($this->resolver->resolve('cached', ['plan' => 'basic']))->toBe('old');
});

test('structured imports invalidate cached results after success', function () {
    Storage::fake('local');
    Storage::disk('local')->put('settings.json', json_encode([['key' => 'cached', 'type' => 'string', 'default_value' => 'imported']]));
    expect($this->resolver->resolve('cached'))->toBe('old');
    (new ImportManager)->import(new JsonFormatter, 'settings.json');
    expect($this->resolver->resolve('cached'))->toBe('imported');
});

test('raw SQL imports invalidate cached results even without model events', function () {
    Storage::fake('local');
    Storage::disk('local')->put('settings.sql', "UPDATE setting_values SET value = 'imported' WHERE valuable_type = '".Setting::class."';");
    expect($this->resolver->resolve('cached'))->toBe('old');
    (new ImportManager)->import(new SqlFormatter, 'settings.sql');
    expect($this->resolver->resolve('cached'))->toBe('imported');
});

test('missing generation metadata creates a fresh namespace', function () {
    expect($this->resolver->resolve('cached'))->toBe('old');
    $this->resolver->set('cached', 'new');
    Cache::forget('fulcrum:generation');
    expect($this->resolver->resolve('cached'))->toBe('new');
});

test('an in flight stale resolution cannot repopulate the new generation', function () {
    $inner = Mockery::mock(SettingResolver::class, CacheContextProvider::class);
    $inner->shouldReceive('currentTenantId')->andReturn(null);
    $inner->shouldReceive('isMultiTenancyEnabled')->andReturn(true);
    $inner->shouldReceive('requestDependencies')->once()->andReturn([]);
    $inner->shouldReceive('resolve')->once()->andReturnUsing(function () {
        $this->setting->defaultValue->update(['value' => 'new']);

        return 'old';
    });
    $resolver = new CachedSettingResolver($inner);
    expect($resolver->resolve('cached'))->toBe('old')
        ->and($this->resolver->resolve('cached'))->toBe('new');
});

test('nested transaction invalidation waits for the outer commit', function () {
    expect($this->resolver->resolve('cached'))->toBe('old');
    $generation = CacheInvalidator::configured()->generation();
    DB::beginTransaction();
    DB::beginTransaction();
    $this->resolver->set('cached', 'new');
    DB::commit();
    expect(CacheInvalidator::configured()->generation())->toBe($generation);
    DB::commit();
    expect(CacheInvalidator::configured()->generation())->not->toBe($generation)
        ->and($this->resolver->resolve('cached'))->toBe('new');
});

test('dry run imports and failed imports leave the cached committed state intact', function () {
    Storage::fake('local');
    Storage::disk('local')->put('settings.json', json_encode([
        ['key' => 'cached', 'type' => 'string', 'default_value' => 'imported'],
        ['key' => 'broken', 'type' => 'invalid-type'],
    ]));
    expect($this->resolver->resolve('cached'))->toBe('old');
    $generation = CacheInvalidator::configured()->generation();
    (new ImportManager)->import(new JsonFormatter, 'settings.json', ['dry_run' => true]);
    expect(CacheInvalidator::configured()->generation())->toBe($generation);
    expect(fn () => (new ImportManager)->import(new JsonFormatter, 'settings.json'))->toThrow(ValueError::class);
    expect(CacheInvalidator::configured()->generation())->toBe($generation)
        ->and($this->resolver->resolve('cached'))->toBe('old');
});

test('empty truncate imports invalidate results despite having no model save events', function () {
    Storage::fake('local');
    Storage::disk('local')->put('settings.json', '[]');
    expect($this->resolver->resolve('cached'))->toBe('old');
    (new ImportManager)->import(new JsonFormatter, 'settings.json', ['truncate' => true]);
    expect($this->resolver->resolve('cached'))->toBeNull();
});

test('file store invalidation works without cache tags', function () {
    config(['fulcrum.cache.store' => 'file', 'fulcrum.cache.prefix' => 'invalidation-'.uniqid()]);
    app()->forgetInstance(SettingResolver::class);
    $resolver = app(SettingResolver::class);
    expect($resolver->resolve('cached'))->toBe('old');
    $this->setting->defaultValue->update(['value' => 'new']);
    expect($resolver->resolve('cached'))->toBe('new');
});

test('browser condition changes invalidate dependency metadata and restore shared hits after removal', function () {
    $rule = $this->setting->rules()->create(['name' => 'browser', 'priority' => 1]);
    $rule->value()->create(['value' => 'rule']);
    request()->headers->set('User-Agent', 'Chrome/130.0');
    expect($this->resolver->resolve('cached'))->toBe('rule');
    $condition = $rule->conditions()->create(['type' => 'user_agent', 'attribute' => 'browser', 'operator' => 'equals', 'value' => 'Chrome']);
    expect($this->resolver->resolve('cached'))->toBe('rule');
    request()->headers->set('User-Agent', 'Firefox/130.0');
    expect($this->resolver->resolve('cached'))->toBe('old');
    $condition->delete();
    expect($this->resolver->resolve('cached'))->toBe('rule');
    DB::connection()->enableQueryLog();
    DB::connection()->flushQueryLog();
    request()->headers->set('User-Agent', 'Chrome/130.0');
    expect($this->resolver->resolve('cached'))->toBe('rule')
        ->and(DB::connection()->getQueryLog())->toBe([]);
});

test('geo condition changes invalidate dependency metadata and restore shared hits after removal', function () {
    $geo = Mockery::mock(GeoResolver::class);
    $geo->shouldReceive('resolve')->andReturnUsing(fn () => ['country' => request()->ip() === '192.0.2.1' ? 'US' : 'GB']);
    app()->instance(GeoResolver::class, $geo);
    $rule = $this->setting->rules()->create(['name' => 'geo', 'priority' => 1]);
    $rule->value()->create(['value' => 'rule']);
    request()->server->set('REMOTE_ADDR', '192.0.2.1');
    expect($this->resolver->resolve('cached'))->toBe('rule');
    $condition = $rule->conditions()->create(['type' => 'geocoding', 'attribute' => 'country', 'operator' => 'equals', 'value' => 'US']);
    expect($this->resolver->resolve('cached'))->toBe('rule');
    request()->server->set('REMOTE_ADDR', '192.0.2.2');
    expect($this->resolver->resolve('cached'))->toBe('old');
    $condition->delete();
    expect($this->resolver->resolve('cached'))->toBe('rule');
    DB::connection()->enableQueryLog();
    DB::connection()->flushQueryLog();
    request()->server->set('REMOTE_ADDR', '192.0.2.1');
    expect($this->resolver->resolve('cached'))->toBe('rule')
        ->and(DB::connection()->getQueryLog())->toBe([]);
});

test('imports fall back to the default database for a non-string connection option', function () {
    Storage::fake('local');
    Storage::disk('local')->put('fallback.json', json_encode([['key' => 'cached', 'type' => 'string', 'default_value' => 'fallback-import']]));
    expect($this->resolver->resolve('cached'))->toBe('old');
    expect((new ImportManager)->import(new JsonFormatter, 'fallback.json', ['connection' => 123]))->toBeTrue();
    expect($this->resolver->resolve('cached'))->toBe('fallback-import');
});

test('read only transactions retain cache hits', function () {
    expect($this->resolver->resolve('cached'))->toBe('old');
    DB::beginTransaction();
    try {
        DB::connection()->enableQueryLog();
        DB::connection()->flushQueryLog();
        expect($this->resolver->resolve('cached'))->toBe('old')
            ->and(DB::connection()->getQueryLog())->toBe([]);
    } finally {
        DB::rollBack();
    }
});

test('many import rows rotate the cache once after commit', function () {
    Storage::fake('local');
    $rows = array_map(fn ($id) => ['key' => 'import-'.$id, 'type' => 'string', 'default_value' => 'value'], range(1, 50));
    Storage::disk('local')->put('many.json', json_encode($rows));
    $cache = Cache::store();
    $spy = Mockery::mock($cache)->makePartial();
    $spy->shouldReceive('forever')->with('fulcrum:generation', Mockery::type('string'))->once()->passthru();
    Cache::shouldReceive('store')->with(null)->andReturn($spy);
    expect((new ImportManager)->import(new JsonFormatter, 'many.json'))->toBeTrue();
});

test('cache rotation failure reports the error without failing successful writes or imports', function () {
    $cache = Cache::store();
    $spy = Mockery::mock($cache)->makePartial();
    $spy->shouldReceive('getStore')->andThrow(new RuntimeException('cache offline'));
    Cache::shouldReceive('store')->with(null)->andReturn($spy);
    $handler = Mockery::mock(ExceptionHandler::class);
    $handler->shouldReceive('report')->times(2)->with(Mockery::on(fn ($error) => $error->getMessage() === 'cache offline'));
    app()->instance(ExceptionHandler::class, $handler);
    $this->setting->defaultValue->update(['value' => 'saved']);
    expect($this->setting->defaultValue->fresh()->value)->toBe('saved');
    Storage::fake('local');
    Storage::disk('local')->put('offline.json', json_encode([['key' => 'cached', 'type' => 'string', 'default_value' => 'imported']]));
    expect((new ImportManager)->import(new JsonFormatter, 'offline.json'))->toBeTrue()
        ->and($this->setting->defaultValue->fresh()->value)->toBe('imported');
});

test('unchanged saves do not rotate generations even after previous changes', function () {
    $value = $this->setting->defaultValue;
    $value->update(['value' => 'changed']);
    $generation = CacheInvalidator::configured()->generation();
    $value->save();
    $this->setting->save();
    expect(CacheInvalidator::configured()->generation())->toBe($generation);
});

test('nested rollbacks clear only writes within the rolled back savepoint', function () {
    expect($this->resolver->resolve('cached'))->toBe('old');
    DB::beginTransaction();
    DB::beginTransaction();
    $this->resolver->set('cached', 'discarded');
    DB::rollBack();
    DB::connection()->enableQueryLog();
    DB::connection()->flushQueryLog();
    expect($this->resolver->resolve('cached'))->toBe('old')
        ->and(DB::connection()->getQueryLog())->toBe([]);
    $this->resolver->set('cached', 'committed');
    DB::beginTransaction();
    $this->resolver->set('cached', 'discarded-again');
    DB::rollBack();
    expect($this->resolver->resolve('cached'))->toBe('committed');
    DB::commit();
    expect($this->resolver->resolve('cached'))->toBe('committed');
});

test('condition deletion visits every row across pagination boundaries', function () {
    $rule = $this->setting->rules()->create(['name' => 'many', 'priority' => 1]);
    $rows = array_fill(0, 1001, ['setting_rule_id' => $rule->id, 'attribute' => 'plan', 'type' => 'user', 'operator' => 'equals', 'value' => 'premium']);
    DB::table('setting_rule_conditions')->insert($rows);
    DB::transaction(fn () => (new RuleModifier($rule))->removeCondition('plan')->apply());
    expect($rule->conditions()->count())->toBe(0);
});

test('writes first made in a committed savepoint remain pending until the outer transaction ends', function () {
    $generation = CacheInvalidator::configured()->generation();
    DB::beginTransaction();
    DB::beginTransaction();
    $this->resolver->set('cached', 'nested');
    DB::commit();
    expect($this->resolver->resolve('cached'))->toBe('nested')
        ->and(CacheInvalidator::configured()->generation())->toBe($generation);
    DB::rollBack();
    expect($this->resolver->resolve('cached'))->toBe('old')
        ->and(CacheInvalidator::configured()->generation())->toBe($generation);
});
