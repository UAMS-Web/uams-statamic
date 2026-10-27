<?php

declare(strict_types=1);

use App\Scopes\ExcludeSuppressedDependents;
use App\Services\PublishDependencies\PublishDependencyResolver;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Query\Builder;
use Tests\TestCase;

covers(ExcludeSuppressedDependents::class);

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * UAMS-Web/uams-statamic-archive#1593: the listing-exclusion query scope drops every currently-suppressed
 * dependent from a collection listing by excluding the resolver's global
 * suppressed-id set. Mocked resolver + query builder — no content/ submodule
 * required.
 *
 * Mutation: tracked under App\Scopes\ExcludeSuppressedDependents (--covered-only).
 *
 * @command php artisan test --compact tests/Feature/Scopes/ExcludeSuppressedDependentsTest.php
 */

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``.
 */
beforeEach(function (): void {
    static $padded = false;
    if ($padded) {
        return;
    }

    if (runningUnderMutate()) {
        usleep(20_000_000);
        $padded = true;
    }
});

/**
 * Bind a resolver double returning the given suppressed-id set into the container.
 *
 * @param  list<string>  $ids
 */
function bindSuppressedIds(array $ids, bool $expectCall = true): void
{
    $resolver = Mockery::mock(PublishDependencyResolver::class);

    if ($expectCall) {
        $resolver->shouldReceive('suppressedEntryIds')->andReturn($ids);
    } else {
        $resolver->shouldNotReceive('suppressedEntryIds');
    }

    app()->instance(PublishDependencyResolver::class, $resolver);
}

it('excludes the suppressed dependent ids from the query', function (): void {
    bindSuppressedIds(['down-a', 'down-b']);

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereNotIn')->once()->with('id', ['down-a', 'down-b']);

    (new ExcludeSuppressedDependents)->apply($query, []);
});

it('leaves the query untouched when nothing is suppressed', function (): void {
    bindSuppressedIds([]);

    $query = Mockery::mock(Builder::class);
    $query->shouldNotReceive('whereNotIn');

    (new ExcludeSuppressedDependents)->apply($query, []);
});

it('does nothing and never consults the resolver for a non-builder value', function (): void {
    bindSuppressedIds([], expectCall: false);

    // A non-Builder value must be a clean no-op (scopes can be invoked off-query).
    $threw = null;

    try {
        (new ExcludeSuppressedDependents)->apply('not-a-query', []);
    } catch (Throwable $e) {
        $threw = $e->getMessage();
    }

    expect($threw)->toBeNull();
});
