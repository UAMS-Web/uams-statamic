<?php

declare(strict_types=1);

use App\Scopes\ExcludeUnsearchableEntries;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Query\Builder;
use Tests\TestCase;

covers(ExcludeUnsearchableEntries::class);

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Narrow mutate coverage for App\Scopes\ExcludeUnsearchableEntries.
 *
 * EntryFacade::query() and the scoped builder are mocked so mutant children
 * never touch the Stache.
 *
 * @command  php artisan test --compact tests/Unit/Scopes/ExcludeUnsearchableEntriesMutationTest.php
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
 * @param  list<Entry>  $gated
 */
function excludeUnsearchableStubLookup(array $gated): void
{
    $lookup = Mockery::mock();
    $lookup->shouldReceive('whereIn')
        ->once()
        ->with(
            'collection',
            ['clinical_expertise'],
        )
        ->andReturnSelf();
    $lookup->shouldReceive('get')->once()->andReturn(collect($gated));

    EntryFacade::shouldReceive('query')->once()->andReturn($lookup);
}

function excludeUnsearchableEntry(mixed $searchable, string|int|null $id): Entry
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')->with('searchable')->andReturn($searchable);
    $entry->shouldReceive('id')->andReturn($id);

    return $entry;
}

it('applies excludeUnsearchable through the Scope apply() entry point', function (): void {
    excludeUnsearchableStubLookup([]);

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereNotIn')->never();

    (new ExcludeUnsearchableEntries)->apply(
        $query,
        [],
    );
});

it('does not constrain when no gated entry is strictly unsearchable', function (): void {
    excludeUnsearchableStubLookup([
        excludeUnsearchableEntry(
            null,
            'missing-toggle',
        ),
        excludeUnsearchableEntry(
            true,
            'explicit-true',
        ),
    ]);

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereNotIn')->never();

    ExcludeUnsearchableEntries::excludeUnsearchable($query);
});

it('whereNotIns only string ids whose searchable toggle is strictly false', function (): void {
    excludeUnsearchableStubLookup([
        excludeUnsearchableEntry(
            false,
            'off-a',
        ),
        excludeUnsearchableEntry(
            false,
            'off-b',
        ),
        excludeUnsearchableEntry(
            false,
            123,
        ),
        excludeUnsearchableEntry(
            true,
            'kept',
        ),
    ]);

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereNotIn')
        ->once()
        ->with(
            'id',
            [
                'off-a',
                'off-b',
            ],
        )
        ->andReturnSelf();

    ExcludeUnsearchableEntries::excludeUnsearchable($query);
});

it('skips whereNotIn when every strictly-false entry lacks a string id', function (): void {
    excludeUnsearchableStubLookup([
        excludeUnsearchableEntry(
            false,
            null,
        ),
        excludeUnsearchableEntry(
            false,
            99,
        ),
    ]);

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereNotIn')->never();

    ExcludeUnsearchableEntries::excludeUnsearchable($query);
});
