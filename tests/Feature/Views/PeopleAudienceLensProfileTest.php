<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Render coverage for the per-audience people lens bodies (UAMS-Web/uams-statamic-archive#1667): the academic,
 * institutional, and researcher lenses each render their OWN curated field selection
 * (`people/<lens>/profile`) rather than all delegating to one identical body. These
 * assert the divergence — each lens shows fields the others deliberately omit — and
 * that every editor-controlled sink stays XSS-safe (Antlers is raw by default).
 *
 * Rendered as partials with a synthetic person scope (no resolvable portrait → the
 * glide/asset path is skipped), so they are hermetic and CI-safe, mirroring
 * ProviderLensProfileTest.
 *
 * @command php artisan test --compact tests/Feature/Views/PeopleAudienceLensProfileTest.php
 */

/**
 * Render a lens `_profile` body with the given Antlers scope.
 *
 * @param  array<string, mixed>  $scope
 */
function renderLensProfile(string $lens, array $scope): string
{
    return (string) Antlers::parse('{{ partial:people/'.$lens.'/profile }}', $scope);
}

/**
 * A person carrying every audience-divergent field, so each lens can be shown to pick its own.
 *
 * @return array<string, mixed>
 */
function everyLensField(): array
{
    return [
        'title' => 'Jane McCoy',
        'display_name_full_with_credentials' => ['text' => 'Jane McCoy, Ph.D.'],
        'display_name_short_with_prefix' => ['text' => 'Dr. McCoy'],
        'academic_profile_url' => 'https://academic.uams.edu/jane',
        'research_profile_url' => 'https://tri.uams.edu/jane',
        'email' => 'jane.mccoy@uams.edu',
        'telephone' => '5015551212',
        'location' => 'Sickbay, Deck 7',
        'orcid_id' => '0000-0002-1825-0097',
        // `research_interests` is a replicator: a `controlled_vocabulary` row carries a
        // `subject_matter` terms field, a `free_text` row carries a `text` string.
        'research_interests' => [
            ['type' => 'controlled_vocabulary', 'subject_matter' => [['title' => 'Xenobiology']]],
            ['type' => 'free_text', 'text' => 'Warp field theory'],
        ],
        'bio' => '<p>General bio.</p>',
        'bio_academic' => '<p>Academic bio.</p>',
        'bio_institutional' => '<p>Institutional bio.</p>',
        'bio_research' => '<p>Research bio.</p>',
    ];
}

it('academic lens curates academic + research profiles and the academic bio, omitting contact', function (): void {
    $html = renderLensProfile('academic', everyLensField());

    // What the academic audience gets.
    expect($html)->toContain('data-lens="academic"')
        ->toContain('UAMS Academic Profile')
        ->toContain('https://academic.uams.edu/jane')
        ->toContain('UAMS Research Profile')
        ->toContain('Academic bio.')
        // ...and NOT the institutional contact block (a curated omission, not a coincidence)...
        ->and($html)->not->toContain('mailto:jane.mccoy@uams.edu')
        ->and($html)->not->toContain('>Contact<')
        // ...nor the researcher lens's research identifiers / interests.
        ->and($html)->not->toContain('ORCID iD')
        ->and($html)->not->toContain('Research Interests');
});

it('institutional lens curates the contact overview and institutional bio, omitting scholarship', function (): void {
    $html = renderLensProfile('institutional', everyLensField());

    // What the staff-directory audience gets.
    expect($html)->toContain('data-lens="institutional"')
        ->toContain('mailto:jane.mccoy@uams.edu')
        ->toContain('Contact')
        ->toContain('Institutional bio.')
        // ...and NOT the academic/research external profiles (curated omission).
        ->and($html)->not->toContain('UAMS Academic Profile')
        ->and($html)->not->toContain('UAMS Research Profile');
});

it('researcher lens curates the research profile, identifiers, and research bio', function (): void {
    $html = renderLensProfile('researcher', everyLensField());

    expect($html)->toContain('data-lens="researcher"')
        ->toContain('UAMS Research Profile')
        ->toContain('https://tri.uams.edu/jane')
        ->toContain('ORCID iD')
        ->toContain('0000-0002-1825-0097')
        // The research-interests block reads the real replicator set fields, so the
        // interest text renders (not the person's name via a bad title/value/label lookup).
        ->toContain('Research Interests')
        ->toContain('Xenobiology')
        ->toContain('Warp field theory')
        ->toContain('Research bio.')
        // The researcher lens is neither a contact card nor the academic lens.
        ->and($html)->not->toContain('>Contact<')
        ->and($html)->not->toContain('UAMS Academic Profile');
});

it('renders research interests from the replicator set fields, not a flat title lookup', function (): void {
    // Pins the bug: `research_interests` is a replicator, so reading `title`/`value`/`label`
    // off a row finds nothing and cascades to the parent person's `title` (their name),
    // rendering the person's name once per interest instead of the interest text.
    $html = renderLensProfile('researcher', [
        'title' => 'Jane McCoy',
        'display_name_full_with_credentials' => ['text' => 'Jane McCoy, Ph.D.'],
        'display_name_short_with_prefix' => ['text' => 'Dr. McCoy'],
        'research_interests' => [
            ['type' => 'controlled_vocabulary', 'subject_matter' => [['title' => 'Oncology']]],
            ['type' => 'free_text', 'text' => 'Hip and Knee Surgery'],
        ],
    ]);

    // The real interest text renders in the list (a controlled_vocabulary term + a free_text row)…
    expect($html)->toContain('<li>Oncology</li>')
        ->toContain('<li>Hip and Knee Surgery</li>')
        // …and the person's name is NOT rendered as a research interest (the bug's symptom).
        ->and($html)->not->toContain('<li>Jane McCoy</li>');
});

it('academic and institutional lenses render genuinely different field selections', function (): void {
    $academic = renderLensProfile('academic', everyLensField());
    $institutional = renderLensProfile('institutional', everyLensField());

    // The academic body shows the academic profile the institutional body hides…
    expect($academic)->toContain('UAMS Academic Profile')
        ->and($institutional)->not->toContain('UAMS Academic Profile')
        // …and the institutional body shows the contact the academic body hides.
        ->and($institutional)->toContain('mailto:jane.mccoy@uams.edu')
        ->and($academic)->not->toContain('mailto:jane.mccoy@uams.edu');
});

it('academic lens renders the scholarly professional background when present', function (): void {
    $html = renderLensProfile('academic', [
        ...everyLensField(),
        'education' => [
            ['activity' => ['full_name' => 'Starfleet Academy']],
        ],
    ]);

    // The block-header heading applies `| widont` (last space → `&nbsp;`), so match the
    // sub-section heading + the rendered education item rather than the two-word block title.
    expect($html)->toContain('Education')
        ->toContain('Starfleet');
});

it('sanitizes the academic lens academic-profile URL href against XSS', function (): void {
    // Attribute-breakout payload is HTML-encoded (no live tag escapes the href); the
    // UAMS-Web/uams-statamic-archive#1500 sanitize_url pattern, since Antlers is raw by default.
    $breakout = renderLensProfile('academic', [
        ...everyLensField(),
        'academic_profile_url' => '"><svg onload=alert(document.cookie)>',
    ]);

    expect($breakout)->not->toContain('<svg onload')
        ->and($breakout)->not->toContain('"><svg')
        ->and($breakout)->toContain('&lt;svg');

    // A `javascript:` scheme collapses to an inert `#`.
    $scheme = renderLensProfile('academic', [
        ...everyLensField(),
        'academic_profile_url' => 'javascript:alert(1)',
    ]);

    expect($scheme)->not->toContain('javascript:alert')
        ->and($scheme)->toContain('href="#"');
});

it('sanitizes the institutional lens contact email href against attribute breakout', function (): void {
    $html = renderLensProfile('institutional', [
        ...everyLensField(),
        'email' => 'a@b.com"><svg onload=alert(1)>',
    ]);

    // The `"` that would break out of the mailto href is HTML-encoded, so no live tag escapes.
    expect($html)->not->toContain('<svg onload')
        ->and($html)->not->toContain('"><svg');
});

it('sanitizes the researcher lens research-profile URL href against XSS', function (): void {
    $html = renderLensProfile('researcher', [
        ...everyLensField(),
        'research_profile_url' => '"><svg onload=alert(document.cookie)>',
    ]);

    expect($html)->not->toContain('<svg onload')
        ->and($html)->not->toContain('"><svg')
        ->and($html)->toContain('&lt;svg');
});
