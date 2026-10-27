<?php

declare(strict_types=1);

use App\Services\ComputedFields\ProviderPeople\AppointmentContextResolver;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Globals\Variables;
use Statamic\Taxonomies\LocalizedTerm;
use Tests\Support\StacheIsolatedTestCase;

/**
 * The facts behind the clinical provider lens's appointment CTA and Primary
 * Appointment Location block (UAMS-Web/uams-statamic-archive#1676): eligibility, which locations count, the
 * portal the primary one carries, and the appointment line that portal implies.
 *
 * Every case runs against real persisted entries, terms, and a real global, because
 * the resolution IS the relation walk — a mocked repository would assert the mock.
 *
 * @command php artisan test --compact tests/Feature/ComputedFields/AppointmentContextResolverTest.php
 */
uses(StacheIsolatedTestCase::class);

beforeEach(function (): void {
    $people = CollectionFacade::make('people');
    $people->title('People');
    $people->save();

    // Routed and dated, mirroring `content/collections/clinical_locations.yaml`:
    // the route so `direct_url` and `primary_location_url` can be asserted as real
    // URLs, and `future: private` so a scheduled location behaves as it does in
    // production rather than as a plain published one.
    $locations = CollectionFacade::make('clinical_locations');
    $locations->title('Clinical Locations');
    $locations->routes('/health/locations/{slug}');
    $locations->dated(true);
    $locations->futureDateBehavior('private');
    $locations->save();

    /** @var Statamic\Taxonomies\Taxonomy $taxonomy */
    $taxonomy = Taxonomy::make('portals');
    $taxonomy->title('Patient Portals');
    $taxonomy->save();

    appointmentSettings();
});

afterEach(function (): void {
    // Reset the current site; one case renders as `health` to prove the read is
    // origin-based, not current-site-based.
    Site::setCurrent('default');
});

/**
 * Persist the Find-a-Doc settings global with the given field values.
 *
 * @param  array<string, mixed>  $data
 */
function appointmentSettings(array $data = []): void
{
    /** @var Statamic\Globals\GlobalSet $set */
    $set = GlobalSet::make('uams_fad_settings');
    $set->title('Find-a-Doc Settings');
    $set->save();

    /** @var Statamic\Sites\Site $defaultSite */
    $defaultSite = Site::default();

    /** @var Variables $vars */
    $vars = $set->makeLocalization($defaultSite->handle());
    $vars->data($data + [
        'provider_mainline' => true,
        'appointment_phone_default' => '501-686-8000',
        'appointment_phone_name_default' => 'the UAMS Health appointment line',
    ]);
    $vars->save();
}

/**
 * Persist one `portals` term.
 *
 * @param  array<string, mixed>  $data
 */
function appointmentPortal(string $slug, array $data): void
{
    /** @var Statamic\Taxonomies\Term $term */
    $term = Term::make($slug);
    $term->taxonomy('portals');

    // Term data lives on the localization, not the term itself — the fluent
    // `Term::data()` is a magic passthrough PHPStan cannot see.
    /** @var LocalizedTerm $localized */
    $localized = $term->inDefaultLocale();
    $localized->data($data);

    $term->saveQuietly();
}

/**
 * Persist one `clinical_locations` entry and return its id.
 *
 * @param  array<string, mixed>  $data
 */
function appointmentLocation(string $id, bool $published = true, array $data = [], ?string $date = null): string
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('clinical_locations');
    $entry->id($id);
    $entry->slug($id);
    $entry->published($published);
    $entry->data($data + ['title' => ucfirst($id)]);

    // The entry date is not a data key — a dated collection reads it from the
    // entry itself, and it is what makes a future-dated entry `scheduled`.
    $entry->date($date ?? '2020-01-01');

    $entry->saveQuietly();

    return $id;
}

/**
 * Resolve the appointment context for a provider with the given stored data.
 *
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
function resolveAppointmentContext(array $data): array
{
    /** @var Statamic\Entries\Entry $provider */
    $provider = Entry::make();
    $provider->collection('people');
    $provider->id('mccoy');
    $provider->slug('mccoy');
    $provider->published(true);
    $provider->data($data + ['title' => 'Leonard McCoy']);
    $provider->saveQuietly();

    return app(AppointmentContextResolver::class)->resolve($provider);
}

// --- Eligibility -------------------------------------------------------------

it('is eligible for a provider who takes individually-appointed patients', function (): void {
    $context = resolveAppointmentContext([
        'appointments_query' => true,
        'appointments_individual_query' => true,
    ]);

    expect($context['eligible'])->toBeTrue();
});

it('is not eligible when patients are appointed to a resource rather than the person', function (): void {
    $context = resolveAppointmentContext([
        'appointments_query' => true,
        'appointments_individual_query' => false,
    ]);

    expect($context['eligible'])->toBeFalse();
});

it('is never eligible for a resident, whatever the appointment answers say', function (): void {
    // WordPress forces the section off for residents before reading anything else.
    $context = resolveAppointmentContext([
        'resident_query' => true,
        'appointments_query' => true,
        'appointments_individual_query' => true,
    ]);

    expect($context['eligible'])->toBeFalse();
});

// --- Locations ---------------------------------------------------------------

it('counts only published locations, and takes the first published one as primary', function (): void {
    appointmentLocation('embargoed', published: false);
    appointmentLocation('sickbay');
    appointmentLocation('annex');

    // Entered draft-first: a resolver that took `[0]` blindly would pick the draft.
    $context = resolveAppointmentContext(['clinical_locations' => ['embargoed', 'sickbay', 'annex']]);

    expect($context['location_valid'])->toBeTrue()
        ->and($context['location_count'])->toBe(2)
        ->and($context['primary_location_id'])->toBe('sickbay');
});

it('reports no valid location when every related location is unpublished', function (): void {
    appointmentLocation('embargoed', published: false);

    $context = resolveAppointmentContext(['clinical_locations' => ['embargoed']]);

    expect($context['location_valid'])->toBeFalse()
        ->and($context['location_count'])->toBe(0)
        ->and($context['primary_location_id'])->toBeNull();
});

it('prefers the clean generic name over the disambiguated entry title', function (): void {
    appointmentLocation('sickbay', data: [
        'title' => 'Sickbay (Deck 7, Enterprise)',
        'generic_name' => 'Sickbay',
    ]);

    $context = resolveAppointmentContext(['clinical_locations' => ['sickbay']]);

    expect($context['primary_location_title'])->toBe('Sickbay');
});

it('targets the single location directly and the locations anchor when there are several', function (): void {
    appointmentLocation('sickbay');
    appointmentLocation('annex');

    $single = resolveAppointmentContext(['clinical_locations' => ['sickbay']]);
    $several = resolveAppointmentContext(['clinical_locations' => ['sickbay', 'annex']]);

    // The fixture collection is routed in `beforeEach`, so the single-location
    // case asserts a real URL rather than the empty string a routeless fixture
    // would produce — the acceptance criterion is that it targets the LOCATION,
    // which an assertion on `direct_is_anchor` alone would not establish.
    expect($single['direct_url'])->toBe('/health/locations/sickbay')
        ->and($single['direct_is_anchor'])->toBeFalse()
        ->and($single['primary_location_url'])->toBe('/health/locations/sickbay')
        ->and($several['direct_url'])->toBe('#locations')
        ->and($several['direct_is_anchor'])->toBeTrue();
});

// `clinical_locations` is a DATED collection with `date_behavior: {future: private}`,
// so a flagged-published entry dated tomorrow has status `scheduled` and is excluded
// from the augmented relation every template gate walks. Reading the raw `published()`
// flag here made the CTA name a clinic, and the primary-location block link to a URL,
// that the page's own Locations section never rendered.
it('excludes a scheduled location, matching what the templates actually render', function (): void {
    appointmentLocation('sickbay');
    appointmentLocation('annex', date: now()->addYear()->format('Y-m-d'));

    $context = resolveAppointmentContext(['clinical_locations' => ['annex', 'sickbay']]);

    expect($context['location_count'])->toBe(1)
        ->and($context['primary_location_id'])->toBe('sickbay')
        ->and($context['direct_is_anchor'])->toBeFalse();
});

// --- The portal --------------------------------------------------------------

it('resolves the portal from the primary location, not from the provider', function (): void {
    appointmentPortal('ach-mychart', [
        'title' => "Arkansas Children's MyChart",
        'official_website' => 'https://mychart.archildrens.org/',
    ]);
    appointmentLocation('sickbay', data: ['portals' => 'ach-mychart']);

    $context = resolveAppointmentContext(['clinical_locations' => ['sickbay']]);

    expect($context['show_portal'])->toBeTrue()
        ->and($context['portal_name'])->toBe("Arkansas Children's MyChart")
        ->and($context['portal_url'])->toBe('https://mychart.archildrens.org/');
});

it('does not offer a portal for the `_none` placeholder term', function (): void {
    appointmentPortal('_none', ['title' => 'None']);
    appointmentLocation('sickbay', data: ['portals' => '_none']);

    $context = resolveAppointmentContext(['clinical_locations' => ['sickbay']]);

    expect($context['show_portal'])->toBeFalse();
});

it('does not offer a portal when there is no published location to inherit one from', function (): void {
    // `show_portal` implying `location_valid` is what makes several combinations of
    // the CTA's branch table unreachable — so it is worth pinning.
    appointmentPortal('mychart', ['title' => 'MyChart', 'official_website' => 'https://mychart.example/']);
    appointmentLocation('embargoed', published: false, data: ['portals' => 'mychart']);

    $context = resolveAppointmentContext(['clinical_locations' => ['embargoed']]);

    expect($context['show_portal'])->toBeFalse()
        ->and($context['location_valid'])->toBeFalse();
});

// --- The appointment line ----------------------------------------------------

it('falls back to the system appointment line when no portal names one', function (): void {
    appointmentLocation('sickbay');

    $context = resolveAppointmentContext(['clinical_locations' => ['sickbay']]);

    expect($context['phone_tel'])->toBe('5016868000')
        ->and($context['phone_display'])->toBe('(501) 686-8000')
        ->and($context['phone_name'])->toBe('the UAMS Health appointment line');
});

it('takes the appointment line from the resolved portal when it carries one', function (): void {
    // The point of moving these onto the term: WordPress hard-codes a slug switch
    // (`ach-mychart` → Arkansas Children's), so a new portal needed a template edit.
    appointmentPortal('ach-mychart', [
        'title' => "Arkansas Children's MyChart",
        'official_website' => 'https://mychart.archildrens.org/',
        'appointment_phone' => '501-364-4000',
        'appointment_phone_name' => "the main Arkansas Children's Hospital appointment line",
    ]);
    appointmentLocation('sickbay', data: ['portals' => 'ach-mychart']);

    $context = resolveAppointmentContext(['clinical_locations' => ['sickbay']]);

    expect($context['phone_tel'])->toBe('5013644000')
        ->and($context['phone_display'])->toBe('(501) 364-4000')
        ->and($context['phone_name'])->toBe("the main Arkansas Children's Hospital appointment line");
});

// The `phone` fieldtype STORES E.164 whatever the editor typed, so a bare
// ten-digit format test never matched and the CTA printed `+15016868000` at a
// patient. The country code is stripped before formatting.
it('formats an E.164 number, which is what the phone fieldtype stores', function (): void {
    appointmentSettings(['appointment_phone_default' => '+15016868000']);
    appointmentLocation('sickbay');

    $context = resolveAppointmentContext(['clinical_locations' => ['sickbay']]);

    expect($context['phone_display'])->toBe('(501) 686-8000')
        ->and($context['phone_tel'])->toBe('15016868000');
});

// The number and its name render as ONE sentence — "by calling {name} at
// {number}" — so falling back independently would label Arkansas Children's
// number as the UAMS Health line, telling a patient to call one organization by
// another's name.
it('falls back to the system line as a pair when a portal supplies only half of one', function (): void {
    appointmentPortal('ach-mychart', [
        'title' => "Arkansas Children's MyChart",
        'appointment_phone' => '501-364-4000',
        // No `appointment_phone_name`.
    ]);
    appointmentLocation('sickbay', data: ['portals' => 'ach-mychart']);

    $context = resolveAppointmentContext(['clinical_locations' => ['sickbay']]);

    expect($context['phone_display'])->toBe('(501) 686-8000')
        ->and($context['phone_name'])->toBe('the UAMS Health appointment line');
});

it('returns an unparseable number as entered rather than mangling or hiding it', function (): void {
    appointmentSettings(['appointment_phone_default' => '1-800-UAMS-DOC']);
    appointmentLocation('sickbay');

    $context = resolveAppointmentContext(['clinical_locations' => ['sickbay']]);

    expect($context['phone_display'])->toBe('1-800-UAMS-DOC');
});

// --- The site-wide toggle ----------------------------------------------------

// The production wiring after UAMS-Web/uams-statamic-archive#1698: the Find-a-Doc global is rooted at the
// `default` site with `health` inheriting its non-localizable fields, and the
// resolver reads `inDefaultSite()`. The failure UAMS-Web/uams-statamic-archive#1694 shipped was the inverse —
// the global localized on `health` ONLY, so `inDefaultSite()` read null and
// every setting collapsed (an empty number, an empty spoken name, and
// `provider_mainline` stuck on because "unset" means "enabled"). Rendering as
// the `health` site here proves the read is origin-based, not current-site-based;
// an explicit `false` surviving is the sharpest proof the origin was read, since
// a null read looks exactly like "unset", which defaults ON.
it('resolves Find-a-Doc settings from the default origin the health site inherits', function (): void {
    /** @var Statamic\Globals\GlobalSet $set */
    $set = GlobalSet::make('uams_fad_settings');
    $set->title('Find-a-Doc Settings');
    $set->sites(['default' => null, 'health' => 'default']);
    $set->save();

    /** @var Variables $vars */
    $vars = $set->makeLocalization('default');
    $vars->data([
        'provider_mainline' => false,
        'appointment_phone_default' => '501-296-1200',
        'appointment_phone_name_default' => 'the UAMS Health appointment line',
    ]);
    $vars->save();

    appointmentLocation('sickbay');

    // Render as the `health` site — where Find-a-Doc pages actually render, and
    // the context that read null when the global was `health`-only.
    Site::setCurrent('health');

    $context = resolveAppointmentContext(['clinical_locations' => ['sickbay']]);

    expect($context['phone_display'])->toBe('(501) 296-1200')
        ->and($context['phone_name'])->toBe('the UAMS Health appointment line')
        ->and($context['main_line_enabled'])->toBeFalse();
});

it('honors the main-line toggle, and defaults it on when the global has never been saved', function (): void {
    appointmentSettings(['provider_mainline' => false]);
    expect(resolveAppointmentContext([])['main_line_enabled'])->toBeFalse();

    appointmentSettings(['provider_mainline' => null]);
    expect(resolveAppointmentContext([])['main_line_enabled'])->toBeTrue();
});

// --- Edge branches (mutation hardening) --------------------------------------
// These pin the defensive branches the happy-path cases never exercised, so a
// mutation of each stays caught. Comments name the code each guards.

// `settingValue()`: the `?->inDefaultSite()` null-safe and the `instanceof`
// guard — an absent global (fresh install / uninitialized content) must resolve
// to null, not fatal, so the CTA defaults the main line on and empties the line.
it('reads settings as null (main line on, empty line) when the global is absent', function (): void {
    /** @var Statamic\Globals\GlobalSet|null $set */
    $set = GlobalSet::findByHandle('uams_fad_settings');
    $set?->delete();
    appointmentLocation('sickbay');

    $context = resolveAppointmentContext(['clinical_locations' => ['sickbay']]);

    expect($context['main_line_enabled'])->toBeTrue()
        ->and($context['phone_name'])->toBe('')
        ->and($context['phone_display'])->toBe('')
        ->and($context['phone_tel'])->toBe('');
});

// `resolve()`: every `instanceof`-gated primary/portal field takes its empty
// fallback when no location is valid, and the direct link targets the anchor.
it('empties every primary-location and portal field when no location is valid', function (): void {
    appointmentLocation('embargoed', published: false);

    $context = resolveAppointmentContext(['clinical_locations' => ['embargoed']]);

    expect($context['primary_location_title'])->toBe('')
        ->and($context['primary_location_url'])->toBe('')
        ->and($context['direct_url'])->toBe('#locations')
        ->and($context['direct_is_anchor'])->toBeTrue()
        ->and($context['show_portal'])->toBeFalse()
        ->and($context['portal_name'])->toBe('')
        ->and($context['portal_url'])->toBe('');
});

// `resolve()` `show_portal`: a valid primary location that carries no portal
// keeps `show_portal` false (the `$portal instanceof LocalizedTerm` conjunct).
it('does not show a portal for a valid location that carries none', function (): void {
    appointmentLocation('sickbay');

    $context = resolveAppointmentContext(['clinical_locations' => ['sickbay']]);

    expect($context['show_portal'])->toBeFalse()
        ->and($context['portal_name'])->toBe('')
        ->and($context['portal_url'])->toBe('');
});

// `resolve()`: the `referral_required` / `accepting_patients` keys are present
// and carry both truthy states, so neither can be dropped from the payload.
it('carries the referral-required and accepting-patients answers through', function (): void {
    $context = resolveAppointmentContext([
        'referral_required' => true,
        'accepting_patients_query' => false,
    ]);

    expect($context['referral_required'])->toBeTrue()
        ->and($context['accepting_patients'])->toBeFalse();

    $inverse = resolveAppointmentContext([
        'referral_required' => false,
        'accepting_patients_query' => true,
    ]);

    expect($inverse['referral_required'])->toBeFalse()
        ->and($inverse['accepting_patients'])->toBeTrue();
});

// `truthy()`: the importer can store a toggle as the integer 1 rather than
// boolean true; it must still read as affirmative (the `=== 1` arm).
it('reads an integer 1 as an affirmative toggle', function (): void {
    $context = resolveAppointmentContext([
        'appointments_query' => 1,
        'appointments_individual_query' => 1,
    ]);

    expect($context['eligible'])->toBeTrue();
});

// `stringValue()`: trims surrounding whitespace.
it('trims surrounding whitespace from a stored string value', function (): void {
    appointmentLocation('sickbay', data: ['generic_name' => '  Sickbay  ']);

    $context = resolveAppointmentContext(['clinical_locations' => ['sickbay']]);

    expect($context['primary_location_title'])->toBe('Sickbay');
});

// `locationName()`: an empty `generic_name` yields the title, not the empty string.
it('falls to the entry title when the generic name is empty', function (): void {
    appointmentLocation('sickbay', data: ['title' => 'Sickbay Prime', 'generic_name' => '']);

    $context = resolveAppointmentContext(['clinical_locations' => ['sickbay']]);

    expect($context['primary_location_title'])->toBe('Sickbay Prime');
});

// `portalTerm()`: the `max_items: 1` field stores either a bare slug or a
// one-element list; the array form (`$portals[0]`) must resolve the same portal.
it('reads a portal stored as a single-element array', function (): void {
    appointmentPortal('ach-mychart', [
        'title' => "Arkansas Children's MyChart",
        'appointment_phone' => '501-364-4000',
        'appointment_phone_name' => "the Arkansas Children's line",
    ]);
    appointmentLocation('sickbay', data: ['portals' => ['ach-mychart']]);

    $context = resolveAppointmentContext(['clinical_locations' => ['sickbay']]);

    expect($context['phone_display'])->toBe('(501) 364-4000')
        ->and($context['phone_name'])->toBe("the Arkansas Children's line");
});

// `publishedLocations()`: a dangling id (deleted entry) is skipped, not
// dereferenced (`$location instanceof Entry`).
it('skips a location id that resolves to nothing', function (): void {
    $context = resolveAppointmentContext(['clinical_locations' => ['ghost']]);

    expect($context['location_count'])->toBe(0)
        ->and($context['location_valid'])->toBeFalse();
});

// `publishedLocations()`: malformed data (a bare string) is treated as no
// locations, not iterated (`! \is_array($ids)` + early return).
it('treats a non-array clinical_locations value as no locations', function (): void {
    $context = resolveAppointmentContext(['clinical_locations' => 'sickbay']);

    expect($context['location_count'])->toBe(0);
});

// `publishedLocations()`: an empty-string id in the list is skipped
// (`! \is_string($id) || $id === ''`).
it('skips empty ids in the locations list', function (): void {
    appointmentLocation('sickbay');

    $context = resolveAppointmentContext(['clinical_locations' => ['', 'sickbay']]);

    expect($context['location_count'])->toBe(1)
        ->and($context['primary_location_id'])->toBe('sickbay');
});

// `portalTerm()`: an empty portals reference yields no portal
// (`$reference === ''`).
it('does not resolve a portal from an empty portals reference', function (): void {
    appointmentLocation('sickbay', data: ['portals' => '']);

    $context = resolveAppointmentContext(['clinical_locations' => ['sickbay']]);

    expect($context['show_portal'])->toBeFalse();
});

// `displayNumber()`: the country-code strip is gated on ELEVEN digits AND a
// leading 1 (the `&&`); a ten-digit number that starts with 1 must format whole.
it('formats a ten-digit number that starts with 1 without stripping it', function (): void {
    appointmentSettings(['appointment_phone_default' => '1015551234']);
    appointmentLocation('sickbay');

    $context = resolveAppointmentContext(['clinical_locations' => ['sickbay']]);

    expect($context['phone_display'])->toBe('(101) 555-1234');
});

// `appointmentLine()` pair fallback (the `$number === ''` disjunct): a portal
// that names a spoken NAME but no NUMBER must fall back to the system pair — the
// inverse of the number-without-name case, so an empty number can't keep a
// half-filled line.
it('falls back to the system line as a pair when a portal supplies only the name', function (): void {
    appointmentPortal('ach-mychart', [
        'title' => "Arkansas Children's MyChart",
        'appointment_phone_name' => "the Arkansas Children's line",
        // No `appointment_phone`.
    ]);
    appointmentLocation('sickbay', data: ['portals' => 'ach-mychart']);

    $context = resolveAppointmentContext(['clinical_locations' => ['sickbay']]);

    expect($context['phone_display'])->toBe('(501) 686-8000')
        ->and($context['phone_name'])->toBe('the UAMS Health appointment line');
});

// `stringValue()` string cast: a non-string scalar (an importer-stored integer)
// coerces to a string rather than being passed raw to `trim()` (a TypeError
// under strict types).
it('coerces a non-string scalar value to a string', function (): void {
    appointmentLocation('sickbay', data: ['generic_name' => 90210]);

    $context = resolveAppointmentContext(['clinical_locations' => ['sickbay']]);

    expect($context['primary_location_title'])->toBe('90210');
});

// `publishedLocations()`: a non-string id (malformed data) is skipped before
// `find()`, not passed to it (a TypeError) — the `! \is_string($id)` disjunct.
it('skips a non-string id in the locations list', function (): void {
    appointmentLocation('sickbay');

    $context = resolveAppointmentContext(['clinical_locations' => [['nested'], 'sickbay']]);

    expect($context['location_count'])->toBe(1)
        ->and($context['primary_location_id'])->toBe('sickbay');
});

// --- Equivalent mutants (documented, not killable) ---------------------------
// `--mutate` leaves five survivors on this class that are EQUIVALENT mutants: the
// mutation cannot change observable behavior, so no test can kill them (forcing a
// kill would mean contorting production code to satisfy the tool). Named here so
// the ~97% score is understood rather than silently accepted:
//
//   - resolve() `$count === 1 && $primary instanceof Entry` -> `... && true`:
//     `$count === 1` only holds when a published location exists, so `$primary`
//     is always an Entry at that point.
//   - publishedLocations() `$id === ''` -> `$id === '...'`: an empty id that slips
//     past is caught downstream by `find('') === null`, so the count is identical.
//   - portalTerm() `$reference === ''` -> `$reference === '...'`: an empty
//     reference falls through to `Term::find('portals::')`, which resolves to
//     null, so no portal resolves either way.
//   - portalTerm() `$found instanceof LocalizedTerm` -> `true`: `Term::find`
//     returns a LocalizedTerm here, and the `TaxonomyTerm` branch resolves the
//     same term data via `inDefaultLocale()`.
//   - displayNumber() `substr($digits, 6, 4)` -> `..., 5`: a validated ten-digit
//     string has exactly four characters from offset 6.
