<?php

declare(strict_types=1);

use App\Scopes\OntologyOverlapLocations;
use Illuminate\Support\Collection;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Query\Scopes\Scope;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Real-entry behavior of `OntologyOverlapLocations` (UAMS-Web/uams-statamic-archive#1366) against an isolated
 * Stache — not a mocked builder.
 *
 * The scope must filter the stored `clinical_locations` Entries field (the
 * universal FaD locations handle). It previously filtered a bare `locations`
 * handle that no collection stores, so on the Stache builder any supplied ids
 * yielded an EMPTY result. A mocked-builder test that only asserts the
 * `whereJsonOverlaps('locations', …)` call shape stays green through that; a real
 * query does not.
 *
 * @covers \App\Scopes\OntologyOverlapLocations
 *
 * @command  php artisan test --compact tests/Feature/Scopes/OntologyOverlapLocationsScopeTest.php
 */
uses(StacheIsolatedTestCase::class);

/**
 * Apply a Statamic query scope through its base `Scope` contract, to the Statamic
 * query builder the scope operates on.
 *
 * @param  array<string, mixed>  $values
 */
function applyOntologyOverlapScope(Scope $scope, EntryQueryBuilder $query, array $values): void
{
    $scope->apply($query, $values);
}

/**
 * Apply the scope to a `people` query with the given `locations` block param and
 * return the matching slugs.
 *
 * @return array<int, string>
 */
function ontologyLocationSlugs(mixed $locations): array
{
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'people');

    // Invoke through the base `Scope` contract: the concrete scope's PHPDoc narrows
    // `$query` to an Eloquent builder, but Statamic passes — and this scope operates
    // on — the Statamic query builder that the inherited signature types correctly.
    applyOntologyOverlapScope(new OntologyOverlapLocations, $query, ['locations' => $locations]);

    /** @var Collection<int, Statamic\Entries\Entry> $results */
    $results = $query->get();

    /** @var list<string> $slugs */
    $slugs = $results
        ->map(function (Statamic\Entries\Entry $entry): string {
            /** @var string $slug */
            $slug = $entry->slug();

            return $slug;
        })
        ->sort()
        ->values()
        ->all();

    return $slugs;
}

/**
 * Resolve a clinical_locations entry id from its slug.
 */
function locationId(string $slug): string
{
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'clinical_locations');
    $query->where('slug', $slug);

    /** @var Statamic\Entries\Entry $entry */
    $entry = $query->first();

    /** @var string $id */
    $id = $entry->id();

    return $id;
}

beforeEach(function (): void {
    $clinicalLocations = CollectionFacade::make('clinical_locations');
    $clinicalLocations->title('Clinical Locations');
    $clinicalLocations->save();

    $people = CollectionFacade::make('people');
    $people->title('People');
    $people->save();

    $personBlueprint = Blueprint::make('person');
    $personBlueprint->setNamespace('collections.people');
    $personBlueprint->setContents([
        'tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
            ['handle' => 'clinical_locations', 'field' => ['type' => 'entries', 'collections' => ['clinical_locations']]],
        ]]]]],
    ]);
    $personBlueprint->save();

    /** @var Statamic\Entries\Entry $locOne */
    $locOne = Entry::make();
    /** @var Statamic\Entries\Entry $locOne */
    $locOne = $locOne->collection('clinical_locations');
    /** @var Statamic\Entries\Entry $locOne */
    $locOne = $locOne->slug('loc-one');
    /** @var Statamic\Entries\Entry $locOne */
    $locOne = $locOne->data(['title' => 'One']);
    $locOne->save();

    /** @var Statamic\Entries\Entry $locTwo */
    $locTwo = Entry::make();
    /** @var Statamic\Entries\Entry $locTwo */
    $locTwo = $locTwo->collection('clinical_locations');
    /** @var Statamic\Entries\Entry $locTwo */
    $locTwo = $locTwo->slug('loc-two');
    /** @var Statamic\Entries\Entry $locTwo */
    $locTwo = $locTwo->data(['title' => 'Two']);
    $locTwo->save();

    /** @var Statamic\Entries\Entry $providerOne */
    $providerOne = Entry::make();
    /** @var Statamic\Entries\Entry $providerOne */
    $providerOne = $providerOne->collection('people');
    /** @var Statamic\Entries\Entry $providerOne */
    $providerOne = $providerOne->slug('provider-one');
    /** @var Statamic\Entries\Entry $providerOne */
    $providerOne = $providerOne->data(['title' => 'Dr One', 'clinical_locations' => [$locOne->id()]]);
    $providerOne->save();

    /** @var Statamic\Entries\Entry $providerTwo */
    $providerTwo = Entry::make();
    /** @var Statamic\Entries\Entry $providerTwo */
    $providerTwo = $providerTwo->collection('people');
    /** @var Statamic\Entries\Entry $providerTwo */
    $providerTwo = $providerTwo->slug('provider-two');
    /** @var Statamic\Entries\Entry $providerTwo */
    $providerTwo = $providerTwo->data(['title' => 'Dr Two', 'clinical_locations' => [$locTwo->id()]]);
    $providerTwo->save();
});

it('narrows providers to those related to the requested location', function (): void {
    expect(ontologyLocationSlugs([locationId('loc-one')]))->toBe(['provider-one']);
});

it('accepts a comma-joined id string across multiple locations', function (): void {
    $joined = implode(',', [locationId('loc-one'), locationId('loc-two')]);

    expect(ontologyLocationSlugs($joined))->toBe(['provider-one', 'provider-two']);
});

it('leaves the query unfiltered when no location is provided', function (): void {
    expect(ontologyLocationSlugs(''))->toBe(['provider-one', 'provider-two']);
});
