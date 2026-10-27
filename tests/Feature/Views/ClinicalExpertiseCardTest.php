<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Hermetic coverage for the Areas of Expertise index card
 * (resources/views/clinical_expertise/_card.antlers.html, UAMS-Web/uams-statamic-archive#1467): the WP-archive
 * parity upgrade of the AoE index card — a 16:9 featured image (with a no-image
 * fallback), a "Part of {parent}" subtitle for a child Area, and a
 * "View Area of Expertise" CTA link, with all editor/import text escaped.
 *
 * The card renders from entry scope, so it is exercised hermetically with a
 * synthetic array (no Stache, no submodule). The end-to-end resolution of the
 * `parent_expertise_name` / `parent_expertise_url` structure-tree computeds is
 * covered in ClinicalExpertiseParentComputedTest.
 *
 * @command php artisan test --compact tests/Feature/Views/ClinicalExpertiseCardTest.php
 */

/**
 * Render the Area of Expertise card partial with the given entry scope.
 *
 * @param  array<string, mixed>  $area
 */
function renderExpertiseCard(array $area): string
{
    return (string) Antlers::parse('{{ partial:clinical_expertise/card }}', $area);
}

it('renders an Area of Expertise card with a 16:9 image, title, description, and CTA', function (): void {
    // No `cover_image_query`: the AoE blueprint has no such toggle, so the image is
    // shown whenever a `featured_image_16_9` is set — the real-entry behavior.
    $html = renderExpertiseCard([
        'title' => 'Cardiology',
        'url' => '/health/expertise/cardiology',
        'featured_image_16_9' => [['url' => '/img/cardiology.jpg', 'alt' => 'A beating heart']],
        'cover_image_alt_text_override' => null,
        'alt' => 'A beating heart',
        'excerpt' => 'Heart and vascular care.',
    ]);

    expect($html)
        ->toContain('Cardiology')
        ->toContain('Heart and vascular care.')
        ->toContain('<img src="/img/cardiology.jpg"')
        ->toContain('alt="A beating heart"')
        ->toContain('href="/health/expertise/cardiology"')
        ->toContain('View Area of Expertise');
    // Image present → no fallback SVG.
    expect($html)->not->toContain('viewBox="0 0 1024 576"');
});

it('escapes editor/import-controlled image alt text (no attribute-context XSS)', function (): void {
    $html = renderExpertiseCard([
        'title' => 'Cardiology',
        'url' => '/x',
        'featured_image_16_9' => [['url' => '/img/x.jpg', 'alt' => '"><img src=x onerror=alert(1)>']],
    ]);

    expect($html)
        ->not->toContain('"><img src=x onerror=alert(1)>')
        ->toContain('&lt;img src=x onerror=alert(1)&gt;');
});

it('prefers the editor alt override and escapes it', function (): void {
    $html = renderExpertiseCard([
        'title' => 'Cardiology',
        'url' => '/x',
        'featured_image_16_9' => [['url' => '/img/x.jpg', 'alt' => 'asset alt']],
        'cover_image_alt_text_override' => '" onmouseover=alert(1) x="',
    ]);

    expect($html)->not->toContain('" onmouseover=alert(1) x="');
    expect($html)->not->toContain('asset alt');
});

it('renders the 16:9 no-image fallback when there is no featured image', function (): void {
    $html = renderExpertiseCard([
        'title' => 'Neurology',
        'url' => '/health/expertise/neurology',
        'featured_image_16_9' => [],
        'excerpt' => 'Brain and nervous-system care.',
    ]);

    expect($html)
        // The shared 16:9 "no image available" fallback SVG.
        ->toContain('viewBox="0 0 1024 576"')
        ->toContain('aria-hidden="true"');
    expect($html)->not->toContain('<img');
});

it('renders a "Part of {parent}" subtitle linking to the parent Area', function (): void {
    $html = renderExpertiseCard([
        'title' => 'Interventional Cardiology',
        'url' => '/health/expertise/cardiology/interventional',
        'parent_expertise_name' => 'Cardiology',
        'parent_expertise_url' => '/health/expertise/cardiology',
    ]);

    expect($html)
        ->toContain('card-parent')
        ->toContain('Part of')
        ->toContain('<a href="/health/expertise/cardiology">Cardiology</a>');
});

it('renders the parent name without a link when the parent URL is unavailable', function (): void {
    $html = renderExpertiseCard([
        'title' => 'Interventional Cardiology',
        'url' => '/x',
        'parent_expertise_name' => 'Cardiology',
        'parent_expertise_url' => null,
    ]);

    expect($html)
        ->toContain('Part of')
        ->toContain('Cardiology');
    expect($html)->not->toContain('<a href="">');
});

it('omits the "Part of" subtitle for a top-level Area with no parent', function (): void {
    $html = renderExpertiseCard([
        'title' => 'Cardiology',
        'url' => '/health/expertise/cardiology',
    ]);

    expect($html)->not->toContain('card-parent');
});

it('escapes editor-controlled title, description, and parent name (no raw HTML sink)', function (): void {
    $html = renderExpertiseCard([
        'title' => '<script>alert("t")</script>',
        'url' => '/x',
        'excerpt' => '<script>alert("e")</script>',
        'parent_expertise_name' => '<script>alert("p")</script>',
        'parent_expertise_url' => '/x',
    ]);

    expect($html)->not->toContain('<script>alert("t")</script>');
    expect($html)->not->toContain('<script>alert("e")</script>');
    expect($html)->not->toContain('<script>alert("p")</script>');
    expect($html)->toContain('&lt;script&gt;');
});
