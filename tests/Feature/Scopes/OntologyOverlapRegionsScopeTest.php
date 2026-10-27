<?php

declare(strict_types=1);

use App\Scopes\OntologyOverlapRegions;
use Illuminate\Support\Collection;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Taxonomies\LocalizedTerm;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Real-entry behavior of `OntologyOverlapRegions` (UAMS-Web/uams-statamic-archive#1366) against an isolated
 * Stache — not a mocked builder.
 *
 * `regions` is a computed-only aggregate on providers/people, never stored, so it
 * cannot be a `where` target. The scope therefore routes through the authoritative
 * `regions` on `clinical_locations`: it resolves the block's region slugs to the
 * ids of locations in those regions, then constrains the ontology query IN PLACE
 * with `whereJsonOverlaps('clinical_locations', $ids)`.
 *
 * The previous implementation `->get()`-then-filtered-in-PHP and RETURNED a new
 * collection — a silent no-op, since Statamic's `Scope::apply()` is `@return void`
 * and every call site discards the return, so the outer query stayed unfiltered.
 *
 * @covers \App\Scopes\OntologyOverlapRegions
 *
 * @command  php artisan test --compact tests/Feature/Scopes/OntologyOverlapRegionsScopeTest.php
 */
uses(StacheIsolatedTestCase::class);

/**
 * Apply the scope to a `people` query with the given `regions` block param and
 * return the matching slugs (mutating the query IN PLACE, ignoring any return).
 *
 * @return array<int, string>
 */
function ontologyRegionSlugs(mixed $regions): array
{
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'people');

    (new OntologyOverlapRegions)->apply($query, ['regions' => $regions]);

    /** @var Collection<int, Statamic\Entries\Entry> $entries */
    $entries = $query->get();

    return $entries
        ->map(function (Statamic\Entries\Entry $entry): string {
            /** @var string $slug */
            $slug = $entry->slug();

            return $slug;
        })
        ->sort()
        ->values()
        ->all();
}

beforeEach(function (): void {
    /** @var Statamic\Taxonomies\Taxonomy $regionsTaxonomy */
    $regionsTaxonomy = Taxonomy::make('regions');
    $regionsTaxonomy->title('Regions');
    $regionsTaxonomy->save();

    /** @var Statamic\Taxonomies\Term $centralTerm */
    $centralTerm = Term::make();
    $centralTerm->taxonomy('regions');
    $centralTerm->slug('central-arkansas');
    /** @var LocalizedTerm $centralTermLocalized */
    $centralTermLocalized = $centralTerm->inDefaultLocale();
    $centralTermLocalized->data(['title' => 'Central Arkansas']);
    $centralTerm->saveQuietly();

    /** @var Statamic\Taxonomies\Term $northwestTerm */
    $northwestTerm = Term::make();
    $northwestTerm->taxonomy('regions');
    $northwestTerm->slug('northwest-arkansas');
    /** @var LocalizedTerm $northwestTermLocalized */
    $northwestTermLocalized = $northwestTerm->inDefaultLocale();
    $northwestTermLocalized->data(['title' => 'Northwest Arkansas']);
    $northwestTerm->saveQuietly();

    $clinicalLocations = CollectionFacade::make('clinical_locations');
    $clinicalLocations->title('Clinical Locations');
    $clinicalLocations->save();

    $people = CollectionFacade::make('people');
    $people->title('People');
    $people->save();

    // `regions` term field is authoritative on the location; `clinical_locations`
    // entries field points a provider at its locations.
    $locationBlueprint = Blueprint::make('location');
    $locationBlueprint->setNamespace('collections.clinical_locations');
    $locationBlueprint->setContents([
        'tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
            ['handle' => 'regions', 'field' => ['type' => 'terms', 'taxonomies' => ['regions']]],
        ]]]]],
    ]);
    $locationBlueprint->save();

    $personBlueprint = Blueprint::make('person');
    $personBlueprint->setNamespace('collections.people');
    $personBlueprint->setContents([
        'tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
            ['handle' => 'clinical_locations', 'field' => ['type' => 'entries', 'collections' => ['clinical_locations']]],
        ]]]]],
    ]);
    $personBlueprint->save();

    /** @var Statamic\Entries\Entry $locCentral */
    $locCentral = Entry::make();
    /** @var Statamic\Entries\Entry $locCentral */
    $locCentral = $locCentral->collection('clinical_locations');
    /** @var Statamic\Entries\Entry $locCentral */
    $locCentral = $locCentral->slug('loc-central');
    /** @var Statamic\Entries\Entry $locCentral */
    $locCentral = $locCentral->data(['title' => 'Central Clinic', 'regions' => ['central-arkansas']]);
    $locCentral->saveQuietly();

    /** @var Statamic\Entries\Entry $locNw */
    $locNw = Entry::make();
    /** @var Statamic\Entries\Entry $locNw */
    $locNw = $locNw->collection('clinical_locations');
    /** @var Statamic\Entries\Entry $locNw */
    $locNw = $locNw->slug('loc-nw');
    /** @var Statamic\Entries\Entry $locNw */
    $locNw = $locNw->data(['title' => 'NW Clinic', 'regions' => ['northwest-arkansas']]);
    $locNw->saveQuietly();

    /** @var Statamic\Entries\Entry $providerCentral */
    $providerCentral = Entry::make();
    /** @var Statamic\Entries\Entry $providerCentral */
    $providerCentral = $providerCentral->collection('people');
    /** @var Statamic\Entries\Entry $providerCentral */
    $providerCentral = $providerCentral->slug('provider-central');
    /** @var Statamic\Entries\Entry $providerCentral */
    $providerCentral = $providerCentral->data(['title' => 'Dr Central', 'clinical_locations' => [$locCentral->id()]]);
    $providerCentral->saveQuietly();

    /** @var Statamic\Entries\Entry $providerNw */
    $providerNw = Entry::make();
    /** @var Statamic\Entries\Entry $providerNw */
    $providerNw = $providerNw->collection('people');
    /** @var Statamic\Entries\Entry $providerNw */
    $providerNw = $providerNw->slug('provider-nw');
    /** @var Statamic\Entries\Entry $providerNw */
    $providerNw = $providerNw->data(['title' => 'Dr NW', 'clinical_locations' => [$locNw->id()]]);
    $providerNw->saveQuietly();
});

it('narrows providers to those whose locations overlap the requested region', function (): void {
    expect(ontologyRegionSlugs('central-arkansas'))->toBe(['provider-central']);
});

it('accepts a prefixed region reference and a comma-joined string', function (): void {
    expect(ontologyRegionSlugs('regions::central-arkansas,regions::northwest-arkansas'))
        ->toBe(['provider-central', 'provider-nw']);
});

it('accepts a region slug array', function (): void {
    expect(ontologyRegionSlugs(['northwest-arkansas']))->toBe(['provider-nw']);
});

it('returns no providers when no location sits in the requested region', function (): void {
    expect(ontologyRegionSlugs('nonexistent-region'))->toBe([]);
});

it('leaves the query unfiltered when no region is provided', function (): void {
    expect(ontologyRegionSlugs(''))->toBe(['provider-central', 'provider-nw']);
});
