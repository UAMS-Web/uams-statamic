<?php

declare(strict_types=1);

use App\Scopes\ClinicalResourcesForProvider;
use Statamic\Entries\Entry as EntryObject;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\Support\StacheIsolatedTestCase;

/**
 * The `?provider=` filter on the `clinical_resources` archive (UAMS-Web/uams-statamic-archive#1692).
 *
 * Runs against real persisted entries because the filter IS a relation walk in
 * the opposite direction — `clinical_resources` is a field on `people`, so the
 * scope has to find the person before it can constrain the resource query.
 *
 * @command php artisan test --compact tests/Feature/Scopes/ClinicalResourcesForProviderTest.php
 */
uses(StacheIsolatedTestCase::class);

beforeEach(function (): void {
    $people = CollectionFacade::make('people');
    $people->title('People');
    $people->save();

    $resources = CollectionFacade::make('clinical_resources');
    $resources->title('Clinical Resources');
    $resources->save();

    $providers = CollectionFacade::make('clinical_providers');
    $providers->title('Clinical Providers');
    $providers->save();
});

/**
 * Persist one clinical resource and return its id.
 */
function providerFilterResource(string $id, bool $published = true): string
{
    /** @var EntryObject $entry */
    $entry = Entry::make();
    $entry->collection('clinical_resources');
    $entry->id($id);
    $entry->slug($id);
    $entry->published($published);
    $entry->data(['title' => ucfirst($id)]);
    $entry->saveQuietly();

    return $id;
}

/**
 * Persist one provider relating the given resource ids.
 *
 * @param  list<string>|string  $resourceIds
 * @param  array<string, mixed>  $data
 */
function providerFilterPerson(string $slug, array|string $resourceIds = [], bool $published = true, array $data = []): void
{
    /** @var EntryObject $entry */
    $entry = Entry::make();
    $entry->collection('people');
    $entry->id($slug);
    $entry->slug($slug);
    $entry->published($published);
    $entry->data($data + [
        'title' => ucfirst($slug),
        'clinical_resources' => $resourceIds,
    ]);
    $entry->saveQuietly();
}

/**
 * The resource ids the archive lists for the given `?provider=` value.
 *
 * @return list<string>
 */
function providerFilterResults(?string $provider): array
{
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'clinical_resources');

    app(ClinicalResourcesForProvider::class)->apply($query, ['provider' => $provider]);

    $ids = [];
    $results = $query->get();

    if (! is_iterable($results)) {
        return $ids;
    }

    foreach ($results as $entry) {
        if (! $entry instanceof EntryObject) {
            continue;
        }

        $id = $entry->id();

        if (is_scalar($id)) {
            $ids[] = (string) $id;
        }
    }

    return $ids;
}

// --- The filter ---------------------------------------------------------------

it('lists only the resources the requested provider relates', function (): void {
    $mine = providerFilterResource('spock-protocol');
    providerFilterResource('unrelated-guide');
    providerFilterPerson('spock', [$mine]);

    expect(providerFilterResults('spock'))->toBe([$mine]);
});

it('lists every resource the provider relates, whatever the stored order', function (): void {
    // Deliberately NOT an order assertion. The scope constrains membership; the
    // listing sorts `date:desc`, so the stored order never reaches the reader. An
    // earlier version of this test claimed to pin "entered order" while using
    // `toEqualCanonicalizing`, which ignores order and could never have failed on it.
    $second = providerFilterResource('beta-guide');
    $first = providerFilterResource('alpha-guide');
    providerFilterPerson('mccoy', [$first, $second]);

    expect(providerFilterResults('mccoy'))->toEqualCanonicalizing([$first, $second]);
});

// --- The unfiltered cases ------------------------------------------------------

it('leaves the archive unfiltered for an unknown provider slug', function (): void {
    // A stale or mistyped link degrades to browse-all rather than an empty state,
    // matching how `clinical_resource_type` treats an absent type.
    $one = providerFilterResource('one-guide');
    $two = providerFilterResource('two-guide');

    expect(providerFilterResults('nobody-by-this-name'))->toEqualCanonicalizing([$one, $two]);
});

it('leaves the archive unfiltered for an empty, blank, or missing provider', function (mixed $provider): void {
    $one = providerFilterResource('solo-guide');

    expect(providerFilterResults(is_string($provider) ? $provider : null))->toBe([$one]);
})->with([[''], ['   '], [null]]);

it('leaves the archive unfiltered for a provider the public cannot open', function (array $state): void {
    /** @var array<string, mixed> $data */
    $data = is_array($state['data'] ?? null) ? $state['data'] : [];

    // The gate is the full publicly-renderable one, not just publication. A private
    // or password-protected person has a profile the public cannot open, so naming
    // them in the notice — and listing what they relate — would leak through a query
    // parameter exactly what the lens route withholds.
    $mine = providerFilterResource('confidential-guide');
    $other = providerFilterResource('public-guide');

    providerFilterPerson(
        'hidden',
        [$mine],
        published: is_bool($state['published'] ?? null) ? $state['published'] : true,
        data: $data
    );

    expect(providerFilterResults('hidden'))->toEqualCanonicalizing([$mine, $other]);
})->with([
    'unpublished' => [['published' => false]],
    'private' => [['data' => ['status_and_visibility' => 'private']]],
    'password protected' => [['data' => ['password_protected' => true]]],
]);

it('filters for a published, publicly-visible provider', function (): void {
    // The control for the case above: the same shape, with nothing withheld.
    $mine = providerFilterResource('open-guide');
    providerFilterResource('somebody-elses-open-guide');
    providerFilterPerson('open', [$mine], data: ['status_and_visibility' => 'public']);

    expect(providerFilterResults('open'))->toBe([$mine]);
});

it('leaves the archive unfiltered for a slug that only exists in another collection', function (): void {
    // `clinical_resources` is a field on `people` and on no other blueprint, so
    // accepting a `clinical_providers` slug would resolve a real person, name them
    // in the notice, and then filter to an empty id list — an archive claiming to
    // show a named doctor's resources while showing nothing at all.
    $one = providerFilterResource('some-guide');

    /** @var EntryObject $entry */
    $entry = Entry::make();
    $entry->collection('clinical_providers');
    $entry->id('provider-spock');
    $entry->slug('spock');
    $entry->published(true);
    $entry->data(['title' => 'Spock']);
    $entry->saveQuietly();

    expect(providerFilterResults('spock'))->toBe([$one]);
});

// --- The genuinely-empty case --------------------------------------------------

it('lists nothing for a known provider who relates no resources', function (): void {
    // Distinct from the unknown-slug case above: this is a real, empty answer, so
    // the archive shows the empty state rather than everything.
    providerFilterResource('somebody-elses-guide');
    providerFilterPerson('uhura', []);

    expect(providerFilterResults('uhura'))->toBe([]);
});

it('lists nothing when the provider relates only ids that no longer exist', function (): void {
    providerFilterResource('live-guide');
    providerFilterPerson('chekov', ['deleted-resource']);

    expect(providerFilterResults('chekov'))->toBe([]);
});

it('accepts a relation stored as a bare id rather than a list', function (): void {
    $mine = providerFilterResource('sulu-guide');
    providerFilterResource('other-guide');
    providerFilterPerson('sulu', $mine);

    expect(providerFilterResults('sulu'))->toBe([$mine]);
});

// --- Composition ---------------------------------------------------------------

it('composes with a type constraint rather than replacing it', function (): void {
    // The listing pipes `clinical_resource_type|clinical_resources_for_provider`, so
    // the provider scope must only ever ADD constraints to an already-narrowed query.
    $mine = providerFilterResource('kirk-guide');
    $alsoMine = providerFilterResource('kirk-video');
    providerFilterPerson('kirk', [$mine, $alsoMine]);

    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'clinical_resources');
    $query->where('id', $mine);

    app(ClinicalResourcesForProvider::class)->apply($query, ['provider' => 'kirk']);

    $ids = [];
    $results = $query->get();

    if (is_iterable($results)) {
        foreach ($results as $entry) {
            if (! $entry instanceof EntryObject) {
                continue;
            }

            $id = $entry->id();

            if (is_scalar($id)) {
                $ids[] = (string) $id;
            }
        }
    }

    expect($ids)->toBe([$mine]);
});
