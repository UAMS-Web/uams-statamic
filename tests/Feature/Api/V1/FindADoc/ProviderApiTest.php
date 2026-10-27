<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Statamic\Entries\Entry as EntryInstance;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Tests\Support\ClinicalProviderFixtures;
use Tests\Support\StacheIsolatedTestCase;

use function Pest\Laravel\getJson;

/**
 * Coverage for the Find-a-Doc read API (UAMS-Web/uams-statamic-archive#1043): the versioned, read-only provider
 * endpoints that mirror the WordPress `provider_meta` payload for the "UAMS
 * Health" app.
 *
 * The two load-bearing guarantees are asserted here against real, isolated-Stache
 * `people` fixtures (the Star-Trek cast encodes the eligibility cases):
 *   1. Only eligible + searchable providers are exposed — an ineligible (resident)
 *      or a not-searchable (actively separated) person is absent from the index
 *      and 404s on show, so the API can't reach a provider the site would hide.
 *   2. The payload is an allow-list — PII set on the entry (`npi`, `email`,
 *      `telephone`, `epic_ser_id`, `uams_workday_id`, `gender`) never appears.
 *
 * @command php artisan test --compact tests/Feature/Api/V1/FindADoc/ProviderApiTest.php
 */
uses(StacheIsolatedTestCase::class);

beforeEach(function (): void {
    // NOT `Cache::flush()`. This suite is Stache-isolated, and a bare flush
    // empties the worker's SHARED store — seeded Stache index included. What
    // rebuilds in its place is starved rather than absent (one entry per
    // collection, cached as authoritative), so every later test in this process
    // ran against a crippled Stache while the band stayed green (UAMS-Web/uams-statamic-archive#1994).
    $this->flushIsolatedCache();
    $people = CollectionFacade::make('people');
    $people->title('People');
    $people->save();

    // A structured collection on `default` since UAMS-Web/uams-statamic-archive#2596; people store its entry ids.
    $specializations = CollectionFacade::make('clinical_specializations');
    $specializations->title('Clinical Specializations');
    $specializations->sites(['default']);
    $specializations->structureContents(['root' => false]);
    $specializations->save();
});

/**
 * Persist a `clinical_specializations` entry and return its id.
 */
function fadApiSpecialization(string $slug, string $title): string
{
    /** @var EntryInstance $entry */
    $entry = Entry::make();
    $entry->collection('clinical_specializations');
    $entry->locale('default');
    $entry->slug($slug);
    $entry->published(true);
    $entry->data(['title' => $title]);
    $entry->saveQuietly();

    return mustBeString($entry->id(), 'the specialization id');
}

/**
 * The minimum people field set where every clinical-provider eligibility base
 * rule passes (mirrors config/clinical_provider_profile.php `base`).
 *
 * @return array<string, mixed>
 */
function fadApiBaseFields(): array
{
    return [
        'uams_worker_query' => true,
        'uams_worker_subtype' => 'employee',
        'uams_employee_subtype' => 'regular',
        'resident_query' => false,
        'fellow_query' => false,
        'clinical_role_query' => true,
        'direct_patient_care_query' => true,
        'clinical_locations' => [ClinicalProviderFixtures::publishedLocationId()],
    ];
}

/**
 * Persist a people entry that is eligible by default (with a structured name);
 * overrides make it ineligible / not searchable or add payload/PII fields.
 *
 * @param  array<string, mixed>  $fields
 */
function fadApiPerson(string $slug, string $first, string $last, array $fields = []): EntryInstance
{
    /** @var EntryInstance $entry */
    $entry = Entry::make();
    $entry->collection('people');
    $entry->slug($slug);
    $entry->published(true);
    $entry->data([
        ...fadApiBaseFields(),
        'title' => $first.' '.$last,
        'credentials' => ['md'],
        'person_name' => [
            'first_name' => ['text' => $first, 'phonetic_text' => ''],
            'no_middle_name' => true,
            'middle_name' => ['text' => '', 'phonetic_text' => ''],
            'no_nickname' => true,
            'nickname' => ['text' => '', 'phonetic_text' => ''],
            'last_name' => ['text' => $last, 'phonetic_text' => ''],
            'no_generational_suffix' => true,
            'generational_suffix' => null,
            'display_format' => null,
        ],
        ...$fields,
    ]);
    $entry->saveQuietly();

    return $entry;
}

/**
 * Build the standard cast: an eligible provider carrying PII + clinical fields, an
 * ineligible resident, and an actively-separated (not searchable) provider.
 *
 * @return array{mccoy: EntryInstance, chekov: EntryInstance, sulu: EntryInstance}
 */
function fadApiCast(): array
{
    $mccoy = fadApiPerson('leonard-mccoy', 'Leonard', 'McCoy', [
        'clinical_specializations' => [fadApiSpecialization('cardiology', 'Cardiology')],
        'service_line' => ['heart'],
        'pronouns' => 'he-him',
        'accepting_patients_query' => true,
        'referral_required' => false,
        'portrait' => 'portraits/mccoy-3x4.jpg',
        'portrait_wide' => 'portraits/mccoy-16x9.jpg',
        // PII that must never leave the API:
        'npi' => '1234567890',
        'epic_ser_id' => 'EPIC-42',
        'uams_workday_id' => ['workday_universal_id' => 'WD-7'],
        'email' => 'mccoy@uams.edu',
        'telephone' => '501-555-0100',
        'gender' => 'Male',
    ]);

    $chekov = fadApiPerson('pavel-chekov', 'Pavel', 'Chekov', ['resident_query' => true]);

    $sulu = fadApiPerson('hikaru-sulu', 'Hikaru', 'Sulu', [
        'clinical_separated_query' => true,
        'separated_start_date' => Carbon::yesterday()->toDateString(),
        'separated_end_date' => Carbon::tomorrow()->toDateString(),
    ]);

    return ['mccoy' => $mccoy, 'chekov' => $chekov, 'sulu' => $sulu];
}

it('lists only eligible, searchable providers, versioned', function (): void {
    ['mccoy' => $mccoy, 'chekov' => $chekov, 'sulu' => $sulu] = fadApiCast();

    $response = getJson('/api/v1/find-a-doc/providers');

    $response->assertOk();
    $response->assertJsonPath('version', 'v1');

    /** @var array<int, array<string, mixed>> $providerData */
    $providerData = $response->json('data');
    $ids = collect($providerData)->pluck('id')->all();

    expect($ids)
        ->toContain($mccoy->id())
        ->and($ids)->not->toContain($chekov->id())   // ineligible (resident)
        ->and($ids)->not->toContain($sulu->id());    // eligible but not searchable (separated)
});

it('never exposes people PII in the provider payload', function (): void {
    fadApiCast();

    $response = getJson('/api/v1/find-a-doc/providers');
    $response->assertOk();

    // No PII value survives anywhere in the serialized body.
    $body = (string) $response->getContent();
    expect($body)
        ->not->toContain('1234567890')                  // npi
        ->and($body)->not->toContain('EPIC-42')         // epic_ser_id
        ->and($body)->not->toContain('WD-7')            // uams_workday_id
        ->and($body)->not->toContain('mccoy@uams.edu')  // email
        ->and($body)->not->toContain('501-555-0100');   // telephone

    // The payload is a strict allow-list — no PII keys, exactly the known keys.
    /** @var array<int, array<string, mixed>> $allowListData */
    $allowListData = $response->json('data');
    /** @var array<string, mixed> $mccoyPayload */
    $mccoyPayload = collect($allowListData)->firstWhere('name.full', 'Leonard McCoy');
    expect($mccoyPayload)->not->toBeNull();
    expect(array_keys($mccoyPayload))->toEqualCanonicalizing([
        'id', 'slug', 'url', 'name', 'credentials', 'clinical_title',
        'specialties', 'service_line', 'pronouns', 'appointments', 'is_resident', 'portrait',
    ]);
    expect($mccoyPayload)->not->toHaveKeys(['npi', 'epic_ser_id', 'uams_workday_id', 'email', 'telephone', 'gender']);
});

it('emits the provider display fields from the shared computed values', function (): void {
    fadApiCast();

    /** @var array<int, array<string, mixed>> $displayData */
    $displayData = getJson('/api/v1/find-a-doc/providers')->json('data');
    /** @var array{name: array<string, mixed>, credentials: mixed, specialties: mixed, appointments: array<string, mixed>, is_resident: mixed, portrait: array<string, mixed>} $mccoy */
    $mccoy = collect($displayData)->firstWhere('name.full', 'Leonard McCoy');

    expect($mccoy['name']['full'])->toBe('Leonard McCoy');
    expect($mccoy['name']['full_with_credentials'])->toContain('McCoy');
    expect($mccoy['credentials'])->toBe(['md']);
    expect($mccoy['specialties'])->toBe(['cardiology']);
    expect($mccoy['appointments']['accepting_new_patients'])->toBeTrue();
    expect($mccoy['appointments']['referral_required'])->toBeFalse();
    expect($mccoy['is_resident'])->toBeFalse();
    expect($mccoy['portrait']['headshot'])->toBe('portraits/mccoy-3x4.jpg');
});

it('keeps `specialties` as the specialization slugs, in stored order, now that the field stores entry ids (#2596)', function (): void {
    // The field stored term slugs before UAMS-Web/uams-statamic-archive#2596 and stores entry ids after it. The API has
    // always emitted the slugs, and the mobile app consumes them, so the payload maps each
    // id back to its entry's slug. An id that resolves to nothing in the collection (a
    // deleted entry, a left-over term slug) is dropped rather than leaked as an id.
    /** @var EntryInstance $other */
    $other = Entry::make();
    $other->collection('people');
    $other->slug('christine-chapel');
    $other->published(true);
    $other->data(['title' => 'Christine Chapel']);
    $other->saveQuietly();

    $scott = fadApiPerson('montgomery-scott', 'Montgomery', 'Scott', [
        'clinical_specializations' => [
            fadApiSpecialization('thoracic-surgeon', 'Thoracic Surgeon'),
            'cardiology',
            $other->id(),
            fadApiSpecialization('congenital-heart-surgeon', 'Congenital Heart Surgeon'),
        ],
    ]);

    // The show endpoint, which is not cached: the index's response is cached under one key
    // for the whole suite, so a provider no other test builds would read a stale listing.
    $response = getJson('/api/v1/find-a-doc/providers/'.mustBeString($scott->id(), 'the provider id'))->assertOk();

    expect($response->json('data.specialties'))->toBe(['thoracic-surgeon', 'congenital-heart-surgeon'])
        // Byte-level: the same JSON a slug-storing field produced for these two.
        ->and(mustBeString($response->getContent(), 'the response body'))->toContain('"specialties":["thoracic-surgeon","congenital-heart-surgeon"]');
});

it('serves the same provider payload, byte for byte, once the name is split into its own fields (#3186)', function (): void {
    // UAMS-Web/uams-statamic-archive#3186 moves the nickname, the pronunciation of each name part and the display
    // format out of `person_name` and into fields of their own. The mobile app reads
    // this payload, so the move must not change a single byte of it.
    $mccoy = fadApiPerson('leonard-mccoy', 'Leonard', 'McCoy', [
        'person_name' => [
            'first_name' => ['text' => 'Leonard', 'phonetic_text' => 'fu'],
            'no_middle_name' => false,
            'middle_name' => ['text' => 'Horatio', 'phonetic_text' => 'bɑr'],
            'no_nickname' => false,
            'nickname' => ['text' => 'Bones', 'phonetic_text' => 'kʌks'],
            'last_name' => ['text' => 'McCoy', 'phonetic_text' => 'bæz'],
            'no_generational_suffix' => true,
            'generational_suffix' => null,
            'display_format' => 'nickname_only@ns',
        ],
    ]);
    $url = '/api/v1/find-a-doc/providers/'.mustBeString($mccoy->id(), 'the provider id');

    $before = mustBeString(getJson($url)->assertOk()->getContent(), 'the response body');

    pendingArtisan('content:split-person-names')->assertSuccessful();

    $split = Entry::find($mccoy->id());
    expect($split)->toBeInstanceOf(EntryInstance::class);
    /** @var EntryInstance $split */
    expect($split->get('nickname'))->toBe(['text' => 'Bones', 'phonetic_text' => 'kʌks'])
        ->and($split->get('name_display_format'))->toBe('nickname_only@ns');

    expect(getJson($url)->assertOk()->getContent())->toBe($before)
        ->and($before)->toContain('Bones');
});

it('serves a single eligible provider by id', function (): void {
    ['mccoy' => $mccoy] = fadApiCast();

    /** @var string $mccoyId */
    $mccoyId = $mccoy->id();
    getJson('/api/v1/find-a-doc/providers/'.$mccoyId)
        ->assertOk()
        ->assertJsonPath('version', 'v1')
        ->assertJsonPath('data.id', $mccoyId)
        ->assertJsonPath('data.name.full', 'Leonard McCoy');
});

it('404s show for an ineligible, a not-searchable, and an unknown provider', function (): void {
    ['chekov' => $chekov, 'sulu' => $sulu] = fadApiCast();

    /** @var string $chekovId */
    $chekovId = $chekov->id();
    /** @var string $suluId */
    $suluId = $sulu->id();
    getJson('/api/v1/find-a-doc/providers/'.$chekovId)->assertNotFound(); // resident
    getJson('/api/v1/find-a-doc/providers/'.$suluId)->assertNotFound();   // separated
    getJson('/api/v1/find-a-doc/providers/does-not-exist')->assertNotFound();
});

it('exposes the provider endpoints as read-only (GET only)', function (): void {
    $providerRoutes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_contains($route->uri(), 'find-a-doc/providers'));

    expect($providerRoutes)->not->toBeEmpty();

    $providerRoutes->each(function ($route): void {
        $methods = $route->methods();
        expect($methods)
            ->not->toContain('POST')
            ->and($methods)->not->toContain('PUT')
            ->and($methods)->not->toContain('PATCH')
            ->and($methods)->not->toContain('DELETE');
    });
});
