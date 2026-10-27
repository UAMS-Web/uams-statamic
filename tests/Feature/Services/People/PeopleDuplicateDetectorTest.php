<?php

declare(strict_types=1);

use App\Services\People\DuplicatePersonCluster;
use App\Services\People\PeopleDuplicateDetector;
use Illuminate\Support\Collection;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Detection coverage for {@see PeopleDuplicateDetector} (UAMS-Web/uams-statamic-archive#839): multi-signal
 * clustering, confidence ranking, transitive grouping, and the guarantee that
 * detection only suggests — it never mutates entries.
 *
 * @command  php artisan test --compact tests/Feature/Services/People/PeopleDuplicateDetectorTest.php
 */
uses(StacheIsolatedTestCase::class);

/**
 * Build a person_name structure from a first and last name.
 *
 * @return array<string, mixed>
 */
function dupName(string $first, string $last): array
{
    return [
        'first_name' => ['text' => $first],
        'last_name' => ['text' => $last],
    ];
}

/**
 * Persist a person entry with arbitrary data, bypassing save listeners.
 *
 * @param  array<string, mixed>  $data
 */
function dupMakePerson(string $id, string $slug, array $data): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->collection(PeopleDuplicateDetector::peopleCollection());
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->id($id);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->slug($slug);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->published(true);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->data($data);
    $entry->saveQuietly();

    /** @var Statamic\Entries\Entry $found */
    $found = Entry::find($id);

    return $found;
}

/**
 * The cluster (if any) that contains the given entry id.
 *
 * @param  list<DuplicatePersonCluster>  $clusters
 */
function dupClusterWith(array $clusters, string $id): ?DuplicatePersonCluster
{
    foreach ($clusters as $cluster) {
        if (\in_array($id, $cluster->entryIds, true)) {
            return $cluster;
        }
    }

    return null;
}

beforeEach(function (): void {
    /** @var Collection<array-key, Statamic\Sites\Site> $sites */
    $sites = Site::all();
    $siteHandles = $sites->map(fn (Statamic\Sites\Site $site) => $site->handle())->values()->all();

    /** @var Statamic\Entries\Collection $collection */
    $collection = CollectionFacade::make(PeopleDuplicateDetector::peopleCollection());
    /** @var Statamic\Entries\Collection $collection */
    $collection = $collection->title('People');
    /** @var Statamic\Entries\Collection $collection */
    $collection = $collection->sites($siteHandles);
    /** @var Statamic\Entries\Collection $collection */
    $collection = $collection->routes('/people/{slug}');
    $collection->save();
});

it('flags a shared NPI as a high-confidence cluster', function (): void {
    dupMakePerson('a', 'jane-smith', ['person_name' => dupName('Jane', 'Smith'), 'npi' => '1234567890']);
    dupMakePerson('b', 'jane-s', ['person_name' => dupName('Janet', 'Smithe'), 'npi' => '1234567890']);
    dupMakePerson('c', 'bob-jones', ['person_name' => dupName('Bob', 'Jones'), 'npi' => '9999999999']);

    $clusters = (new PeopleDuplicateDetector)->detect();

    $cluster = dupClusterWith($clusters, 'a');
    /** @var DuplicatePersonCluster $cluster */
    expect($cluster)->not->toBeNull()
        ->and($cluster->entryIds)->toEqualCanonicalizing(['a', 'b'])
        ->and($cluster->confidence)->toBe(DuplicatePersonCluster::CONFIDENCE_HIGH)
        ->and($cluster->matchedSignals)->toContain('identifier')
        // Bob (a unique NPI) is not clustered.
        ->and(dupClusterWith($clusters, 'c'))->toBeNull();
});

it('flags a shared email as high confidence', function (): void {
    dupMakePerson('a', 'j-smith', ['person_name' => dupName('Jane', 'Smith'), 'email' => 'JANE@uams.edu']);
    dupMakePerson('b', 'jane-2', ['person_name' => dupName('Jane', 'S'), 'email' => 'jane@uams.edu']);

    $cluster = dupClusterWith((new PeopleDuplicateDetector)->detect(), 'a');
    /** @var DuplicatePersonCluster $cluster */
    expect($cluster)->not->toBeNull()
        ->and($cluster->confidence)->toBe(DuplicatePersonCluster::CONFIDENCE_HIGH)
        ->and($cluster->matchedSignals)->toContain('email');
});

it('flags a shared name plus phone as high confidence', function (): void {
    dupMakePerson('a', 'jane-smith', ['person_name' => dupName('Jane', 'Smith'), 'telephone' => '(501) 555-1212']);
    dupMakePerson('b', 'jane-smith-2', ['person_name' => dupName('Jane', 'Smith'), 'telephone_mobile' => '501-555-1212']);

    $cluster = dupClusterWith((new PeopleDuplicateDetector)->detect(), 'a');
    /** @var DuplicatePersonCluster $cluster */
    expect($cluster)->not->toBeNull()
        ->and($cluster->confidence)->toBe(DuplicatePersonCluster::CONFIDENCE_HIGH)
        ->and($cluster->matchedSignals)->toContain('name')
        ->and($cluster->matchedSignals)->toContain('phone');
});

it('flags a shared name plus slug stem as medium confidence', function (): void {
    // Same name and same slug stem (the reused/de-duplicated slug), no stronger signal.
    dupMakePerson('a', 'jane-smith', ['person_name' => dupName('Jane', 'Smith')]);
    dupMakePerson('b', 'jane-smith-2', ['person_name' => dupName('Jane', 'Smith')]);

    $cluster = dupClusterWith((new PeopleDuplicateDetector)->detect(), 'a');
    /** @var DuplicatePersonCluster $cluster */
    expect($cluster)->not->toBeNull()
        ->and($cluster->confidence)->toBe(DuplicatePersonCluster::CONFIDENCE_MEDIUM)
        ->and($cluster->matchedSignals)->toContain('name')
        ->and($cluster->matchedSignals)->toContain('slug');
});

it('flags a shared name alone as low confidence', function (): void {
    // Same name, but unrelated slug stems and no other signal.
    dupMakePerson('a', 'jane-smith', ['person_name' => dupName('Jane', 'Smith')]);
    dupMakePerson('b', 'js-cardiology', ['person_name' => dupName('Jane', 'Smith')]);

    $cluster = dupClusterWith((new PeopleDuplicateDetector)->detect(), 'a');
    /** @var DuplicatePersonCluster $cluster */
    expect($cluster)->not->toBeNull()
        ->and($cluster->confidence)->toBe(DuplicatePersonCluster::CONFIDENCE_LOW)
        ->and($cluster->matchedSignals)->toBe(['name']);
});

it('does not cluster clearly distinct people', function (): void {
    dupMakePerson('a', 'jane-smith', ['person_name' => dupName('Jane', 'Smith'), 'npi' => '1111111111', 'email' => 'jane@x.test']);
    dupMakePerson('b', 'bob-jones', ['person_name' => dupName('Bob', 'Jones'), 'npi' => '2222222222', 'email' => 'bob@x.test']);

    expect((new PeopleDuplicateDetector)->detect())->toBe([]);
});

it('does not cluster entries that merely share missing signals', function (): void {
    // Neither has a name, NPI, email, phone, or comparable slug → no false cluster.
    dupMakePerson('a', 'person-one', ['bio' => 'A']);
    dupMakePerson('b', 'person-two', ['bio' => 'B']);

    expect((new PeopleDuplicateDetector)->detect())->toBe([]);
});

it('clusters transitively across different shared signals', function (): void {
    // a~b by NPI, b~c by email → a, b, c form one cluster.
    dupMakePerson('a', 'jane', ['person_name' => dupName('Jane', 'Smith'), 'npi' => '1234567890']);
    dupMakePerson('b', 'jane-2', ['person_name' => dupName('Jane', 'Smith'), 'npi' => '1234567890', 'email' => 'jane@uams.edu']);
    dupMakePerson('c', 'jane-3', ['person_name' => dupName('Jane', 'Smith'), 'email' => 'jane@uams.edu']);

    $cluster = dupClusterWith((new PeopleDuplicateDetector)->detect(), 'a');
    /** @var DuplicatePersonCluster $cluster */
    expect($cluster)->not->toBeNull()
        ->and($cluster->entryIds)->toEqualCanonicalizing(['a', 'b', 'c'])
        ->and($cluster->confidence)->toBe(DuplicatePersonCluster::CONFIDENCE_HIGH);
});

it('matches on a nested Workday identifier', function (): void {
    dupMakePerson('a', 'jane', ['person_name' => dupName('Jane', 'Smith'), 'uams_workday_id' => ['workday_employee_id' => 'E12345']]);
    dupMakePerson('b', 'jane-q', ['person_name' => dupName('Janet', 'Smithe'), 'uams_workday_id' => ['workday_employee_id' => 'E12345']]);

    $cluster = dupClusterWith((new PeopleDuplicateDetector)->detect(), 'a');
    /** @var DuplicatePersonCluster $cluster */
    expect($cluster)->not->toBeNull()
        ->and($cluster->confidence)->toBe(DuplicatePersonCluster::CONFIDENCE_HIGH)
        ->and($cluster->matchedSignals)->toContain('identifier');
});

it('ranks clusters strongest-confidence first regardless of input order', function (): void {
    // Insert in REVERSE confidence order (low, then medium, then high) so the output
    // order can only be correct if the ranking sort actually runs — a stable sort that
    // skipped ranking, or a flattened rank scale, would leave them in insertion order.
    // All three clusters are size 2 so the size tie-break never masks the confidence rank.

    // Low: shared name only (unrelated slug stems).
    dupMakePerson('l1', 'zed-zhang', ['person_name' => dupName('Zed', 'Zhang')]);
    dupMakePerson('l2', 'zz-clinic', ['person_name' => dupName('Zed', 'Zhang')]);
    // Medium: shared name + slug stem.
    dupMakePerson('m1', 'amy-adams', ['person_name' => dupName('Amy', 'Adams')]);
    dupMakePerson('m2', 'amy-adams-2', ['person_name' => dupName('Amy', 'Adams')]);
    // High: shared NPI (different names, so identifier is the only signal).
    dupMakePerson('h1', 'bob-brown', ['person_name' => dupName('Bob', 'Brown'), 'npi' => '5555555555']);
    dupMakePerson('h2', 'bobby-browne', ['person_name' => dupName('Bobby', 'Browne'), 'npi' => '5555555555']);

    $clusters = (new PeopleDuplicateDetector)->detect();

    expect($clusters)->toHaveCount(3)
        ->and($clusters[0]->confidence)->toBe(DuplicatePersonCluster::CONFIDENCE_HIGH)
        ->and($clusters[1]->confidence)->toBe(DuplicatePersonCluster::CONFIDENCE_MEDIUM)
        ->and($clusters[2]->confidence)->toBe(DuplicatePersonCluster::CONFIDENCE_LOW);
});

it('ranks a larger cluster before a smaller one of equal confidence', function (): void {
    // Two high-confidence clusters: one of three entries, one of two. The larger must
    // sort first (the size tie-break after equal confidence).
    dupMakePerson('big1', 'big-a', ['person_name' => dupName('Gail', 'Green'), 'npi' => '6000000001']);
    dupMakePerson('big2', 'big-b', ['person_name' => dupName('Gaile', 'Greene'), 'npi' => '6000000001']);
    dupMakePerson('big3', 'big-c', ['person_name' => dupName('Gayle', 'Grene'), 'npi' => '6000000001']);
    dupMakePerson('sm1', 'sm-a', ['person_name' => dupName('Hank', 'Hill'), 'npi' => '7000000002']);
    dupMakePerson('sm2', 'sm-b', ['person_name' => dupName('Henry', 'Hille'), 'npi' => '7000000002']);

    $clusters = (new PeopleDuplicateDetector)->detect();

    expect($clusters)->toHaveCount(2)
        ->and($clusters[0]->entryIds)->toHaveCount(3)
        ->and($clusters[1]->entryIds)->toHaveCount(2);
});

it('does not cluster the same value held under different identifier kinds', function (): void {
    // The same digits as an NPI on one person and an Epic SER id on another are NOT
    // the same identity — the detector namespaces identifiers by kind. No other signal
    // is shared, so they must not cluster.
    dupMakePerson('a', 'anna-avery', ['person_name' => dupName('Anna', 'Avery'), 'npi' => '123456']);
    dupMakePerson('b', 'boris-bell', ['person_name' => dupName('Boris', 'Bell'), 'epic_ser_id' => '123456']);

    expect((new PeopleDuplicateDetector)->detect())->toBe([]);
});

it('only scans the people collection', function (): void {
    // An entry in another collection that shares an NPI with a person must be ignored —
    // detection is scoped to `people`, so the cross-collection match never clusters.
    /** @var Statamic\Entries\Collection $clinics */
    $clinics = CollectionFacade::make('clinics');
    /** @var Statamic\Entries\Collection $clinics */
    $clinics = $clinics->title('Clinics');
    $clinics->save();

    dupMakePerson('p', 'jane-smith', ['person_name' => dupName('Jane', 'Smith'), 'npi' => '1234567890']);

    /** @var Statamic\Entries\Entry $clinicEntry */
    $clinicEntry = Entry::make();
    /** @var Statamic\Entries\Entry $clinicEntry */
    $clinicEntry = $clinicEntry->collection('clinics');
    /** @var Statamic\Entries\Entry $clinicEntry */
    $clinicEntry = $clinicEntry->id('not-a-person');
    /** @var Statamic\Entries\Entry $clinicEntry */
    $clinicEntry = $clinicEntry->slug('a-clinic');
    /** @var Statamic\Entries\Entry $clinicEntry */
    $clinicEntry = $clinicEntry->data(['npi' => '1234567890']);
    $clinicEntry->saveQuietly();

    // Only one person carries that NPI, so there is no people-cluster at all.
    expect((new PeopleDuplicateDetector)->detect())->toBe([]);
});

it('keeps scanning for clusters after a non-duplicate singleton', function (): void {
    // 'solo' is unique (its own singleton group, iterated first). The real duplicate pair
    // that follows it must still be detected — the scan must not stop at the singleton.
    dupMakePerson('solo', 'solo', ['person_name' => dupName('Solo', 'Singleton'), 'npi' => '1000000001']);
    dupMakePerson('b', 'dup-one', ['person_name' => dupName('Dup', 'Pair'), 'npi' => '2000000002']);
    dupMakePerson('c', 'dup-two', ['person_name' => dupName('Dup', 'Pair'), 'npi' => '2000000002']);

    $clusters = (new PeopleDuplicateDetector)->detect();

    $cluster = dupClusterWith($clusters, 'b');
    /** @var DuplicatePersonCluster $cluster */
    expect($cluster)->not->toBeNull()
        ->and($cluster->entryIds)->toEqualCanonicalizing(['b', 'c'])
        ->and(dupClusterWith($clusters, 'solo'))->toBeNull();
});

it('matches on a seven-digit phone but not a shorter number', function (): void {
    // Seven digits is the documented minimum. Two people sharing a 7-digit number cluster
    // (medium, phone alone); a six-digit number is too short to match.
    dupMakePerson('a', 'cara-cole', ['person_name' => dupName('Cara', 'Cole'), 'telephone' => '555-1212']);
    dupMakePerson('b', 'drew-dole', ['person_name' => dupName('Drew', 'Dole'), 'telephone' => '555-1212']);
    // A third pair sharing only a 6-digit number must NOT cluster.
    dupMakePerson('c', 'eve-evans', ['person_name' => dupName('Eve', 'Evans'), 'telephone' => '55-1212']);
    dupMakePerson('d', 'finn-frye', ['person_name' => dupName('Finn', 'Frye'), 'telephone' => '55-1212']);

    $clusters = (new PeopleDuplicateDetector)->detect();
    $phoneCluster = dupClusterWith($clusters, 'a');
    /** @var DuplicatePersonCluster $phoneCluster */
    expect($phoneCluster)->not->toBeNull()
        ->and($phoneCluster->entryIds)->toEqualCanonicalizing(['a', 'b'])
        ->and($phoneCluster->matchedSignals)->toContain('phone')
        ->and($phoneCluster->confidence)->toBe(DuplicatePersonCluster::CONFIDENCE_MEDIUM)
        // The six-digit pair is below the threshold and never clusters.
        ->and(dupClusterWith($clusters, 'c'))->toBeNull();
});

it('does not cluster people who share only a first name', function (): void {
    // Same first name, different last name, unrelated slugs — name matching must use the
    // full name, so these are distinct people.
    dupMakePerson('a', 'jane-smith', ['person_name' => dupName('Jane', 'Smith')]);
    dupMakePerson('b', 'jane-doe', ['person_name' => dupName('Jane', 'Doe')]);

    expect((new PeopleDuplicateDetector)->detect())->toBe([]);
});

it('does not cluster people who share only a last name', function (): void {
    // Same last name, different first name, unrelated slugs — matching on the last name
    // alone would flag every pair of common surnames as duplicates, so it must not.
    dupMakePerson('a', 'jane-smith', ['person_name' => dupName('Jane', 'Smith')]);
    dupMakePerson('b', 'bob-smith', ['person_name' => dupName('Bob', 'Smith')]);

    expect((new PeopleDuplicateDetector)->detect())->toBe([]);
});

it('matches duplicate names written in a non-Latin script', function (): void {
    // Two records for the same person whose name is written in CJK, sharing no other
    // signal and with unrelated slug stems. An ASCII-only normalizer would drop every
    // character and miss the duplicate; the detector must still cluster them by name.
    dupMakePerson('a', 'profile-one', ['person_name' => dupName('李', '伟')]);
    dupMakePerson('b', 'profile-two', ['person_name' => dupName('李', '伟')]);

    $cluster = dupClusterWith((new PeopleDuplicateDetector)->detect(), 'a');
    /** @var DuplicatePersonCluster $cluster */
    expect($cluster)->not->toBeNull()
        ->and($cluster->entryIds)->toEqualCanonicalizing(['a', 'b'])
        ->and($cluster->matchedSignals)->toContain('name')
        ->and($cluster->confidence)->toBe(DuplicatePersonCluster::CONFIDENCE_LOW);
});

it('never mutates entries while detecting', function (): void {
    dupMakePerson('a', 'jane-smith', ['person_name' => dupName('Jane', 'Smith'), 'npi' => '1234567890', 'bio' => 'Original']);
    dupMakePerson('b', 'jane-smith-2', ['person_name' => dupName('Jane', 'Smith'), 'npi' => '1234567890', 'bio' => 'Second']);

    (new PeopleDuplicateDetector)->detect();

    // Both entries still exist with their original data — detection only suggests.
    /** @var Statamic\Entries\Entry $foundA */
    $foundA = Entry::find('a');
    /** @var string $bioA */
    $bioA = $foundA->get('bio');
    /** @var Statamic\Entries\Entry $foundB */
    $foundB = Entry::find('b');
    /** @var string $bioB */
    $bioB = $foundB->get('bio');

    expect(Entry::find('a'))->not->toBeNull()
        ->and($bioA)->toBe('Original')
        ->and(Entry::find('b'))->not->toBeNull()
        ->and($bioB)->toBe('Second');
});
