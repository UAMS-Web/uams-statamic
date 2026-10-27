<?php

declare(strict_types=1);

use App\Services\ComputedFields\Reverse\PeopleReverseReferenceComputedValues;
use App\Services\ComputedFields\Reverse\ReverseEntryReferenceResolver;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Entries\EntryCollection;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(PeopleReverseReferenceComputedValues::class);

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``.
 */
beforeEach(function (): void {
    static $padded = false;
    if ($padded) {
        return;
    }

    if (runningUnderMutate()) {
        usleep(12_000_000);
        $padded = true;
    }
});

/*
|--------------------------------------------------------------------------
| PeopleReverseReferenceComputedValues (UAMS-Web/uams-statamic-archive#1374)
|--------------------------------------------------------------------------
|
| The `people` reverse-reference computeds are thin wiring over the shared
| ReverseEntryReferenceResolver (whose real-Stache behavior — scalar/array/multi
| and nested-group storage matching — is proved in ReverseEntryReferenceResolverTest).
| These mock the resolver to pin the exact call each reverse makes:
|
| - credited content deep-scans the nested `creator` group (resolveNested) across
|   the three content collections that author it;
| - the clinical-provider entry is a flat top-level pointer (resolve) on the
|   clinical_providers collection.
|
| @covers \App\Services\ComputedFields\Reverse\PeopleReverseReferenceComputedValues
|
| @command php artisan test --compact tests/Feature/Services/ComputedFields/Reverse/PeopleReverseReferenceComputedValuesTest.php
*/

function peopleReverseEntry(?string $id): Entry
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn($id);

    return $entry;
}

it('resolves credited content by deep-scanning the creator group across the content collections', function (): void {
    $expected = EntryCollection::make();
    $entry = peopleReverseEntry('person-1');

    $resolver = Mockery::mock(ReverseEntryReferenceResolver::class);
    // Must use the NESTED matcher (the creator group's per-role sub-fields), not a
    // shallow resolve, and span all three creator-crediting collections.
    $resolver->shouldReceive('resolveNested')
        ->once()
        ->with(['posts', 'clinical_resources', 'knowledge_bases'], ['creator', 'segments'], 'person-1')
        ->andReturn($expected);
    $resolver->shouldReceive('resolve')->never();

    expect((new PeopleReverseReferenceComputedValues($resolver))->resolveCreditedContent($entry))
        ->toBe($expected);
});

it('resolves the clinical-provider entry from the flat top-level person pointer', function (): void {
    $expected = EntryCollection::make();
    $entry = peopleReverseEntry('person-2');

    $resolver = Mockery::mock(ReverseEntryReferenceResolver::class);
    // A top-level 1:1 pointer — a shallow resolve, not the nested matcher.
    $resolver->shouldReceive('resolve')
        ->once()
        ->with('clinical_providers', 'person', 'person-2')
        ->andReturn($expected);
    $resolver->shouldReceive('resolveNested')->never();

    expect((new PeopleReverseReferenceComputedValues($resolver))->resolveClinicalProviderEntry($entry))
        ->toBe($expected);
});

it('passes the entry id straight through, including a null id for an unsaved person', function (): void {
    // The resolver owns the id-less short-circuit; the service must forward whatever
    // id() returns without pre-filtering, so an unsaved person still delegates.
    $credited = EntryCollection::make();
    $provider = EntryCollection::make();
    $entry = peopleReverseEntry(null);

    $resolver = Mockery::mock(ReverseEntryReferenceResolver::class);
    $resolver->shouldReceive('resolveNested')->once()->with(['posts', 'clinical_resources', 'knowledge_bases'], ['creator', 'segments'], null)->andReturn($credited);
    $resolver->shouldReceive('resolve')->once()->with('clinical_providers', 'person', null)->andReturn($provider);

    $service = new PeopleReverseReferenceComputedValues($resolver);

    expect($service->resolveCreditedContent($entry))->toBe($credited)
        ->and($service->resolveClinicalProviderEntry($entry))->toBe($provider);
});
