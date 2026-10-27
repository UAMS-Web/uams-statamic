<?php

declare(strict_types=1);

use App\Modifiers\ClinicalProviderEligible;
use App\Services\ComputedFields\ProviderPeople\ProviderPeopleListingComputedValues;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(ClinicalProviderEligible::class);

/**
 * Narrow mutate coverage for ClinicalProviderEligible.
 *
 * @command  php artisan test --compact tests/Unit/Modifiers/ClinicalProviderEligibleMutationTest.php
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
 * Keys are entry ids; PHP stores a numeric-string id such as '42' as an int key.
 *
 * @param  array<array-key, array{eligible: bool, searchable: bool}>  $byId
 */
function clinicalProviderEligibleModifier(array $byId): ClinicalProviderEligible
{
    $computed = Mockery::mock(ProviderPeopleListingComputedValues::class);
    $computed->shouldReceive('computeClinicalProviderProfileEligibleQuery')
        ->andReturnUsing(function (Entry $entry) use ($byId): bool {
            $id = mustBeString($entry->id(), 'the entry id');

            return $byId[$id]['eligible'] ?? false;
        });
    $computed->shouldReceive('computeSearchable')
        ->andReturnUsing(function (Entry $entry) use ($byId): bool {
            $id = mustBeString($entry->id(), 'the entry id');

            return $byId[$id]['searchable'] ?? false;
        });

    return new ClinicalProviderEligible($computed);
}

function clinicalProviderEligibleEntry(string $id): Entry
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn($id);

    return $entry;
}

it('keeps entries that are both eligible and searchable', function (): void {
    $keep = clinicalProviderEligibleEntry('keep');
    $dropEligible = clinicalProviderEligibleEntry('drop-eligible');
    $dropSearchable = clinicalProviderEligibleEntry('drop-search');

    $modifier = clinicalProviderEligibleModifier([
        'keep' => ['eligible' => true, 'searchable' => true],
        'drop-eligible' => ['eligible' => false, 'searchable' => true],
        'drop-search' => ['eligible' => true, 'searchable' => false],
    ]);

    // Non-entry first: ContinueToBreak would stop before $keep.
    expect($modifier->index([null, $keep, $dropEligible, $dropSearchable], [], []))->toBe([$keep]);
});

it('resolves string and int ids through EntryFacade before filtering', function (): void {
    $keep = clinicalProviderEligibleEntry('keep');
    $fromInt = clinicalProviderEligibleEntry('42');
    EntryFacade::shouldReceive('find')->with('keep')->andReturn($keep);
    // RemoveStringCast would pass int 42; require the string cast explicitly.
    EntryFacade::shouldReceive('find')
        ->once()
        ->withArgs(static fn (mixed $id): bool => $id === '42')
        ->andReturn($fromInt);

    $modifier = clinicalProviderEligibleModifier([
        'keep' => ['eligible' => true, 'searchable' => true],
        '42' => ['eligible' => true, 'searchable' => true],
    ]);

    expect($modifier->index(['keep', 42, null, ['not-scalar']], [], []))->toBe([$keep, $fromInt]);
});

it('returns an empty list when an id does not resolve', function (): void {
    EntryFacade::shouldReceive('find')->with('bogus')->andReturnNull();

    $modifier = clinicalProviderEligibleModifier([]);

    expect($modifier->index(['bogus'], [], []))->toBe([]);
});
