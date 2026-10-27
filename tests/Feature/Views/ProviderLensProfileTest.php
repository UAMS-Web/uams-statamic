<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\LabeledValue;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Render coverage for the clinical provider lens body (`people/provider/profile`,
 * UAMS-Web/uams-statamic-archive#571 / UAMS-Web/uams-statamic-archive#1636) — the distinct clinical-lens template the substrate renders for a
 * resolved canonical `people` entry. Rendered as a partial with a synthetic person
 * scope (mirroring ProviderSpotlightTemplateTest) so it needs no layout, no Vite,
 * and no real asset (the portrait block is skipped without a resolvable
 * `portrait:permalink`), keeping it hermetic and CI-safe.
 *
 * @command php artisan test --compact tests/Feature/Views/ProviderLensProfileTest.php
 */

/**
 * Render the lens body partial with the given Antlers scope.
 *
 * @param  array<string, mixed>  $scope
 */
function renderProviderLensBody(array $scope): string
{
    return (string) Antlers::parse('{{ partial:people/provider/profile }}', $scope);
}

it('renders the provider identity, bio, and the lens marker', function (): void {
    $html = renderProviderLensBody([
        'title' => 'Leonard McCoy',
        'display_name_full_with_credentials' => ['text' => 'Leonard McCoy, M.D.'],
        'display_name_short_with_prefix' => ['text' => 'Dr. McCoy'],
        'job_title' => 'Chief Medical Officer',
        'bio' => '<p>Chief medical officer aboard the Enterprise.</p>',
    ]);

    // The clinical lens rendered its distinct template (the substrate marker), the
    // person's identity, the clinical bio, and the short-name-derived bio heading
    // (the block header applies a widont filter, so match up to the last space).
    expect($html)->toContain('data-lens="provider"')
        ->toContain('Leonard McCoy, M.D.')
        ->toContain('Chief medical officer aboard the Enterprise.')
        ->toContain('About Dr.');
});

it('strip_tags-escapes the editor-controlled name (Antlers is raw by default)', function (): void {
    $html = renderProviderLensBody([
        'title' => 'Leonard McCoy',
        'display_name_full_with_credentials' => ['text' => 'Leonard <script>alert(1)</script>McCoy'],
        'display_name_short_with_prefix' => ['text' => 'Dr. McCoy'],
    ]);

    // The name sink is `| strip_tags`, so the injected `<script>` tag cannot reach
    // the output (its inert text content is harmless), and the real name still renders.
    expect($html)->not->toContain('<script>')
        ->toContain('Leonard')
        ->toContain('McCoy');
});

// --- The clinical field selection (UAMS-Web/uams-statamic-archive#571) ------------------------------------
//
// A `true_false` field augments to a `LabeledValue`, never a raw bool, so these
// fixtures inject that shape — a raw-scalar fixture would pass while the
// production template is inert (the UAMS-Web/uams-statamic-archive#1190 trap).

/**
 * The minimum identity scope every clinical-section fixture needs.
 *
 * @return array<string, mixed>
 */
function providerIdentityScope(): array
{
    return [
        'title' => 'Leonard McCoy',
        'display_name_full_with_credentials' => ['text' => 'Leonard McCoy, M.D.'],
        'display_name_short_with_prefix' => ['text' => 'Dr. McCoy'],
    ];
}

it('states that a provider is accepting new patients', function (): void {
    $html = renderProviderLensBody(providerIdentityScope() + [
        'accepting_patients_query' => new LabeledValue(true, 'Yes'),
    ]);

    expect($html)->toContain('Accepting new patients')
        ->and($html)->not->toContain('Not accepting new patients');
});

// The negative moved to the appointment CTA (UAMS-Web/uams-statamic-archive#1676), which opens with "This provider
// is not currently accepting new patients." — the page must not say it twice, and
// WordPress renders its Accepting-New-Patients row only for an appointment-eligible
// provider anyway. `ProviderLensAppointmentCtaTest` owns the assertions on that
// sentence; this pins that the facts list no longer duplicates it.
it('leaves the not-accepting statement to the appointment CTA', function (): void {
    $html = renderProviderLensBody(providerIdentityScope() + [
        'accepting_patients_query' => new LabeledValue(false, 'No'),
    ]);

    expect($html)->not->toContain('Not accepting new patients')
        ->and($html)->not->toContain('provider-accepting-patients');
});

it('omits the appointments section entirely for a person with no scheduling facts', function (): void {
    // An unset `true_false` is null, not false — a non-provider must show no
    // appointments block at all rather than a block asserting "Not accepting".
    $html = renderProviderLensBody(providerIdentityScope());

    expect($html)->not->toContain('provider-at-a-glance')
        ->and($html)->not->toContain('Accepting new patients')
        ->and($html)->not->toContain('Not accepting new patients');
});

it('shows primary-care and second-opinion facts only in the affirmative', function (): void {
    $affirmative = renderProviderLensBody(providerIdentityScope() + [
        'primary_care_query' => new LabeledValue(true, 'Yes'),
        'second_opinions_query' => new LabeledValue(true, 'Yes'),
    ]);

    // An AFFIRMATIVE `accepting_patients_query` keeps the BLOCK alive. Without it an
    // all-false fixture fails the block's own `:when` gate, nothing renders at all,
    // and the two `not->toContain` assertions below pass for the wrong reason — they
    // would stay green even if the row guards accepted an explicit "No".
    $negative = renderProviderLensBody(providerIdentityScope() + [
        'accepting_patients_query' => new LabeledValue(true, 'Yes'),
        'primary_care_query' => new LabeledValue(false, 'No'),
        'second_opinions_query' => new LabeledValue(false, 'No'),
    ]);

    expect($affirmative)->toContain('Provides primary care')
        ->and($affirmative)->toContain('Offers second opinions')
        ->and($negative)->toContain('Accepting new patients')
        ->and($negative)->not->toContain('Provides primary care')
        ->and($negative)->not->toContain('Offers second opinions');
});

it('lists the patient types, escaping the entry title', function (): void {
    $html = renderProviderLensBody(providerIdentityScope() + [
        'patient_types' => [
            ['title' => 'Adults'],
            ['title' => '<img src=x onerror=alert(1)>Children'],
        ],
    ]);

    expect($html)->toContain('Adults')
        ->and($html)->toContain('Children')
        ->and($html)->not->toContain('<img');
});

it('prefers the clinical bio over the general bio', function (): void {
    $html = renderProviderLensBody(providerIdentityScope() + [
        'bio' => '<p>General biography.</p>',
        'bio_clinical' => '<p>Clinical biography.</p>',
    ]);

    expect($html)->toContain('Clinical biography.')
        ->and($html)->not->toContain('General biography.');
});

it('falls back to the general bio when no clinical bio is written', function (): void {
    $html = renderProviderLensBody(providerIdentityScope() + [
        'bio' => '<p>General biography.</p>',
    ]);

    expect($html)->toContain('General biography.');
});

// `block_header` pipes the heading through `widont`, which replaces the last space
// with a non-breaking space — so a multi-word heading never appears verbatim in the
// output. These assertions key off the block's stable `id` / `class` instead, which
// is also what makes the negative cases meaningful rather than trivially true.
it('renders the clinical focus section only when set', function (): void {
    $withFocus = renderProviderLensBody(providerIdentityScope() + [
        'clinical_focus' => '<p>Trauma surgery and xenobiology.</p>',
    ]);

    $withoutFocus = renderProviderLensBody(providerIdentityScope());

    expect($withFocus)->toContain('provider-clinical-focus')
        ->and($withFocus)->toContain('Trauma surgery and xenobiology.')
        ->and($withoutFocus)->not->toContain('provider-clinical-focus');
});

it('renders each clinical ontology section as linked, escaped entry titles', function (): void {
    $html = renderProviderLensBody(providerIdentityScope() + [
        'expertise' => [['title' => 'Emergency Medicine', 'url' => '/health/expertise/emergency-medicine']],
        'conditions' => [['title' => 'Vulcan Neuropressure', 'url' => '/health/conditions/vulcan-neuropressure']],
        'treatments' => [['title' => 'Cordrazine Therapy', 'url' => '/health/treatments/cordrazine-therapy']],
        'clinical_locations' => [['title' => 'Sickbay', 'url' => '/health/locations/sickbay']],
    ]);

    expect($html)
        ->toContain('provider-expertise')
        ->toContain('/health/expertise/emergency-medicine')
        ->toContain('Emergency Medicine')
        ->toContain('provider-conditions')
        ->toContain('Vulcan Neuropressure')
        ->toContain('provider-treatments')
        ->toContain('Cordrazine Therapy')
        // Locations are no longer an ontology list: they render as real location
        // cards (UAMS-Web/uams-statamic-archive#1676), so the section is keyed off its own block class and the
        // `#locations` anchor the appointment CTA targets. The card markup is
        // asserted too — without it, reverting the whole locations change back to
        // `people/provider/ontology_list` keeps this test green.
        ->toContain('profile-locations')
        ->toContain('id="locations"')
        ->toContain('Sickbay')
        ->toContain('schema.org/MedicalClinic')
        ->toContain('View location');
});

it('escapes an editor-controlled ontology title and href', function (): void {
    $html = renderProviderLensBody(providerIdentityScope() + [
        'conditions' => [[
            'title' => '<img src=x onerror=alert(1)>Neuropressure',
            'url' => 'javascript:alert(1)',
        ]],
    ]);

    expect($html)->not->toContain('<img')
        ->and($html)->not->toContain('href="javascript:alert(1)"')
        ->and($html)->toContain('Neuropressure');
});

it('omits an ontology section the provider has nothing in', function (): void {
    $html = renderProviderLensBody(providerIdentityScope() + [
        'expertise' => [['title' => 'Emergency Medicine', 'url' => '/health/expertise/emergency-medicine']],
    ]);

    expect($html)->toContain('provider-expertise')
        ->and($html)->not->toContain('provider-conditions')
        ->and($html)->not->toContain('provider-treatments')
        ->and($html)->not->toContain('profile-locations');
});

// --- Remove Medical Ontology (UAMS-Web/uams-statamic-archive#1687) -----------------------------------------

it('hides Areas of Expertise for a provider a removal rule matches', function (): void {
    // The acceptance criterion, at the render: a matching provider loses the section
    // itself, not merely its links. `hide_medical_ontology` is the computed the
    // `MedicalOntologySuppressionResolver` registers; the resolver's own matching is
    // covered in `MedicalOntologySuppressionResolverTest`.
    $html = renderProviderLensBody(providerIdentityScope() + [
        'hide_medical_ontology' => true,
        'expertise' => [['title' => 'Emergency Medicine', 'url' => '/health/expertise/emergency-medicine']],
    ]);

    expect($html)->not->toContain('provider-expertise')
        ->and($html)->not->toContain('Emergency Medicine')
        ->and($html)->not->toContain('/health/expertise/emergency-medicine');
});

it('keeps conditions and treatments visible for a suppressed provider', function (): void {
    // Only the routable Areas of Expertise is suppressed. `clinical_conditions` and
    // `clinical_services` have no `route`, so those sections render unlinked text and
    // there is nothing for a removal rule to remove. Without this case, widening the
    // gate to all three sections would pass every other test in the file.
    $html = renderProviderLensBody(providerIdentityScope() + [
        'hide_medical_ontology' => true,
        'expertise' => [['title' => 'Emergency Medicine', 'url' => '/health/expertise/emergency-medicine']],
        'conditions' => [['title' => 'Vulcan Neuropressure']],
        'treatments' => [['title' => 'Cordrazine Therapy']],
    ]);

    expect($html)->not->toContain('provider-expertise')
        ->and($html)->toContain('provider-conditions')
        ->and($html)->toContain('Vulcan Neuropressure')
        ->and($html)->toContain('provider-treatments')
        ->and($html)->toContain('Cordrazine Therapy');
});

it('renders Areas of Expertise for a provider no removal rule matches', function (): void {
    $html = renderProviderLensBody(providerIdentityScope() + [
        'hide_medical_ontology' => false,
        'expertise' => [['title' => 'Emergency Medicine', 'url' => '/health/expertise/emergency-medicine']],
    ]);

    expect($html)->toContain('provider-expertise')
        ->and($html)->toContain('Emergency Medicine');
});

it('renders Areas of Expertise when the suppression flag is absent entirely', function (): void {
    // The gate must fail OPEN. The flag is a computed on `people` and
    // `clinical_providers`, and this partial is reachable from scopes that carry no such
    // key — a preview, a test scope, a future lens. An absent key hiding real clinical
    // content would be a silent content outage rather than a visible error.
    $html = renderProviderLensBody(providerIdentityScope() + [
        'expertise' => [['title' => 'Emergency Medicine', 'url' => '/health/expertise/emergency-medicine']],
    ]);

    expect($html)->toContain('provider-expertise')
        ->and($html)->toContain('Emergency Medicine');
});

// THE shape that matters for the ontology sections. A plain-array fixture omits the key
// entirely when a provider has no conditions — but a real augmented entry NEVER omits it:
// `AugmentedEntry::keys()` merges every blueprint handle, so an empty relation arrives as
// a `Value` wrapping an empty query builder, which is a truthy OBJECT. Gating on the bare
// field therefore rendered a section whose loop fell through to the parent scope and
// linked the provider to themselves. This asserts the production shape.
it('omits an ontology section whose augmented relation resolves to nothing', function (): void {
    $conditions = Blueprint::find('collections/people/person')?->field('conditions');

    if (! $conditions instanceof Field) {
        $this->markTestSkipped('Requires the people blueprint with a `conditions` field.');
    }

    $conditions->setValue([]);
    $augmented = $conditions->augment();

    if (! $augmented instanceof Field) {
        $this->markTestSkipped('The people blueprint `conditions` field did not augment.');
    }

    $html = renderProviderLensBody(providerIdentityScope() + [
        'conditions' => $augmented->value(),
    ]);

    expect($html)->not->toContain('provider-conditions')
        ->and($html)->not->toContain('provider-ontology-item')
        // The self-link is the exact symptom: the loop emitting once against the parent.
        ->and($html)->not->toContain('Leonard McCoy, M.D.</a>');
});

it('renders the credentials section from the provider certifications', function (): void {
    $withCredentials = renderProviderLensBody(providerIdentityScope() + [
        'certifications' => [[
            'type' => 'board_certification',
            'activity' => ['name' => 'Orthopaedic Surgery'],
        ]],
    ]);

    expect($withCredentials)->toContain('provider-credentials')
        ->and($withCredentials)->toContain('Orthopaedic Surgery')
        ->and(renderProviderLensBody(providerIdentityScope()))->not->toContain('provider-credentials');
});

it('lists the clinical specializations as the provider specialties', function (): void {
    $html = renderProviderLensBody(providerIdentityScope() + [
        'clinical_specializations' => [['title' => 'Orthopaedic Surgery']],
    ]);

    expect($html)->toContain('provider-specialization')
        ->and($html)->toContain('Orthopaedic Surgery');
});

// The block header emits `{{ heading | widont }}` with no escaping, so the name has to be
// escaped at the call site or an editor/importer payload renders live on a public page.
// `widont` only swallows the last space, so a space-free payload would survive whole.
it('escapes the provider name in the bio block heading', function (): void {
    $html = renderProviderLensBody([
        'title' => 'Leonard McCoy',
        'display_name_full_with_credentials' => ['text' => 'Leonard McCoy, M.D.'],
        'display_name_short_with_prefix' => ['text' => 'Dr. McCoy<svg/onload=alert(1)></svg>'],
        'bio' => '<p>Chief medical officer aboard the Enterprise.</p>',
    ]);

    expect($html)->not->toContain('<svg/onload')
        ->and($html)->not->toContain('</svg>')
        ->and($html)->toContain('McCoy');
});

// The clinical lens is the canonical clinical profile, so it must not link the patient
// back to the legacy WordPress provider page it replaces.
it('does not surface the legacy clinical profile link on the canonical clinical lens', function (): void {
    $html = renderProviderLensBody(providerIdentityScope() + [
        'clinical_profile_url' => 'https://uamshealth.com/provider/leonard-mccoy',
    ]);

    expect($html)->not->toContain('UAMS Health Provider Profile')
        ->and($html)->not->toContain('https://uamshealth.com/provider/leonard-mccoy');
});

// --- Related clinical resources (UAMS-Web/uams-statamic-archive#1684) -------------------------------------

it('heads the locations section with the provider name, as WordPress does', function (): void {
    $html = renderProviderLensBody(providerIdentityScope() + [
        'clinical_locations' => [['title' => 'Sickbay', 'url' => '/health/locations/sickbay']],
    ]);

    // `block_header` pipes the heading through `widont`, so the last space becomes a
    // non-breaking one and the full string never appears verbatim.
    expect($html)->toContain('Locations Where Dr. McCoy')
        ->and($html)->toContain('Practices');
});

it('renders the provider related clinical resources as cards, in the entered order', function (): void {
    $html = renderProviderLensBody(providerIdentityScope() + [
        'clinical_resources' => [
            ['title' => 'Knee Replacement Recovery', 'url' => '/health/resources/knee', 'blueprint' => 'article'],
            ['title' => 'Shoulder Arthroscopy', 'url' => '/health/resources/shoulder', 'blueprint' => 'video'],
        ],
    ]);

    expect($html)->toContain('provider-resources')
        // The provider's name in the heading, not just the fixed copy — asserting
        // "Resources Related to" alone stays green with the interpolation deleted.
        ->and($html)->toContain('Resources Related to Dr.')
        ->and($html)->toContain('Knee Replacement Recovery')
        ->and($html)->toContain('Shoulder Arthroscopy')
        // The shared card, not provider-specific markup.
        ->and($html)->toContain('schema.org/CreativeWork')
        ->and((int) strpos($html, 'Knee Replacement Recovery'))->toBeLessThan((int) strpos($html, 'Shoulder Arthroscopy'));
});

it('renders no resources section for a provider with none', function (): void {
    expect(renderProviderLensBody(providerIdentityScope()))->not->toContain('provider-resources');
});

// The augmented shape, as with the ontology sections: an `entries` relation is a
// truthy query-builder object whose blueprint key is NEVER absent, so a bare gate
// would render an empty section whose loop falls through to the parent scope.
it('omits the resources section whose augmented relation resolves to nothing', function (): void {
    $field = Blueprint::find('collections/people/person')?->field('clinical_resources');

    if (! $field instanceof Field) {
        $this->markTestSkipped('Requires the people blueprint with a `clinical_resources` field.');
    }

    $field->setValue([]);
    $augmented = $field->augment();

    if (! $augmented instanceof Field) {
        $this->markTestSkipped('The people blueprint `clinical_resources` field did not augment.');
    }

    $html = renderProviderLensBody(providerIdentityScope() + ['clinical_resources' => $augmented->value()]);

    expect($html)->not->toContain('provider-resources')
        ->and($html)->not->toContain('schema.org/CreativeWork');
});

// The heading interpolates the short-name computed, which `block_header` renders
// through `widont` — so escape at the call site, as the About heading does.
it('escapes the provider name in the resources heading', function (): void {
    $html = renderProviderLensBody([
        'title' => 'Leonard McCoy',
        'display_name_full_with_credentials' => ['text' => 'Leonard McCoy, M.D.'],
        'display_name_short_with_prefix' => ['text' => 'Dr. McCoy<svg/onload=alert(1)></svg>'],
        'clinical_resources' => [
            ['title' => 'Knee Replacement Recovery', 'url' => '/health/resources/knee', 'blueprint' => 'article'],
        ],
    ]);

    expect($html)->toContain('provider-resources')
        ->and($html)->not->toContain('<svg/onload')
        ->and($html)->not->toContain('</svg>');
});

// WordPress ends the section with a "more" CTA carrying the provider as an archive
// filter parameter. Our archive accepts only `?type=`, so the CTA is deliberately
// absent until UAMS-Web/uams-statamic-archive#1692 builds the filter — this pins it against a CTA that would land
// the reader on an unfiltered archive of every resource on the site.
it('links no unfiltered resources archive from the section', function (): void {
    $html = renderProviderLensBody(providerIdentityScope() + [
        'clinical_resources' => [
            ['title' => 'Knee Replacement Recovery', 'url' => '/health/resources/knee', 'blueprint' => 'article'],
        ],
    ]);

    expect($html)->toContain('provider-resources')
        ->and($html)->not->toContain('href="/health/resources"')
        ->and($html)->not->toContain('View all resources');
});

// --- The clinical video (UAMS-Web/uams-statamic-archive#1682) ---------------------------------------------

it('renders the clinical video inside the About block', function (): void {
    $html = renderProviderLensBody(providerIdentityScope() + [
        'bio_clinical' => '<p>Clinical biography.</p>',
        'clinical_video_url' => 'https://www.youtube.com/watch?v=enterprise',
    ]);

    expect($html)->toContain('provider-bio')
        ->and($html)->toContain('lite-video')
        ->and($html)->toContain('data-video-url="https://www.youtube.com/watch?v=enterprise"');
});

// Intended behavior, confirmed by the team, NOT an oversight: a video without a bio
// opens no About section and so shows no video. The WordPress source reads the other
// way (`$physician_clinical_bio || !empty($video)` gates the section, and the two
// renders are independent sibling `if` blocks), which is exactly why this is pinned —
// so the gate is not "corrected" to match a reading of the source.
it('renders no About block, and no video, for a provider with a video but no bio', function (): void {
    $html = renderProviderLensBody(providerIdentityScope() + [
        'clinical_video_url' => 'https://www.youtube.com/watch?v=enterprise',
    ]);

    expect($html)->not->toContain('provider-bio')
        ->and($html)->not->toContain('lite-video')
        ->and($html)->not->toContain('enterprise');
});

it('renders the About block without a video when a provider has only a bio', function (): void {
    $html = renderProviderLensBody(providerIdentityScope() + [
        'bio_clinical' => '<p>Clinical biography.</p>',
    ]);

    expect($html)->toContain('provider-bio')
        ->and($html)->not->toContain('lite-video');
});

it('renders no About block for a provider with neither a bio nor a video', function (): void {
    expect(renderProviderLensBody(providerIdentityScope()))->not->toContain('provider-bio');
});

// `sets/video` accepts `video_link_text` and renders NOTHING from it today — the
// two renders below are byte-identical, and this test says so rather than implying
// coverage it does not have. The lens passes the parameter anyway, byte-identically
// to `people/show`, so the two call sites cannot drift if the partial ever starts
// consuming it; that parity is what the assertion pins.
it('passes the clinical video link text through without changing the embed today', function (): void {
    $base = providerIdentityScope() + [
        'bio_clinical' => '<p>Clinical biography.</p>',
        'clinical_video_url' => 'https://www.youtube.com/watch?v=enterprise',
    ];

    $withLinkText = renderProviderLensBody($base + ['clinical_video_link_text' => 'Watch Dr. McCoy']);
    $withoutLinkText = renderProviderLensBody($base);

    expect($withLinkText)->toContain('lite-video')
        ->and($withoutLinkText)->toContain('lite-video')
        // Identical output is the CURRENT contract of `sets/video`. If this ever
        // fails, the partial has started rendering the link text and both this
        // lens and `people/show` need a real assertion on what it renders.
        ->and($withLinkText)->toBe($withoutLinkText);

    // And the lens really does hand it over — the parity with `people/show` that
    // makes the two profiles impossible to drift apart.
    expect(file_get_contents(resource_path('views/people/provider/_profile.antlers.html')))
        ->toContain(':video_link_text="clinical_video_link_text"');
});

// --- Languages (UAMS-Web/uams-statamic-archive#1681) ------------------------------------------------------
//
// `language` is a multiple `select`, so it augments to `{value,label}` rows — a bare
// `{{ language }}` emits `Array`. These fixtures carry that shape, not bare codes.

it('lists a multilingual provider languages, pluralized', function (): void {
    $html = renderProviderLensBody(providerIdentityScope() + [
        'language' => [
            ['value' => 'en', 'label' => 'English'],
            ['value' => 'es', 'label' => 'Spanish'],
        ],
    ]);

    expect($html)->toContain('<dt>Languages</dt>')
        ->and($html)->toContain('<dd class="provider-language">English</dd>')
        ->and($html)->toContain('<dd class="provider-language">Spanish</dd>')
        ->and($html)->not->toContain('Array')
        ->and($html)->not->toContain('sr-only provider-language');
});

// The augmented shape, not a hand-rolled fixture: `language` is a multiple `select`,
// and the real BCP-47 labels carry commas of their own ("Spanish, Castilian"), which
// is why each language gets its own `<dd>` rather than a joined string.
it('renders the real augmented language rows, one per language', function (): void {
    $field = Blueprint::find('collections/people/person')?->field('language');

    if (! $field instanceof Field) {
        $this->markTestSkipped('Requires the people blueprint with a `language` field.');
    }

    $field->setValue(['en', 'es']);
    $augmented = $field->augment();

    if (! $augmented instanceof Field) {
        $this->markTestSkipped('The people blueprint `language` field did not augment.');
    }

    $html = renderProviderLensBody(providerIdentityScope() + ['language' => $augmented->value()]);

    expect($html)->toContain('<dt>Languages</dt>')
        ->and($html)->toContain('<dd class="provider-language">English</dd>')
        ->and($html)->toContain('Castilian</dd>')
        ->and($html)->not->toContain('Array');
});

it('shows a single non-English language as a visible row', function (): void {
    $html = renderProviderLensBody(providerIdentityScope() + [
        'language' => [['value' => 'es', 'label' => 'Spanish']],
    ]);

    expect($html)->toContain('<dt>Language</dt>')
        ->and($html)->toContain('Spanish')
        ->and($html)->not->toContain('sr-only provider-language');
});

it('hides an English-only language row visually but keeps it in the accessibility tree', function (): void {
    // Another fact keeps the block open: `language` alone must NOT open it (below),
    // so without this the `sr-only` assertion would have nothing to find and would
    // fail for the wrong reason.
    $html = renderProviderLensBody(providerIdentityScope() + [
        'accepting_patients_query' => new LabeledValue(true, 'Yes'),
        'language' => [['value' => 'en', 'label' => 'English']],
    ]);

    expect($html)->toContain('<dt class="sr-only">Language</dt>')
        ->and($html)->toContain('sr-only provider-language')
        ->and($html)->toContain('English');
});

it('does not open an At a Glance block for an English-only provider', function (): void {
    // `language` defaults to `['en']` on the blueprint, so gating the block on the
    // bare count would put an "At a Glance" heading over a visually empty list on
    // nearly every provider.
    $html = renderProviderLensBody(providerIdentityScope() + [
        'language' => [['value' => 'en', 'label' => 'English']],
    ]);

    expect($html)->not->toContain('provider-at-a-glance');
});

it('opens an At a Glance block for a provider whose only fact is a non-English language', function (): void {
    $html = renderProviderLensBody(providerIdentityScope() + [
        'language' => [['value' => 'es', 'label' => 'Spanish']],
    ]);

    expect($html)->toContain('provider-at-a-glance')
        ->and($html)->toContain('Spanish');
});

it('leaves no language row for a provider with none set', function (): void {
    $html = renderProviderLensBody(providerIdentityScope() + [
        'accepting_patients_query' => new LabeledValue(true, 'Yes'),
    ]);

    expect($html)->toContain('provider-at-a-glance')
        ->and($html)->not->toContain('provider-language')
        ->and($html)->not->toContain('>Language<');
});

// --- The clinical subtitle (UAMS-Web/uams-statamic-archive#1680) ------------------------------------------
//
// The `<h1>` subtitle is the patient-facing clinical title, not the person's Workday
// `job_title`. The Find-a-Doc provider card already renders `clinical_title`, so a
// card and the profile it links to would otherwise describe the same provider two
// different ways.

// Both title sinks end in `| widont`, which swallows the LAST space into a
// non-breaking one — so a two-word title never appears verbatim in the output and
// these assertions deliberately match up to that space. The fixtures also keep the
// job title and the clinical title lexically disjoint, so neither `toContain` can
// pass on a substring of the other and quietly assert nothing.

it('renders the clinical title, not the job title, as the lens subtitle', function (): void {
    $html = renderProviderLensBody(providerIdentityScope() + [
        'job_title' => 'Chief Medical Officer',
        'clinical_title' => 'Orthopaedic Surgery',
    ]);

    expect($html)->toContain('Orthopaedic')
        ->and($html)->not->toContain('Chief Medical');
});

it('renders no subtitle, not the job title, when a provider has no resolvable specialization', function (): void {
    // WordPress parity (UAMS-Web/uams-statamic-archive#2600, superseding UAMS-Web/uams-statamic-archive#1680's fallback): `single-physicians.php`
    // printed an empty subtitle here, never the employment title.
    // `ClinicalTitleResolver` returns '' rather than null for an unresolvable
    // specialization, so this fixture pins the shape the computed actually produces.
    $html = renderProviderLensBody(providerIdentityScope() + [
        'job_title' => 'Chief Medical Officer',
        'clinical_title' => '',
    ]);

    expect($html)->toContain('data-lens="provider"')
        ->and($html)->not->toContain('Chief Medical')
        ->and($html)->not->toContain('class="subtitle');
});

it('renders no subtitle at all when a provider has neither title', function (): void {
    $html = renderProviderLensBody(providerIdentityScope());

    expect($html)->not->toContain('class="subtitle');
});

it('substitutes the resident title for the specialization, as WordPress does', function (): void {
    // Parity with `class.fad-custom-post.php`, which short-circuits to the literal
    // "Resident Physician" when `physician_resident` is set and never consults the
    // specialization. `resident_query` is a `true_false`, so it augments to a
    // LabeledValue — a raw-bool fixture would pass against an inert template.
    $html = renderProviderLensBody(providerIdentityScope() + [
        'resident_query' => new LabeledValue(true, 'Yes'),
        'job_title' => 'Chief Medical Officer',
        'clinical_title' => 'Orthopaedic Surgery',
    ]);

    expect($html)->toContain('Resident')
        ->and($html)->not->toContain('Orthopaedic')
        ->and($html)->not->toContain('Chief Medical');
});

it('leaves the clinical title in place for a non-resident answering the question', function (): void {
    $html = renderProviderLensBody(providerIdentityScope() + [
        'resident_query' => new LabeledValue(false, 'No'),
        'clinical_title' => 'Orthopaedic Surgery',
    ]);

    expect($html)->toContain('Orthopaedic')
        ->and($html)->not->toContain('Resident Physician');
});

it('strip_tags-escapes the clinical subtitle', function (): void {
    $html = renderProviderLensBody(providerIdentityScope() + [
        'clinical_title' => 'Orthopaedic <script>alert(1)</script>Surgery',
    ]);

    expect($html)->not->toContain('<script>')
        ->and($html)->toContain('Orthopaedic');
});

it('leaves a sibling lens rendering its job title, unchanged', function (): void {
    // The subtitle override is passed IN by the clinical lens; the shared header's
    // default must stay `job_title`, which is the correct register for the academic,
    // institutional, researcher, and general lenses (UAMS-Web/uams-statamic-archive#1667). A `clinical_title` in
    // scope must not leak into them.
    $html = (string) Antlers::parse('{{ partial:people/academic/profile }}', providerIdentityScope() + [
        'job_title' => 'Chief Medical Officer',
        'clinical_title' => 'Orthopaedic Surgery',
    ]);

    expect($html)->toContain('data-lens="academic"')
        ->and($html)->toContain('Chief Medical')
        ->and($html)->not->toContain('Orthopaedic');
});

it('keeps the job title fallback on the academic lens when the clinical title is empty', function (): void {
    // Turning the fallback off is the provider lens's choice alone (UAMS-Web/uams-statamic-archive#2600); a sibling
    // lens with an empty `clinical_title` in scope still renders its `job_title`.
    $html = (string) Antlers::parse('{{ partial:people/academic/profile }}', providerIdentityScope() + [
        'job_title' => 'Chief Medical Officer',
        'clinical_title' => '',
    ]);

    expect($html)->toContain('data-lens="academic"')
        ->and($html)->toContain('Chief Medical');
});

it('omits the scholarly sections that belong to the academic lens', function (): void {
    // The point of a lens is a DIFFERENT presentation per audience: education,
    // training, and faculty appointments are the academic lens's field selection,
    // and must not leak into the patient-facing clinical one (UAMS-Web/uams-statamic-archive#1667).
    $html = renderProviderLensBody(providerIdentityScope() + [
        'education' => [['title' => 'Starfleet Medical Academy']],
        'training' => [['title' => 'Residency, Starfleet Medical']],
        'research_interests' => [['title' => 'Xenobiology']],
    ]);

    expect($html)->not->toContain('Starfleet Medical Academy')
        ->and($html)->not->toContain('Residency, Starfleet Medical')
        ->and($html)->not->toContain('Research Interests');
});
