<?php

declare(strict_types=1);

use App\Services\ComputedFields\ProviderPeople\ProviderProfileBodyResolver;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Globals\Variables;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Whether anything renders between the clinical provider lens's two appointment
 * CTAs (UAMS-Web/uams-statamic-archive#1695) — the fact that gates the foot-of-page repeat.
 *
 * Every case runs against real persisted entries and a real global, because the
 * relation checks ARE a Stache walk: the sections are gated on the entries a
 * relation RESOLVES, so an unpublished-only relation has to actually resolve to
 * nothing rather than be asserted against a mock.
 *
 * The "accounts for every section" case at the foot is the drift guard: it counts
 * the sections `_profile.antlers.html` renders between the two CTAs, so adding one
 * without a line in the resolver reddens the suite. It does NOT assert that a
 * section's gate and its resolver check agree on the same edge value — that is an
 * accepted gap, named in the resolver's own docblock.
 *
 * @command php artisan test --compact tests/Feature/ComputedFields/ProviderProfileBodyResolverTest.php
 */
uses(StacheIsolatedTestCase::class);

/**
 * Each `entries` relation section, mapped to the collection its blueprint field
 * actually points at (`resources/fieldsets/uamsweb_common_fad.yaml`).
 *
 * @var array<string, string>
 */
const PROFILE_BODY_RELATIONS = [
    'expertise' => 'clinical_expertise',
    'conditions' => 'clinical_conditions',
    'treatments' => 'clinical_services',
    'clinical_locations' => 'clinical_locations',
    'clinical_resources' => 'clinical_resources',
];

/**
 * The distinct collections the relation sections resolve against.
 *
 * @var list<string>
 */
const PROFILE_BODY_RELATION_COLLECTIONS = [
    'clinical_expertise',
    'clinical_conditions',
    'clinical_services',
    'clinical_locations',
    'clinical_resources',
];

beforeEach(function (): void {
    // Pad Pest's mutant-child timeout once per process under `--mutate`.
    static $padded = false;
    if (! $padded && runningUnderMutate()) {
        usleep(20_000_000);
        $padded = true;
    }

    $people = CollectionFacade::make('people');
    $people->title('People');
    $people->save();

    // Dated with `future: private`, mirroring `content/collections/clinical_locations.yaml`,
    // so a scheduled entry behaves here as it does in production. All five are created
    // because each relation handle points at a DIFFERENT collection in the blueprint
    // (`conditions` -> clinical_conditions, `treatments` -> clinical_services), and a
    // test that resolved three handles against one collection would only look like it
    // covered them.
    foreach (PROFILE_BODY_RELATION_COLLECTIONS as $handle) {
        $collection = CollectionFacade::make($handle);
        $collection->title($handle);
        $collection->dated(true);
        $collection->futureDateBehavior('private');
        $collection->save();
    }

    profileBodySettings();
});

/**
 * Persist the Find-a-Doc settings global with the given field values.
 *
 * @param  array<string, mixed>  $data
 */
function profileBodySettings(array $data = []): void
{
    /** @var Statamic\Globals\GlobalSet $set */
    $set = GlobalSet::make('uams_fad_settings');
    $set->title('Find-a-Doc Settings');
    $set->save();

    /** @var Statamic\Sites\Site $defaultSite */
    $defaultSite = Site::default();

    /** @var Variables $vars */
    $vars = $set->makeLocalization($defaultSite->handle());
    $vars->data($data);
    $vars->save();
}

/**
 * Persist one related entry and return its id.
 *
 * @param  array<string, mixed>  $data
 */
function profileBodyRelated(string $collection, string $id, bool $published = true, ?string $date = null, array $data = []): string
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection($collection);
    $entry->id($id);
    $entry->slug($id);
    $entry->published($published);
    $entry->data($data + ['title' => ucfirst($id)]);
    $entry->date($date ?? '2020-01-01');
    $entry->saveQuietly();

    return $id;
}

/**
 * Whether the profile body renders for a provider with the given stored data.
 *
 * @param  array<string, mixed>  $data
 */
function resolvesProfileBody(array $data): bool
{
    /** @var Statamic\Entries\Entry $provider */
    $provider = Entry::make();
    $provider->collection('people');
    $provider->id('mccoy');
    $provider->slug('mccoy');
    $provider->published(true);
    $provider->data($data + ['title' => 'Leonard McCoy']);
    $provider->saveQuietly();

    return app(ProviderProfileBodyResolver::class)->resolve($provider);
}

// --- The empty profile -------------------------------------------------------

it('reports nothing rendered for a provider carrying only appointment facts', function (): void {
    // The whole point of the ticket: an appointment-eligible provider with no body
    // content at all, who otherwise got the same CTA twice, back to back.
    expect(resolvesProfileBody([
        'appointments_query' => true,
        'appointments_individual_query' => true,
        'accepting_patients_query' => true,
    ]))->toBeFalse();
});

it('reports nothing rendered for a provider whose section fields are all empty', function (): void {
    // Empty is empty in every shape a cleared field can take: an empty Bard array,
    // an empty replicator, an empty relation, and a blank string.
    expect(resolvesProfileBody([
        'bio' => [],
        'bio_clinical' => [],
        'clinical_focus' => [],
        'certifications' => [],
        'awards' => [],
        'expertise' => [],
        'clinical_locations' => [],
        'clinical_resources' => [],
        'podcast_guest_name_uams_health_talk' => '   ',
    ]))->toBeFalse();
});

// --- The content sections ----------------------------------------------------

it('reports a rendered body for each Bard and replicator section in turn', function (string $handle): void {
    expect(resolvesProfileBody([$handle => [['type' => 'paragraph']]]))->toBeTrue();
})->with(['bio', 'bio_clinical', 'clinical_focus', 'certifications', 'awards']);

it('reports nothing rendered for a replicator holding only disabled sets', function (string $handle): void {
    // `Replicator::performAugmentation()` rejects every set flagged `enabled: false`,
    // so the field augments to `[]` and its section renders nothing. Counting the raw
    // stored array would report a section that is not on the page and put the
    // back-to-back CTA the ticket removes straight back.
    expect(resolvesProfileBody([
        $handle => [
            ['id' => 'one', 'type' => 'award', 'enabled' => false, 'title' => 'Hidden'],
        ],
    ]))->toBeFalse();
})->with(['awards', 'certifications']);

it('reports a rendered body when a replicator mixes a disabled and an enabled set', function (): void {
    expect(resolvesProfileBody([
        'awards' => [
            ['id' => 'one', 'type' => 'award', 'enabled' => false, 'title' => 'Hidden'],
            ['id' => 'two', 'type' => 'award', 'title' => 'Shown'],
        ],
    ]))->toBeTrue();
});

it('treats a set with no enabled flag as enabled, like the fieldtype does', function (): void {
    // Only an explicit `false` removes a set; an absent flag means enabled, which is
    // also what every Bard node looks like.
    expect(resolvesProfileBody(['awards' => [['id' => 'one', 'type' => 'award']]]))->toBeTrue()
        ->and(resolvesProfileBody(['bio' => [['type' => 'paragraph']]]))->toBeTrue();
});

// --- The relation sections ---------------------------------------------------

it('reports a rendered body for each relation section in turn', function (string $handle, string $collection): void {
    $id = profileBodyRelated($collection, $handle.'-one');

    expect(resolvesProfileBody([$handle => [$id]]))->toBeTrue();
})->with(array_map(
    static fn (string $handle, string $collection): array => [$handle, $collection],
    array_keys(PROFILE_BODY_RELATIONS),
    array_values(PROFILE_BODY_RELATIONS)
));

it('reports nothing rendered for a relation holding only an unpublished entry', function (): void {
    // The UAMS-Web/uams-statamic-archive#1693 trap. The section gate is `| count`, which counts the published entries
    // of the augmented StatusQueryBuilder, so an unpublished-only relation renders NO
    // section. Counting the stored ids here would report a section that never appears
    // and put the foot-of-page CTA back on a profile with nothing above it.
    $id = profileBodyRelated('clinical_resources', 'draft-resource', published: false);

    expect(resolvesProfileBody(['clinical_resources' => [$id]]))->toBeFalse();
});

it('reports nothing rendered for a relation holding only a future-dated entry', function (): void {
    // `status()` not `published()`: a flagged-published entry dated tomorrow is
    // `scheduled` on a `future: private` collection and the relation excludes it.
    $id = profileBodyRelated('clinical_locations', 'future-clinic', date: now()->addYear()->format('Y-m-d'));

    expect(resolvesProfileBody(['clinical_locations' => [$id]]))->toBeFalse();
});

it('reports a rendered body when a relation mixes a published and an unpublished entry', function (): void {
    $live = profileBodyRelated('clinical_resources', 'live-resource');
    $draft = profileBodyRelated('clinical_resources', 'draft-resource-two', published: false);

    expect(resolvesProfileBody(['clinical_resources' => [$draft, $live]]))->toBeTrue();
});

it('skips a malformed relation id and still finds a published entry after it', function (): void {
    $live = profileBodyRelated('clinical_locations', 'live-clinic');

    expect(resolvesProfileBody(['clinical_locations' => [1701, '', $live]]))->toBeTrue();
});

it('reports nothing rendered for a relation holding an id that no longer exists', function (): void {
    expect(resolvesProfileBody(['clinical_locations' => ['deleted-clinic']]))->toBeFalse();
});

// --- The podcast section -----------------------------------------------------

it('reports a rendered body for a provider carrying a podcast guest name', function (): void {
    expect(resolvesProfileBody(['podcast_guest_name_uams_health_talk' => 'Leonard McCoy']))->toBeTrue();
});

it('reports nothing rendered when the podcast toggle is explicitly off', function (): void {
    profileBodySettings(['health_talk_query' => false]);

    expect(resolvesProfileBody(['podcast_guest_name_uams_health_talk' => 'Leonard McCoy']))->toBeFalse();
});

it('reports a rendered body when the podcast toggle has never been saved', function (): void {
    // The field's own `default: true`, and the partial gates on `!== false` for the
    // same reason — an editor who has never opened the tab has not turned it off.
    profileBodySettings();

    expect(resolvesProfileBody(['podcast_guest_name_uams_health_talk' => 'Leonard McCoy']))->toBeTrue();
});

it('reports nothing rendered for the podcast toggle alone, with no guest name', function (): void {
    profileBodySettings(['health_talk_query' => true]);

    expect(resolvesProfileBody([]))->toBeFalse();
});

// --- Ontology suppression (UAMS-Web/uams-statamic-archive#1687) --------------------------------------------

it('does not count a suppressed Areas of Expertise as a rendered section', function (): void {
    // UAMS-Web/uams-statamic-archive#1687 hides the expertise section for a provider matching a removal rule. A
    // provider whose ONLY body content was that section then has nothing between the two
    // appointment CTAs, so counting it here would put UAMS-Web/uams-statamic-archive#1695's back-to-back CTA straight
    // back — and the template drift guard cannot see it, because the partial call is
    // still present inside its new `{{ if }}`.
    profileBodySettings([
        'remove_ontology_criteria' => [
            ['type' => 'new_removal', 'enabled' => true, 'remove_service_lines' => 'surgical-specialties'],
        ],
    ]);

    expect(resolvesProfileBody([
        'service_line' => 'surgical-specialties',
        'expertise' => [profileBodyRelated('clinical_expertise', 'cardiology')],
    ]))->toBeFalse();
});

it('still counts Areas of Expertise for a provider no removal rule matches', function (): void {
    // Bounds the case above: suppression is the exception, and an unmatched provider's
    // section must go on counting. Same fixture, different service line.
    profileBodySettings([
        'remove_ontology_criteria' => [
            ['type' => 'new_removal', 'enabled' => true, 'remove_service_lines' => 'surgical-specialties'],
        ],
    ]);

    expect(resolvesProfileBody([
        'service_line' => 'primary-care',
        'expertise' => [profileBodyRelated('clinical_expertise', 'cardiology')],
    ]))->toBeTrue();
});

it('still counts conditions and treatments for a suppressed provider', function (): void {
    // Only the routable Areas of Expertise is suppressed (UAMS-Web/uams-statamic-archive#1687). A suppressed provider
    // who also has conditions keeps a rendered body, so the foot CTA still repeats —
    // under-reporting here would DROP a real CTA, the regression the class docblock
    // names as the one it must never cause.
    profileBodySettings([
        'remove_ontology_criteria' => [
            ['type' => 'new_removal', 'enabled' => true, 'remove_service_lines' => 'surgical-specialties'],
        ],
    ]);

    expect(resolvesProfileBody([
        'service_line' => 'surgical-specialties',
        'expertise' => [profileBodyRelated('clinical_expertise', 'cardiology')],
        'conditions' => [profileBodyRelated('clinical_conditions', 'arrhythmia')],
    ]))->toBeTrue();
});

// --- The drift guard ---------------------------------------------------------

it('accounts for every section the profile renders between the two appointment CTAs', function (): void {
    // The one coupling this resolver cannot express in code: its section LIST has to
    // stay in step with `_profile.antlers.html`. Each check above reproduces the gate
    // its own section uses, but a section ADDED to the template without a line in the
    // resolver would silently under-report and drop the foot-of-page CTA from a real
    // profile — the regression the class docblock names as the one it must never cause.
    //
    // So count the section-producing partial calls between the two CTA include sites
    // and pin the total. Adding, removing, or re-homing a section reddens this test
    // until the resolver is updated to match, which is what makes the docblock's
    // "one place that list lives" claim true rather than aspirational.
    $template = file_get_contents(resource_path('views/people/provider/_profile.antlers.html'));

    expect($template)->toBeString();

    // Narrow for static analysis; the expectation above has already failed the test
    // if the template could not be read.
    if (! \is_string($template)) {
        return;
    }

    $start = strpos($template, 'appointment_cta instance="1"');
    $end = strpos($template, '{{ if provider_profile_body_rendered }}');

    expect($start)->not->toBeFalse();
    expect($end)->not->toBeFalse();

    // Both markers are required; a rename on either side must fail loudly here rather
    // than silently scan the whole file.
    if ($start === false || $end === false) {
        return;
    }

    expect($end)->toBeGreaterThan($start);

    $body = substr($template, $start, $end - $start);

    // Opening calls only — `{{ /partial:… }}` closers do not match this pattern.
    $sections = preg_match_all(
        '/\{\{\s*partial:(page_builder\/uamsweb_block|people\/provider\/ontology_list|people\/provider\/podcast|people\/components\/awards)\b/',
        $body
    );

    // About, Clinical Focus, Areas of Expertise, Conditions, Treatments, Locations,
    // Podcast, Resources, Credentials, Awards.
    expect($sections)->toBe(10);
});

it('accounts for every conditional wrapped around a profile section', function (): void {
    // The count above is blind to a section that stays present but becomes CONDITIONAL.
    // UAMS-Web/uams-statamic-archive#1687 wraps Areas of Expertise in `{{ if !hide_medical_ontology }}`; the opening
    // partial call is still there, so the count stayed 10 and this resolver could have
    // gone on reporting a section that no longer renders — reinstating UAMS-Web/uams-statamic-archive#1695's
    // back-to-back CTA for a provider whose expertise was the only thing between them.
    // The adversarial review on UAMS-Web/uams-statamic-archive#1687 found exactly that gap.
    //
    // So pin the SET of conditions gating a section partial. A new gate, a removed gate,
    // or a rewritten condition reddens this until the resolver is taught to match.
    $template = file_get_contents(resource_path('views/people/provider/_profile.antlers.html'));

    expect($template)->toBeString();

    if (! \is_string($template)) {
        return;
    }

    $start = strpos($template, 'appointment_cta instance="1"');
    $end = strpos($template, '{{ if provider_profile_body_rendered }}');

    expect($start)->not->toBeFalse();
    expect($end)->not->toBeFalse();

    if ($start === false || $end === false) {
        return;
    }

    $body = substr($template, $start, $end - $start);

    // An `{{ if … }}` whose very next tag opens a section-producing partial. Anything
    // further in (a conditional INSIDE a block's body) gates markup within a section
    // rather than the section itself, and is not this resolver's business.
    preg_match_all(
        '/\{\{\s*if\s+(?<condition>[^}]+?)\s*\}\}\s*\{\{\s*partial:(?:page_builder\/uamsweb_block|people\/provider\/ontology_list|people\/provider\/podcast|people\/components\/awards)\b/',
        $body,
        $matches
    );

    expect($matches['condition'])->toBe(['!hide_medical_ontology']);
});

// --- Edge branches (mutation hardening) --------------------------------------

// `settingValue()`: the `?->inDefaultSite()` null-safe and the `instanceof`
// guard — an absent global must resolve to null, not fatal. The podcast gate
// then reads `null !== false` as true, so a provider with only a podcast guest
// name still renders (matching the field's `default: true`).
it('renders the podcast section when the global is absent (health_talk defaults on)', function (): void {
    /** @var Statamic\Globals\GlobalSet|null $set */
    $set = GlobalSet::findByHandle('uams_fad_settings');
    $set?->delete();

    expect(resolvesProfileBody(['podcast_guest_name_uams_health_talk' => 'Leonard McCoy']))->toBeTrue();
});

// `hasContent()` scalar fallback: a stored `false` is NOT content (the `!== false`
// arm), so a section field holding it renders nothing.
it('treats a scalar false section value as no content', function (): void {
    expect(resolvesProfileBody(['bio' => false]))->toBeFalse();
});

// `hasContent()` scalar fallback: a non-null, non-false scalar IS content (the
// `!== null` arm), so a section field holding it renders.
it('treats a non-null scalar section value as content', function (): void {
    expect(resolvesProfileBody(['bio' => 42]))->toBeTrue();
});

// `isEnabledItem()`: a list element that is not a set carries no `enabled` flag to
// read, so it counts as content rather than being dereferenced.
it('treats a non-array list element as content', function (): void {
    expect(resolvesProfileBody(['bio' => ['Captain Kirk']]))->toBeTrue();
});

// `hasPublishedRelation()`: a non-string relation id (malformed data) is skipped
// before `find()`, not passed to it (a TypeError) — the `! \is_string($id)`
// disjunct.
it('skips a non-string relation id rather than dereferencing it', function (): void {
    expect(resolvesProfileBody(['expertise' => [['nested']]]))->toBeFalse();
});

// `hasPublishedRelation()` `continue`: a skippable id followed by a valid
// published relation must still resolve — the loop continues past the bad id
// rather than breaking out of it.
it('continues past a skippable relation id to a later valid one', function (): void {
    $id = profileBodyRelated('clinical_expertise', 'cardiology');

    expect(resolvesProfileBody(['expertise' => ['', $id]]))->toBeTrue();
});

// --- Equivalent mutant (documented, not killable) ----------------------------
// `hasPublishedRelation()` `$id === ''` -> `$id === '...'`: an empty id that
// slips past the string check is caught downstream by `find('') === null`, so no
// relation resolves either way — the mutation cannot change the result.
