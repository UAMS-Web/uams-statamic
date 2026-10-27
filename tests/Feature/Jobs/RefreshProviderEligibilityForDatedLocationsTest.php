<?php

declare(strict_types=1);

use App\Jobs\RefreshProviderEligibilityForDatedLocations;
use App\Services\Api\FindADoc\FindADocProviderRepository;
use App\Support\People\Lenses\Gates\ClinicalProviderLensGate;
use App\Support\People\ProviderEligibilityIndexRefresher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Statamic\Entries\Entry as EntryInstance;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\Stache;
use Statamic\Sites\Site as SiteInstance;
use Statamic\Stache\Indexes\Index;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Stache\Stores\AggregateStore;
use Statamic\Stache\Stores\Store;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Tests that a future-dated practice location confers provider eligibility only from its date (UAMS-Web/uams-statamic-archive#3178).
 *
 * `clinical_locations` is built here as it is configured in content: dated, with future entries private
 * and past entries public. Each case reads the four surfaces a provider appears on. The provider
 * directory and Find-a-Doc provider selection both filter on the people eligibility index; the clinical
 * provider lens and the Find-a-Doc API evaluate eligibility when asked.
 *
 * @covers \App\Jobs\RefreshProviderEligibilityForDatedLocations
 * @covers \App\Support\People\ProviderEligibilityIndexRefresher
 * @covers \App\Support\People\ClinicalProviderProfileEligibility
 *
 * @command  php artisan test --compact tests/Feature/Jobs/RefreshProviderEligibilityForDatedLocationsTest.php
 */
uses(StacheIsolatedTestCase::class);

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-10-20 09:00:00'));
    Cache::forget(RefreshProviderEligibilityForDatedLocations::LAST_RUN_KEY);

    $people = CollectionFacade::make('people');
    $people->title('People');
    $people->save();

    $locations = CollectionFacade::make('clinical_locations');
    $locations->title('Clinical Locations');
    $locations->dated(true);
    $locations->futureDateBehavior('private');
    $locations->pastDateBehavior('public');
    $locations->save();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/**
 * Persist a practice location with the given date and published flag.
 */
function datedLocation(string $slug, string $date, bool $published = true): EntryInstance
{
    /** @var EntryInstance $location */
    $location = Entry::make();
    $location->collection('clinical_locations');
    $location->slug($slug);
    $location->date(Carbon::parse($date));
    $location->published($published);
    $location->data(['title' => $slug]);
    $location->saveQuietly();

    return $location;
}

/**
 * Persist a person who passes every rule except, possibly, the practice location one.
 */
function datedLocationPerson(string $slug, EntryInstance $location): EntryInstance
{
    /** @var EntryInstance $person */
    $person = Entry::make();
    $person->collection('people');
    $person->slug($slug);
    $person->published(true);
    $person->data([
        'title' => $slug,
        'uams_worker_query' => true,
        'uams_worker_subtype' => 'employee',
        'uams_employee_subtype' => 'regular',
        'resident_query' => false,
        'fellow_query' => false,
        'clinical_role_query' => true,
        'direct_patient_care_query' => true,
        'credentials' => ['md'],
        'clinical_locations' => [$location->id()],
    ]);
    $person->saveQuietly();

    return $person;
}

/**
 * The people store's eligibility index.
 */
function datedLocationEligibilityIndex(): Index
{
    /** @var AggregateStore $entries */
    $entries = Stache::store('entries');

    /** @var Store $people */
    $people = $entries->store('people');

    /** @var Index $index */
    $index = $people->index(ProviderEligibilityIndexRefresher::ELIGIBILITY_INDEX);

    return $index;
}

/**
 * Which of the four provider surfaces list the person.
 *
 * @return array{directory: bool, lens: bool, find_a_doc_selection: bool, find_a_doc_api: bool}
 */
function providerSurfaces(EntryInstance $person): array
{
    // The directory and the Find-a-Doc providers block both filter on the eligibility index
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'people');
    $query->where(ProviderEligibilityIndexRefresher::ELIGIBILITY_INDEX, true);

    /** @var Collection<int, EntryInstance> $eligible */
    $eligible = $query->get();
    $indexed = $eligible->contains(fn (EntryInstance $entry): bool => $entry->id() === $person->id());

    /** @var string $id */
    $id = $person->id();

    /** @var EntryInstance $fresh */
    $fresh = Entry::find($id);

    /** @var SiteInstance $site */
    $site = Site::default();

    return [
        'directory' => $indexed,
        'lens' => app(ClinicalProviderLensGate::class)->passes($fresh, $site),
        'find_a_doc_selection' => $indexed,
        'find_a_doc_api' => app(FindADocProviderRepository::class)->findEligible($id) instanceof EntryInstance,
    ];
}

/**
 * Run the job as the queue would.
 */
function runDatedLocationRefresh(): void
{
    app()->call([new RefreshProviderEligibilityForDatedLocations, 'handle']);
}

it('keeps a provider whose only location is scheduled off every surface', function (): void {
    $location = datedLocation('opening-soon', '2026-10-25');
    $person = datedLocationPerson('beverly-crusher', $location);

    /** @var EntryInstance $stored */
    $stored = Entry::find($location->id());

    expect($stored->status())->toBe('scheduled')
        ->and(providerSurfaces($person))->toBe([
            'directory' => false,
            'lens' => false,
            'find_a_doc_selection' => false,
            'find_a_doc_api' => false,
        ]);
});

it('lists the provider everywhere once the date passes and the refresh runs, with no save', function (): void {
    $location = datedLocation('opening-soon', '2026-10-25');
    $person = datedLocationPerson('beverly-crusher', $location);

    // A run before the date records its time and changes nothing
    runDatedLocationRefresh();
    expect(providerSurfaces($person)['directory'])->toBeFalse();

    Carbon::setTestNow(Carbon::parse('2026-10-25 09:00:00'));

    // The index is stale until the refresh: nothing was saved when the date arrived
    expect(providerSurfaces($person)['directory'])->toBeFalse();

    runDatedLocationRefresh();

    expect(providerSurfaces($person))->toBe([
        'directory' => true,
        'lens' => true,
        'find_a_doc_selection' => true,
        'find_a_doc_api' => true,
    ]);
});

it('keeps a provider with a published, past-dated location on every surface', function (): void {
    $location = datedLocation('open-already', '2026-01-05');
    $person = datedLocationPerson('jane-doe', $location);

    expect(providerSurfaces($person))->toBe([
        'directory' => true,
        'lens' => true,
        'find_a_doc_selection' => true,
        'find_a_doc_api' => true,
    ]);
});

it('keeps a provider whose only location is a draft off every surface, before and after its date', function (): void {
    $location = datedLocation('never-opened', '2026-10-25', published: false);
    $person = datedLocationPerson('wesley-crusher', $location);

    runDatedLocationRefresh();
    Carbon::setTestNow(Carbon::parse('2026-10-26 09:00:00'));
    runDatedLocationRefresh();

    expect(providerSurfaces($person))->toBe([
        'directory' => false,
        'lens' => false,
        'find_a_doc_selection' => false,
        'find_a_doc_api' => false,
    ]);
});

it('re-indexes only the people linked to locations whose date passed since the last run', function (): void {
    $older = datedLocation('open-already', '2026-01-05');
    $passing = datedLocation('opening-soon', '2026-10-25');
    $unchanged = datedLocationPerson('data', $older);
    $newlyEligible = datedLocationPerson('john-smith', $passing);

    runDatedLocationRefresh();

    // Values only a refresh would replace: the planted false on a person whose location
    // passed before the last run must survive the next one
    $index = datedLocationEligibilityIndex();
    $index->put($unchanged->id(), false);
    $index->cache();

    Carbon::setTestNow(Carbon::parse('2026-10-25 09:00:00'));
    runDatedLocationRefresh();

    expect(datedLocationEligibilityIndex()->get($unchanged->id()))->toBeFalse()
        ->and(datedLocationEligibilityIndex()->get($newlyEligible->id()))->toBeTrue();
});

it('treats every past-dated location as passed when no run is recorded', function (): void {
    $older = datedLocation('open-already', '2026-01-05');
    $person = datedLocationPerson('data', $older);

    $index = datedLocationEligibilityIndex();
    $index->put($person->id(), false);
    $index->cache();

    runDatedLocationRefresh();

    expect(datedLocationEligibilityIndex()->get($person->id()))->toBeTrue()
        ->and(Cache::get(RefreshProviderEligibilityForDatedLocations::LAST_RUN_KEY))
        ->toBe(Carbon::parse('2026-10-20 09:00:00')->getTimestamp());
});

it('leaves a location dated after now for a later run', function (): void {
    $passing = datedLocation('opening-soon', '2026-10-25');
    $person = datedLocationPerson('john-smith', $passing);

    $index = datedLocationEligibilityIndex();
    $index->put($person->id(), true);
    $index->cache();

    runDatedLocationRefresh();

    expect(datedLocationEligibilityIndex()->get($person->id()))->toBeTrue();
});
