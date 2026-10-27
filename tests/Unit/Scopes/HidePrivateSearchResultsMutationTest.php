<?php

declare(strict_types=1);

use App\Scopes\HidePrivateSearchResults;
use App\Search\AreaOfExpertiseSectionDocument;
use App\Support\Entries\PrivateEntryAccess;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Query\Builder;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(HidePrivateSearchResults::class);

/**
 * Narrow mutate coverage for App\Scopes\HidePrivateSearchResults.
 *
 * EntryFacade::query() and PrivateEntryAccess are mocked so mutant children
 * never touch the Stache.
 *
 * @command  php artisan test --compact tests/Unit/Scopes/HidePrivateSearchResultsMutationTest.php
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
 * Stub EntryFacade::query() to return the given private entries.
 *
 * @param  list<Entry>  $privateEntries
 */
function hidePrivateStubLookup(array $privateEntries): void
{
    $lookup = Mockery::mock();
    $lookup->shouldReceive('where')
        ->once()
        ->with(
            'status_and_visibility',
            'private',
        )
        ->andReturnSelf();
    $lookup->shouldReceive('get')->once()->andReturn(collect($privateEntries));

    EntryFacade::shouldReceive('query')->once()->andReturn($lookup);
}

/**
 * @param  string|int|null  $id
 */
function hidePrivateEntry(mixed $id): Entry
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn($id);

    return $entry;
}

/**
 * Build the scope with a PrivateEntryAccess stub.
 *
 * @param  array<string, bool>  $canViewById  Map of entry id string to canView result
 */
function hidePrivateScope(array $canViewById = []): HidePrivateSearchResults
{
    $access = Mockery::mock(PrivateEntryAccess::class);
    $access->shouldReceive('canView')
        ->andReturnUsing(function (Entry $entry) use ($canViewById): bool {
            $id = $entry->id();

            return is_string($id) ? ($canViewById[$id] ?? false) : false;
        });

    return new HidePrivateSearchResults($access);
}

it('does nothing when the query is not a Statamic builder', function (): void {
    EntryFacade::shouldReceive('query')->never();

    hidePrivateScope()->apply(
        new stdClass,
        [],
    );
});

it('leaves the query untouched when no private entry is hidden from the reader', function (): void {
    $visible = hidePrivateEntry('vis-1');
    hidePrivateStubLookup([$visible]);

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereNotIn')->never();

    hidePrivateScope(['vis-1' => true])->apply(
        $query,
        [],
    );
});

it('skips private entries whose id is not a non-empty string', function (): void {
    hidePrivateStubLookup([
        hidePrivateEntry(null),
        hidePrivateEntry(''),
        hidePrivateEntry(42),
    ]);

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereNotIn')->never();

    hidePrivateScope()->apply(
        $query,
        [],
    );
});

it('excludes hidden private entry ids from both id and area-of-expertise fields', function (): void {
    hidePrivateStubLookup([
        hidePrivateEntry('priv-1'),
        hidePrivateEntry('vis-1'),
        hidePrivateEntry('priv-2'),
    ]);

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereNotIn')
        ->once()
        ->with(
            'id',
            ['priv-1', 'priv-2'],
        )
        ->andReturnSelf();
    $query->shouldReceive('whereNotIn')
        ->once()
        ->with(
            AreaOfExpertiseSectionDocument::AREA_OF_EXPERTISE_ID_FIELD,
            ['priv-1', 'priv-2'],
        )
        ->andReturnSelf();

    hidePrivateScope([
        'priv-1' => false,
        'vis-1' => true,
        'priv-2' => false,
    ])->apply(
        $query,
        [],
    );
});
