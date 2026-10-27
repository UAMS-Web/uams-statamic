<?php

declare(strict_types=1);

use Statamic\Entries\Entry as EntryInstance;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Stache;
use Statamic\Stache\Indexes\Index;
use Statamic\Stache\Stores\AggregateStore;
use Statamic\Stache\Stores\Store;
use Tests\Support\ClinicalProviderFixtures;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Tests that saving or deleting a practice location re-indexes the eligibility of exactly
 * the people who link it (UAMS-Web/uams-statamic-archive#3123).
 *
 * Each case plants a stale value in the people eligibility index, then saves an entry. Only a
 * refresh can replace the planted value, so the index shows which people the listener reached.
 *
 * @covers \App\Listeners\RefreshProviderEligibilityForLocation
 *
 * @command  php artisan test --compact tests/Feature/Listeners/RefreshProviderEligibilityForLocationTest.php
 */
uses(StacheIsolatedTestCase::class);

beforeEach(function (): void {
    foreach (['people', 'pages', 'clinical_locations'] as $handle) {
        $collection = CollectionFacade::make($handle);
        $collection->title($handle);
        $collection->save();
    }
});

/**
 * The people store's eligibility index.
 */
function eligibilityIndex(): Index
{
    /** @var AggregateStore $entries */
    $entries = Stache::store('entries');

    /** @var Store $people */
    $people = $entries->store('people');

    /** @var Index $index */
    $index = $people->index('clinical_provider_profile_eligible_query');

    return $index;
}

/**
 * Persist a person who passes every base rule and the credentials path.
 *
 * @param  array<string, mixed>  $fields
 */
function makeLocationLinkedPerson(string $slug, array $fields = []): EntryInstance
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
        'clinical_locations' => [ClinicalProviderFixtures::publishedLocationId()],
        ...$fields,
    ]);
    $person->saveQuietly();

    return $person;
}

/**
 * Overwrite one person's indexed eligibility with a value only a refresh would replace.
 */
function plantEligibility(EntryInstance $person, bool $value): void
{
    $index = eligibilityIndex();
    $index->put($person->id(), $value);
    $index->cache();
}

it('refreshes a person who links the saved location', function (): void {
    $kirk = makeLocationLinkedPerson('james-kirk');
    plantEligibility($kirk, false);

    /** @var EntryInstance $location */
    $location = Entry::find(ClinicalProviderFixtures::publishedLocationId());
    $location->save();

    expect(eligibilityIndex()->get($kirk->id()))->toBeTrue();
});

it('refreshes a person whose stored location is a single ID rather than a list', function (): void {
    $spock = makeLocationLinkedPerson('spock', ['clinical_locations' => ClinicalProviderFixtures::publishedLocationId()]);
    plantEligibility($spock, false);

    /** @var EntryInstance $location */
    $location = Entry::find(ClinicalProviderFixtures::publishedLocationId());
    $location->save();

    expect(eligibilityIndex()->get($spock->id()))->toBeTrue();
});

it('leaves a person who does not link the saved location alone', function (): void {
    /** @var EntryInstance $other */
    $other = Entry::make();
    $other->collection('clinical_locations');
    $other->slug('other-location');
    $other->published(true);
    $other->data(['title' => 'Other']);
    $other->saveQuietly();

    $scotty = makeLocationLinkedPerson('montgomery-scott');
    plantEligibility($scotty, false);

    $other->save();

    expect(eligibilityIndex()->get($scotty->id()))->toBeFalse();
});

it('ignores an entry outside the clinical_locations collection, even one a person links', function (): void {
    /** @var EntryInstance $page */
    $page = Entry::make();
    $page->collection('pages');
    $page->slug('sickbay');
    $page->published(true);
    $page->data(['title' => 'Sickbay']);
    $page->saveQuietly();

    $mccoy = makeLocationLinkedPerson('leonard-mccoy', [
        'clinical_locations' => [ClinicalProviderFixtures::publishedLocationId(), $page->id()],
    ]);
    plantEligibility($mccoy, false);

    $page->save();

    expect(eligibilityIndex()->get($mccoy->id()))->toBeFalse();
});

it('refreshes a person who links a deleted location', function (): void {
    $uhura = makeLocationLinkedPerson('nyota-uhura');
    plantEligibility($uhura, true);

    /** @var EntryInstance $location */
    $location = Entry::find(ClinicalProviderFixtures::publishedLocationId());
    $location->delete();

    expect(eligibilityIndex()->get($uhura->id()))->toBeFalse();
});
