<?php

declare(strict_types=1);

use App\Cache\ScopeAliasCache;
use App\Scopes\EntriesBySchemaType;
use Illuminate\Support\Facades\Cache;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * @covers \App\Scopes\EntriesBySchemaType
 *
 * Clearing and warming the scope's persisted alias list. Both stores are mocks: the test
 * environment's cache is file-backed and shared between test processes, and the alias list
 * under `CACHE_KEY_ALIASES` is what every process reads when it registers the scope, so a
 * real write here could hand another process a two-entry alias list.
 *
 * `aliases()` itself is not exercised past its first branch. It memoizes in a function-level
 * static that nothing can reset, so once a process has called it, its fallbacks (the Laravel
 * cache without the dedicated store, a throwing cache, and no cache at all) cannot run again
 * in that process; every test that names the class reached only the first branch.
 *
 * @command  php artisan test --compact tests/Feature/Scopes/EntriesBySchemaTypeAliasCacheTest.php
 */
it('clears the alias list from the dedicated store and the Laravel cache', function (): void {
    $store = Mockery::mock(ScopeAliasCache::class);
    $store->shouldReceive('forget')->once()->with(EntriesBySchemaType::CACHE_KEY_ALIASES);
    app()->instance(ScopeAliasCache::class, $store);

    Cache::shouldReceive('forget')->once()->with(EntriesBySchemaType::CACHE_KEY_ALIASES);

    EntriesBySchemaType::clearAliasesCache();
});

it('warms both stores with one snake-cased alias per type name, without repeats', function (): void {
    $expected = [
        'entries_by_schema_type_thing',
        'entries_by_schema_type_3_d_model',
        'entries_by_schema_type_medical_condition',
    ];

    $store = Mockery::mock(ScopeAliasCache::class);
    $store->shouldReceive('put')->once()->with(EntriesBySchemaType::CACHE_KEY_ALIASES, $expected);
    app()->instance(ScopeAliasCache::class, $store);

    Cache::shouldReceive('put')
        ->once()
        ->withArgs(fn (string $key, array $value, DateTimeInterface $ttl): bool => $key === EntriesBySchemaType::CACHE_KEY_ALIASES
            && $value === $expected
            // Ten years: effectively forever, since the list changes only when the enum is
            // regenerated.
            && abs($ttl->getTimestamp() - now()->addYears(10)->getTimestamp()) < 60);

    EntriesBySchemaType::warmAliasesCache(['Thing', '3DModel', 'MedicalCondition', 'Thing']);
});
