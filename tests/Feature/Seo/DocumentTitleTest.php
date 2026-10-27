<?php

declare(strict_types=1);

use App\Services\Seo\DocumentTitle;
use Illuminate\Support\Facades\File;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Coverage for App\Services\Seo\DocumentTitle — the SEO `<title>` formula for
 * `people` provider profiles and `clinical_locations` (UAMS-Web/uams-statamic-archive#1329) and the
 * proportionate cross-entry uniqueness that keeps those titles distinct (UAMS-Web/uams-statamic-archive#1330).
 *
 * Fixtures persist real people, location and specialization entries into an
 * isolated Stache (StacheIsolatedTestCase), so the specialty (Entry::find), the
 * related location lookup, and the same-title collision scan all run against the
 * real query layer rather than mocks. Entries are written with `saveQuietly()` so
 * no global EntrySaved listener rewrites the titles under the assertions.
 *
 * @command  php artisan test --compact tests/Feature/Seo/DocumentTitleTest.php
 */
uses(StacheIsolatedTestCase::class);

beforeEach(function (): void {
    $people = CollectionFacade::make('people');
    $people->title('People');
    $people->save();

    $locations = CollectionFacade::make('clinical_locations');
    $locations->title('Clinical Locations');
    $locations->save();

    // A structured collection on `default` since UAMS-Web/uams-statamic-archive#2596; people store its entry ids.
    $specializations = CollectionFacade::make('clinical_specializations');
    $specializations->title('Clinical Specializations');
    $specializations->sites(['default']);
    $specializations->structureContents(['root' => false]);
    $specializations->save();
});

/**
 * Persist a `clinical_specializations` entry with the given patient-title data.
 *
 * Its id is `spec-<slug>`, deliberately not its slug, so a lookup by slug instead of
 * by the stored id resolves nothing and fails the test.
 *
 * @param  array<string, mixed>  $data
 */
function documentTitleMakeSpecialization(string $slug, array $data): void
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->id('spec-'.$slug);
    $entry->collection('clinical_specializations');
    $entry->locale('default');
    $entry->slug($slug);
    $entry->published(true);
    $entry->data($data);
    $entry->saveQuietly();
}

/**
 * Persist a clinical_locations entry and return its generated id.
 *
 * @param  array<string, mixed>  $data
 */
function documentTitleMakeLocation(string $slug, array $data): string
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('clinical_locations');
    $entry->slug($slug);
    $entry->published(true);
    $entry->data($data);

    $entry->saveQuietly();

    /** @var string $id */
    $id = $entry->id();

    return (string) $id;
}

/**
 * Persist a people entry, bypassing EntrySaved listeners so its stored title
 * stays exactly $title.
 *
 * @param  array<string, mixed>  $data
 */
function documentTitleMakePerson(string $slug, array $data): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('people');
    $entry->slug($slug);
    $entry->published(true);
    $entry->data($data);

    $entry->saveQuietly();

    return $entry;
}

function documentTitle(): DocumentTitle
{
    return app(DocumentTitle::class);
}

it('reads the computed document_title in the layout head', function (): void {
    // The formula only reaches the browser because the layout head prefers the
    // computed title over the entry title and the site name. The resolved title is
    // strip+entity-encoded (Antlers is raw-by-default and `title` is editor input);
    // that is inert on the trusted `document_title` formula.
    $layout = File::get(resource_path('views/layout.antlers.html'));

    expect($layout)->toContain('( document_title ?? title ?? site:name ) | strip_tags | entities');
});

it('composes name, primary specialty, city/state, and brand for a full provider', function (): void {
    documentTitleMakeSpecialization('thoracic', [
        'title' => 'Thoracic Surgeon',
        'title_specialization_patient' => 'Thoracic Surgery',
        'title_specialization_query' => false,
    ]);
    $locationId = documentTitleMakeLocation('lr-clinic', [
        'title' => 'Little Rock Clinic',
        'address' => ['city' => 'Little Rock', 'state_province_region' => 'Arkansas'],
    ]);
    $person = documentTitleMakePerson('jane-smith', [
        'title' => 'Jane Smith, MD',
        'clinical_specializations' => ['spec-thoracic'],
        'clinical_locations' => [$locationId],
    ]);

    expect(documentTitle()->forPeople($person))
        ->toBe('Jane Smith, MD | Thoracic Surgery | Little Rock, AR | UAMS Health');
});

it('omits the specialty segment when the provider has none', function (): void {
    $locationId = documentTitleMakeLocation('lr-clinic', [
        'title' => 'Little Rock Clinic',
        'address' => ['city' => 'Little Rock', 'state_province_region' => 'Arkansas'],
    ]);
    $person = documentTitleMakePerson('jane-smith', [
        'title' => 'Jane Smith, MD',
        'clinical_locations' => [$locationId],
    ]);

    // No dangling separator where the specialty segment would have been.
    expect(documentTitle()->forPeople($person))
        ->toBe('Jane Smith, MD | Little Rock, AR | UAMS Health');
});

it('omits the city segment when the provider has no location', function (): void {
    documentTitleMakeSpecialization('thoracic', [
        'title' => 'Thoracic Surgeon',
        'title_specialization_patient' => 'Thoracic Surgery',
        'title_specialization_query' => false,
    ]);
    $person = documentTitleMakePerson('jane-smith', [
        'title' => 'Jane Smith, MD',
        'clinical_specializations' => ['spec-thoracic'],
    ]);

    expect(documentTitle()->forPeople($person))
        ->toBe('Jane Smith, MD | Thoracic Surgery | UAMS Health');
});

it('renders just name and brand when specialty and location are both absent', function (): void {
    $person = documentTitleMakePerson('jane-smith', [
        'title' => 'Jane Smith, MD',
    ]);

    expect(documentTitle()->forPeople($person))->toBe('Jane Smith, MD | UAMS Health');
});

it('uses the taxonomy code-set name when the specialization query toggle is on', function (): void {
    documentTitleMakeSpecialization('oncology', [
        'title' => 'Oncologist',
        'code_name' => 'Medical Oncology',
        'title_specialization_patient' => 'Cancer Care',
        'title_specialization_query' => true,
    ]);
    $person = documentTitleMakePerson('john-doe', [
        'title' => 'John Doe, MD',
        'clinical_specializations' => ['spec-oncology'],
    ]);

    // The toggle prefers code_name over the patient specialization title.
    expect(documentTitle()->forPeople($person))
        ->toBe('John Doe, MD | Medical Oncology | UAMS Health');
});

it('composes name, city/state, and brand for a location', function (): void {
    /** @var Statamic\Entries\Entry $location */
    $location = Entry::make();
    $location->collection('clinical_locations');
    $location->slug('lr-clinic');
    $location->published(true);
    $location->data([
        'title' => 'Little Rock Clinic',
        'address' => ['city' => 'Little Rock', 'state_province_region' => 'Arkansas'],
    ]);
    $location->saveQuietly();

    expect(documentTitle()->forClinicalLocation($location))
        ->toBe('Little Rock Clinic | Little Rock, AR | UAMS Health');
});

it('omits the city segment for a location without an address', function (): void {
    /** @var Statamic\Entries\Entry $location */
    $location = Entry::make();
    $location->collection('clinical_locations');
    $location->slug('lr-clinic');
    $location->published(true);
    $location->data(['title' => 'Little Rock Clinic']);
    $location->saveQuietly();

    expect(documentTitle()->forClinicalLocation($location))
        ->toBe('Little Rock Clinic | UAMS Health');
});

it('normalizes the location state to a USPS two-letter code', function (string $slug, array $address, string $expected): void {
    /** @var Statamic\Entries\Entry $location */
    $location = Entry::make();
    $location->collection('clinical_locations');
    $location->slug($slug);
    $location->published(true);
    $location->data(['title' => 'Clinic', 'address' => $address]);
    $location->saveQuietly();

    expect(documentTitle()->forClinicalLocation($location))->toBe('Clinic | '.$expected.' | UAMS Health');
})->with([
    // Real content stores the full state name under the canonical key.
    'canonical full name' => ['loc-ar', ['city' => 'Little Rock', 'state_province_region' => 'Arkansas'], 'Little Rock, AR'],
    // A pre-canonical preset key, already abbreviated, still resolves.
    'preset key + code' => ['loc-tx', ['city' => 'Dallas', 'state_province_region_american' => 'TX'], 'Dallas, TX'],
    // A lowercase code still resolves (the enum lookup upper-cases first).
    'lowercase code' => ['loc-lc', ['city' => 'Austin', 'state_province_region' => 'tx'], 'Austin, TX'],
    // A non-US region has no USPS code and passes through unchanged.
    'non-US region' => ['loc-on', ['city' => 'Toronto', 'state_province_region' => 'Ontario'], 'Toronto, Ontario'],
]);

it('disambiguates two providers whose composed titles would collide', function (): void {
    documentTitleMakeSpecialization('thoracic', [
        'title' => 'Thoracic Surgeon',
        'title_specialization_patient' => 'Thoracic Surgery',
        'title_specialization_query' => false,
    ]);
    $locationId = documentTitleMakeLocation('lr-clinic', [
        'title' => 'Little Rock Clinic',
        'address' => ['city' => 'Little Rock', 'state_province_region' => 'Arkansas'],
    ]);

    // Same name, same specialty, same location — the composed titles would be
    // identical without disambiguation.
    $data = [
        'title' => 'John Smith, MD',
        'clinical_specializations' => ['spec-thoracic'],
        'clinical_locations' => [$locationId],
    ];
    $a = documentTitleMakePerson('john-smith', $data);
    $b = documentTitleMakePerson('john-smith-2', $data);

    $titles = [documentTitle()->forPeople($a), documentTitle()->forPeople($b)];
    sort($titles);

    // One keeps the clean title; the other takes the " (2)" ordinal. Which entry
    // takes it is decided deterministically by id, so assert on the set.
    expect($titles)->toBe([
        'John Smith, MD | Thoracic Surgery | Little Rock, AR | UAMS Health',
        'John Smith, MD | Thoracic Surgery | Little Rock, AR | UAMS Health (2)',
    ]);
});

it('leaves same-named providers unchanged when their specialties differ', function (): void {
    documentTitleMakeSpecialization('thoracic', [
        'title' => 'Thoracic Surgeon',
        'title_specialization_patient' => 'Thoracic Surgery',
        'title_specialization_query' => false,
    ]);
    documentTitleMakeSpecialization('oncology', [
        'title' => 'Oncologist',
        'title_specialization_patient' => 'Oncology',
        'title_specialization_query' => false,
    ]);

    $a = documentTitleMakePerson('john-smith', [
        'title' => 'John Smith, MD',
        'clinical_specializations' => ['spec-thoracic'],
    ]);
    $b = documentTitleMakePerson('john-smith-2', [
        'title' => 'John Smith, MD',
        'clinical_specializations' => ['spec-oncology'],
    ]);

    $titleA = documentTitle()->forPeople($a);
    $titleB = documentTitle()->forPeople($b);

    // Their composed titles already differ (by specialty), so no " (n)" is added.
    expect($titleA)->toBe('John Smith, MD | Thoracic Surgery | UAMS Health')
        ->and($titleB)->toBe('John Smith, MD | Oncology | UAMS Health')
        ->and($titleA)->not->toContain('(2)')
        ->and($titleB)->not->toContain('(2)');
});

it('resolves the registered document_title computed through augmentation', function (): void {
    $person = documentTitleMakePerson('jane-smith', ['title' => 'Jane Smith, MD']);

    // Proves ComputedPropertiesProvider wired the computed for `people`, not just
    // that the service works when called directly.
    expect($person->value('document_title'))->toBe('Jane Smith, MD | UAMS Health');
});

it('trims the name and drops a non-scalar title', function (): void {
    $spacey = documentTitleMakePerson('spacey', ['title' => '  Jane Smith, MD  ']);
    expect(documentTitle()->forPeople($spacey))->toBe('Jane Smith, MD | UAMS Health');

    $weird = documentTitleMakePerson('weird', ['title' => ['not', 'a', 'string']]);
    expect(documentTitle()->forPeople($weird))->toBe('UAMS Health');
});

it('drops the specialty segment for a left-over term reference, now that the field stores entry ids (#2596)', function (): void {
    documentTitleMakeSpecialization('thoracic', [
        'title_specialization_patient' => 'Thoracic Surgery',
        'title_specialization_query' => false,
    ]);
    $person = documentTitleMakePerson('ref', [
        'title' => 'Jane Smith, MD',
        'clinical_specializations' => ['clinical_specializations::thoracic'],
    ]);

    expect(documentTitle()->forPeople($person))->toBe('Jane Smith, MD | UAMS Health');
});

it('falls back to the occupation title, then the entry title, for the specialty', function (): void {
    documentTitleMakeSpecialization('occ-only', [
        'title' => 'Cardiologist',
        'title_occupation' => 'Heart Doctor',
        'title_specialization_query' => false,
    ]);
    $a = documentTitleMakePerson('occ', ['title' => 'A A, MD', 'clinical_specializations' => ['spec-occ-only']]);
    expect(documentTitle()->forPeople($a))->toBe('A A, MD | Heart Doctor | UAMS Health');

    documentTitleMakeSpecialization('title-only', [
        'title' => 'Neurology',
        'title_specialization_query' => false,
    ]);
    $b = documentTitleMakePerson('tit', ['title' => 'B B, MD', 'clinical_specializations' => ['spec-title-only']]);
    expect(documentTitle()->forPeople($b))->toBe('B B, MD | Neurology | UAMS Health');
});

it('uses the patient specialization title (not code_name) when the query toggle is off', function (): void {
    documentTitleMakeSpecialization('both', [
        'code_name' => 'Medical Oncology',
        'title_specialization_patient' => 'Cancer Care',
        'title_specialization_query' => false,
    ]);
    $person = documentTitleMakePerson('q-off', ['title' => 'C C, MD', 'clinical_specializations' => ['spec-both']]);

    expect(documentTitle()->forPeople($person))->toBe('C C, MD | Cancer Care | UAMS Health');
});

it('renders city without state, and state without city', function (): void {
    /** @var Statamic\Entries\Entry $cityOnly */
    $cityOnly = Entry::make();
    $cityOnly->collection('clinical_locations');
    $cityOnly->slug('city-only');
    $cityOnly->published(true);
    $cityOnly->data(['title' => 'Clinic A', 'address' => ['city' => 'Little Rock']]);
    $cityOnly->saveQuietly();
    expect(documentTitle()->forClinicalLocation($cityOnly))->toBe('Clinic A | Little Rock | UAMS Health');

    /** @var Statamic\Entries\Entry $stateOnly */
    $stateOnly = Entry::make();
    $stateOnly->collection('clinical_locations');
    $stateOnly->slug('state-only');
    $stateOnly->published(true);
    $stateOnly->data(['title' => 'Clinic B', 'address' => ['state_province_region' => 'Arkansas']]);
    $stateOnly->saveQuietly();
    expect(documentTitle()->forClinicalLocation($stateOnly))->toBe('Clinic B | AR | UAMS Health');
});

it('drops the city segment when the related location is missing or address-less', function (): void {
    // A related location with no address.
    $locId = documentTitleMakeLocation('no-addr', ['title' => 'Addressless']);
    $withAddresslessLocation = documentTitleMakePerson('no-city', [
        'title' => 'D D, MD',
        'clinical_locations' => [$locId],
    ]);
    expect(documentTitle()->forPeople($withAddresslessLocation))->toBe('D D, MD | UAMS Health');

    // A location reference that does not resolve to an entry.
    $withBadReference = documentTitleMakePerson('bad-loc', [
        'title' => 'E E, MD',
        'clinical_locations' => ['does-not-exist'],
    ]);
    expect(documentTitle()->forPeople($withBadReference))->toBe('E E, MD | UAMS Health');
});

it('tolerates non-array specialty and location fields', function (): void {
    $person = documentTitleMakePerson('scalar-fields', [
        'title' => 'F F, MD',
        'clinical_specializations' => 'oops',
        'clinical_locations' => 'oops',
    ]);

    expect(documentTitle()->forPeople($person))->toBe('F F, MD | UAMS Health');
});
