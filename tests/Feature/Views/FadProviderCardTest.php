<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Statamic\Facades\Blink;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Hermetic render coverage for the Find-a-Doc provider card partial
 * (resources/views/page_builder/components/_fad_provider_card.antlers.html, UAMS-Web/uams-statamic-archive#64):
 * the responsive portrait with a 3:4 "no image available" fallback, the
 * patient-facing clinical title, the name, and the "View Profile" CTA.
 *
 * The card is rendered directly with a synthetic `people`-shaped data array — no
 * `{{ collection }}` loop, no content/ submodule, no Stache — so it runs in a bare
 * worktree. Each render appends a unique {{# render-N #}} comment to bust Antlers'
 * per-source-string parsed-document cache, and Blink is flushed between cases, so a
 * prior render's `portrait` / `clinical_title` cannot leak into a negative
 * assertion (see tests/README.md + the reference_antlers_set_partial_testing note).
 *
 * @command php artisan test --compact tests/Feature/Views/FadProviderCardTest.php
 */
beforeEach(fn () => Blink::flush());

/**
 * Render the provider card partial with a synthetic provider data array.
 *
 * @param  array<string, mixed>  $data
 */
function renderFadProviderCard(array $data): string
{
    /** @var int $render */
    static $render = 0;
    $render++;

    return (string) Antlers::parse(
        '{{ partial:page_builder/components/fad_provider_card }}'."\n{{# render-{$render} #}}",
        $data,
        true,
    );
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function fadProviderCardData(array $overrides = []): array
{
    return array_merge([
        'url' => '/find-a-doctor/james-kirk',
        'display_name_full_with_credentials' => ['text' => 'James Kirk, MD'],
        'display_name_short_with_prefix' => ['text' => 'Dr. Kirk'],
        'clinical_title' => 'Cardiology',
    ], $overrides);
}

it('renders the responsive portrait when the provider has one', function (): void {
    $out = renderFadProviderCard(fadProviderCardData([
        'portrait' => ['url' => 'https://img.test/kirk.jpg'],
    ]));

    // A <picture> with a density/breakpoint <source> and the crop-focal <img>.
    expect($out)->toContain('<picture>')
        ->and($out)->toContain('<source')
        ->and($out)->toContain('class="card-img-top"')
        ->and($out)->toContain('width="256"')
        ->and($out)->toContain('height="342"')
        ->and($out)->toContain('srcset=')
        // The alt text carries the provider name.
        ->and($out)->toContain('alt="James Kirk, MD"')
        // The 3:4 fallback is NOT emitted when a portrait exists.
        ->and($out)->not->toContain('viewBox="0 0 245 327"')
        // No empty image source is ever emitted (the UAMS-Web/uams-statamic-archive#888 invalid-markup class).
        ->and($out)->not->toContain('src=""')
        ->and($out)->not->toContain('srcset=""');
});

it('renders the 3:4 no-image-available fallback when the provider has no portrait', function (): void {
    $out = renderFadProviderCard(fadProviderCardData());

    // The grey no-image-available_3-4 SVG placeholder (identified by its viewBox
    // and fill) stands in for the missing portrait.
    expect($out)->toContain('viewBox="0 0 245 327"')
        ->and($out)->toContain('#dcdadb')
        ->and($out)->toContain('class="card-img-top"')
        // No <picture>/<img> and no empty src when there is no portrait.
        ->and($out)->not->toContain('<picture>')
        ->and($out)->not->toContain('<img')
        ->and($out)->not->toContain('src=""');
});

it('renders the name, clinical title, and View Profile CTA', function (): void {
    $out = renderFadProviderCard(fadProviderCardData([
        'portrait' => ['url' => 'https://img.test/kirk.jpg'],
    ]));

    expect($out)->toContain('<span class="name">James Kirk, MD</span>')
        ->and($out)->toContain('<span class="subtitle">Cardiology</span>')
        ->and($out)->toContain('href="/find-a-doctor/james-kirk"')
        ->and($out)->toContain('>View Profile</a>')
        ->and($out)->toContain('aria-label="View profile for Dr. Kirk"');
});

it('omits the subtitle entirely when the provider has no clinical title', function (): void {
    $out = renderFadProviderCard(fadProviderCardData([
        'clinical_title' => '',
    ]));

    // No empty <span class="subtitle"></span> when the title is blank.
    expect($out)->toContain('<span class="name">James Kirk, MD</span>')
        ->and($out)->not->toContain('class="subtitle"');
});

it('escapes editor- and taxonomy-controlled text in text and attribute contexts (no raw HTML sink)', function (): void {
    $out = renderFadProviderCard(fadProviderCardData([
        'display_name_full_with_credentials' => ['text' => '<script>alert(1)</script>'],
        'display_name_short_with_prefix' => ['text' => '"><script>alert(2)</script>'],
        'clinical_title' => '<b>onmouseover</b>',
    ]));

    // Text-node sinks (name, subtitle) are encoded, and the aria-label attribute
    // cannot be broken out of (strip_tags + sanitize). Antlers is raw by default.
    expect($out)->not->toContain('<script>alert(1)</script>')
        ->and($out)->not->toContain('<b>onmouseover</b>')
        ->and($out)->toContain('&lt;script&gt;')
        ->and($out)->toContain('&lt;b&gt;onmouseover&lt;/b&gt;')
        ->and($out)->not->toContain('"><script>alert(2)')
        ->and($out)->toContain('&quot;&gt;alert(2)');
});

it('emits the facet data-* contract on the card root when fad_facet is set (#1242)', function (): void {
    $out = renderFadProviderCard(fadProviderCardData([
        'fad_facet' => true,
        'expertise' => [['slug' => 'cardiology'], ['slug' => 'pediatric-oncology']],
        'clinical_specializations' => [['slug' => 'general-surgery']],
        'regions' => [['slug' => 'central-arkansas'], ['slug' => 'northwest-arkansas']],
    ]));

    // The facet anchor (`data-fad-card`) and the three space-delimited slug lists the
    // fadFilter engine reads.
    expect($out)->toContain('data-fad-card')
        ->and($out)->toContain('data-expertise="cardiology pediatric-oncology"')
        ->and($out)->toContain('data-specialty="general-surgery"')
        ->and($out)->toContain('data-region="central-arkansas northwest-arkansas"');
});

it('omits the facet contract entirely when fad_facet is unset (the static-list card)', function (): void {
    // A filtering-disabled block renders cards without fad_facet: no facet markup ships,
    // so the disabled state is byte-for-byte the plain card.
    $out = renderFadProviderCard(fadProviderCardData([
        'expertise' => [['slug' => 'cardiology']],
        'clinical_specializations' => [['slug' => 'general-surgery']],
        'regions' => [['slug' => 'central-arkansas']],
    ]));

    expect($out)->not->toContain('data-fad-card')
        ->and($out)->not->toContain('data-expertise')
        ->and($out)->not->toContain('data-specialty')
        ->and($out)->not->toContain('data-region');
});

it('emits empty facet attributes for a provider missing a facet, keeping the card intact', function (): void {
    // A provider with no clinical specialization and no region (e.g. no linked location)
    // still gets well-formed empty attributes — the engine treats an empty set as
    // "matches no active filter for that facet", never a crash.
    $out = renderFadProviderCard(fadProviderCardData([
        'fad_facet' => true,
        'expertise' => [['slug' => 'cardiology']],
    ]));

    expect($out)->toContain('data-fad-card')
        ->and($out)->toContain('data-expertise="cardiology"')
        ->and($out)->toContain('data-specialty=""')
        ->and($out)->toContain('data-region=""');
});

it('emits the wider directory facet contract when fad_directory is also set (#570)', function (): void {
    // The /providers directory passes fad_facet + fad_directory, so the card carries the
    // full WP-parity facet set the `fadProviderDirectory` engine reads, plus the A–Z,
    // sort, and name-search keys.
    $out = renderFadProviderCard(fadProviderCardData([
        'fad_facet' => true,
        'fad_directory' => true,
        'expertise' => [['slug' => 'cardiology']],
        'clinical_specializations' => [['slug' => 'general-surgery']],
        'regions' => [['slug' => 'central-arkansas']],
        'conditions' => [['slug' => 'asthma'], ['slug' => 'diabetes']],
        'treatments' => [['slug' => 'bronchoscopy']],
        'patient_types' => [['slug' => 'adults']],
        'clinical_locations' => [['slug' => 'main-campus']],
        'gender' => 'Female',
        // The `language` select augments to `{value,label}` rows, so the card plucks
        // the code — feeding that shape here catches a bare `join` (which emits `Array`).
        'language' => [['value' => 'en', 'label' => 'English'], ['value' => 'es', 'label' => 'Spanish, Castilian']],
        'primary_care_query' => true,
        'person_name' => [
            'last_name' => ['text' => 'McCoy'],
            'first_name' => ['text' => 'Leonard'],
        ],
        'display_name_full_with_credentials' => ['text' => 'Leonard McCoy, MD'],
    ]));

    expect($out)->toContain('data-conditions="asthma diabetes"')
        ->and($out)->toContain('data-treatments="bronchoscopy"')
        ->and($out)->toContain('data-patient_types="adults"')
        ->and($out)->toContain('data-location="main-campus"')
        // gender lower-cased, primary care the boolean "yes" token, languages joined.
        ->and($out)->toContain('data-gender="female"')
        ->and($out)->toContain('data-language="en es"')
        ->and($out)->toContain('data-primary_care="yes"')
        // A–Z initial (upper), the last-then-first sort key, and the searchable name.
        ->and($out)->toContain('data-alpha="M"')
        ->and($out)->toContain('data-sort-name="mccoy leonard"')
        ->and($out)->toContain('data-name="leonard mccoy, md"');
});

it('emits an empty primary-care token when the provider is not primary care (#570)', function (): void {
    $out = renderFadProviderCard(fadProviderCardData([
        'fad_facet' => true,
        'fad_directory' => true,
        'primary_care_query' => false,
    ]));

    // The engine matches `primary_care="yes"`; a non-primary-care provider carries "".
    expect($out)->toContain('data-primary_care=""');
});

it('omits the wider directory facet contract when fad_directory is unset (the block card, #570)', function (): void {
    // The Providers block passes fad_facet ONLY, so the card must stay the UAMS-Web/uams-statamic-archive#1242
    // three-facet contract — none of the directory-only attributes may leak in.
    $out = renderFadProviderCard(fadProviderCardData([
        'fad_facet' => true,
        'expertise' => [['slug' => 'cardiology']],
        'conditions' => [['slug' => 'asthma']],
        'gender' => 'Female',
        'primary_care_query' => true,
    ]));

    expect($out)->toContain('data-expertise="cardiology"')
        ->and($out)->not->toContain('data-conditions')
        ->and($out)->not->toContain('data-treatments')
        ->and($out)->not->toContain('data-patient_types')
        ->and($out)->not->toContain('data-location')
        ->and($out)->not->toContain('data-gender')
        ->and($out)->not->toContain('data-language')
        ->and($out)->not->toContain('data-primary_care')
        ->and($out)->not->toContain('data-alpha')
        ->and($out)->not->toContain('data-sort-name')
        ->and($out)->not->toContain('data-name=');
});

it('escapes malicious name text in the directory name/sort/alpha attributes (no attribute breakout, #570)', function (): void {
    // data-name / data-sort-name / data-alpha carry editor-entered name text — Antlers
    // is raw by default, so they must be strip_tags + sanitize'd like the visible name.
    $out = renderFadProviderCard(fadProviderCardData([
        'fad_facet' => true,
        'fad_directory' => true,
        'person_name' => [
            'last_name' => ['text' => '"><img src=x onerror=alert(1)>'],
            'first_name' => ['text' => 'Evil'],
        ],
        'display_name_full_with_credentials' => ['text' => '"><script>alert(2)</script>'],
    ]));

    expect($out)->not->toContain('<img src=x onerror=alert(1)>')
        ->and($out)->not->toContain('"><script>alert(2)</script>')
        ->and($out)->not->toContain('"><img');
});
