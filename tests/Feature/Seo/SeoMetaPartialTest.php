<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Fields\Field;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Coverage for the bespoke SEO meta head partial (UAMS-Web/uams-statamic-archive#628): meta description +
 * Open Graph / Twitter card, sourced from the single required `excerpt` — the
 * canonical page summary since UAMS-Web/uams-statamic-archive#1378 retired the overlapping `meta_description`
 * field — and the standard cover image `featured_image_16_9`, falling back to
 * the legacy single `featured_image` for collections that keep it, e.g.
 * `clinical_locations` (UAMS-Web/uams-statamic-archive#1553). `people` carry no `excerpt`, so the description
 * falls back to the person's `bio_short`; a Provider Spotlight (UAMS-Web/uams-statamic-archive#1425) to the
 * `spotlight_auto_excerpt` computed. Title/robots/canonical/GTM are emitted
 * elsewhere, so this partial must NOT duplicate them.
 *
 * @command  php artisan test --compact tests/Feature/Seo/SeoMetaPartialTest.php
 */

/**
 * Render the SEO meta partial in isolation with the given Antlers context.
 *
 * @param  array<string, mixed>  $context
 */
function renderSeoMeta(array $context): string
{
    return (string) Antlers::parse(
        (string) file_get_contents(resource_path('views/layout/header/_seo_meta.antlers.html')),
        $context,
        true
    );
}

it('emits the meta description and OG/Twitter description from the required excerpt', function (): void {
    $html = renderSeoMeta([
        'excerpt' => 'A concise page summary.',
        'title' => 'Page',
        'site' => ['name' => 'UAMS'],
        'permalink' => 'https://x.test/p',
    ]);

    expect($html)
        ->toContain('<meta name="description" content="A concise page summary.">')
        ->toContain('<meta property="og:description" content="A concise page summary.">')
        ->toContain('<meta name="twitter:description" content="A concise page summary.">');
});

it('ignores the retired meta_description field — only excerpt drives the description', function (): void {
    // UAMS-Web/uams-statamic-archive#1378 retired `meta_description`; the head no longer reads it, so a
    // context carrying only that field must render no description.
    $html = renderSeoMeta([
        'meta_description' => 'Stale SEO description.',
        'title' => 'Page',
        'site' => ['name' => 'UAMS'],
        'permalink' => 'https://x.test/p',
    ]);

    expect($html)->not->toContain('<meta name="description"');
});

it('falls back to bio_short for people, which carry no excerpt field', function (): void {
    $html = renderSeoMeta([
        'bio_short' => 'Cardiologist and professor of medicine.',
        'title' => 'Dr. Example',
        'site' => ['name' => 'UAMS'],
        'permalink' => 'https://x.test/people/example',
    ]);

    expect($html)->toContain('<meta name="description" content="Cardiologist and professor of medicine.">');
});

it('prefers the excerpt over bio_short when both are present', function (): void {
    $html = renderSeoMeta([
        'excerpt' => 'Canonical summary.',
        'bio_short' => 'Should not win.',
        'title' => 'Page',
        'site' => ['name' => 'UAMS'],
        'permalink' => 'https://x.test/p',
    ]);

    expect($html)->toContain('<meta name="description" content="Canonical summary.">');
});

it('falls back to the provider-spotlight auto excerpt when neither excerpt nor bio_short is set', function (): void {
    $html = renderSeoMeta([
        'spotlight_auto_excerpt' => 'Auto-generated spotlight summary.',
        'title' => 'Spotlight',
        'site' => ['name' => 'UAMS'],
        'permalink' => 'https://x.test/r/spotlight',
    ]);

    expect($html)->toContain('<meta name="description" content="Auto-generated spotlight summary.">');
});

it('omits the description but still emits core Open Graph tags when no summary source exists', function (): void {
    $html = renderSeoMeta([
        'title' => 'Page',
        'site' => ['name' => 'UAMS'],
        'permalink' => 'https://x.test/p',
    ]);

    expect($html)
        ->not->toContain('<meta name="description"')
        ->toContain('<meta property="og:title" content="Page">')
        ->toContain('<meta property="og:type" content="website">')
        ->toContain('<meta property="og:url" content="https://x.test/p">')
        ->toContain('<meta property="og:site_name" content="UAMS">');
    // No <title> here — the layout owns it (no duplication).
    expect($html)->not->toContain('<title>');
});

it('strips tags and encodes entities so the description cannot break out of the attribute', function (): void {
    $html = renderSeoMeta([
        'excerpt' => '<b>Bold</b> & "quoted"',
        'title' => 'Page',
        'site' => ['name' => 'UAMS'],
        'permalink' => '/p',
    ]);

    expect($html)->toContain('Bold &amp; &quot;quoted&quot;');
    expect($html)->not->toContain('<b>Bold');
});

it('omits the social image and downgrades the twitter card when no image source exists', function (): void {
    $html = renderSeoMeta([
        'title' => 'Page',
        'site' => ['name' => 'UAMS'],
        'permalink' => '/p',
    ]);

    expect($html)->not->toContain('property="og:image"');
    expect($html)->not->toContain('name="twitter:image"');
    expect($html)->toContain('<meta name="twitter:card" content="summary">');
});

it('requires the excerpt field on every front-end-view blueprint that carries one', function (string $blueprint): void {
    /** @var Statamic\Fields\Blueprint $found */
    $found = Blueprint::find($blueprint);

    expect($found)->not->toBeNull()
        ->and($found->hasField('excerpt'))->toBeTrue();

    /** @var Field $excerpt */
    $excerpt = $found->field('excerpt');
    expect($excerpt->isRequired())->toBeTrue();
})->with([
    'collections.pages.basic_page',
    'collections.pages.landing_page',
    'collections.knowledge_bases.knowledge_base',
    'collections.knowledge_bases.article',
    'collections.knowledge_bases.topic',
    'collections.clinical_expertise.basic_page',
    'collections.clinical_expertise.landing_page',
    'collections.clinical_expertise.clinical_expertise',
    // posts already required their excerpt before UAMS-Web/uams-statamic-archive#1378 — guard against regression.
    'collections.posts.post',
    'collections.posts.news_release',
    'collections.posts.audio',
    'collections.posts.video',
]);

it('retires the meta_description field from every blueprint that carried it', function (string $blueprint): void {
    /** @var Statamic\Fields\Blueprint $found */
    $found = Blueprint::find($blueprint);

    expect($found)->not->toBeNull()
        ->and($found->hasField('meta_description'))->toBeFalse();
})->with([
    'collections.pages.basic_page',
    'collections.pages.landing_page',
    'collections.posts.post',
    'collections.posts.news_release',
    'collections.posts.audio',
    'collections.posts.video',
    'collections.clinical_expertise.basic_page',
    'collections.clinical_expertise.landing_page',
]);

it('keeps canonical_from_origin on the SEO-meta blueprints after retiring meta_description', function (): void {
    // Retiring `meta_description` must not take the co-located canonical toggle (UAMS-Web/uams-statamic-archive#189) with it.
    /** @var Statamic\Fields\Blueprint $found */
    $found = Blueprint::find('collections.pages.basic_page');

    expect($found)->not->toBeNull()
        ->and($found->hasField('canonical_from_origin'))->toBeTrue();
});

it('emits Open Graph tags in the rendered layout head', function (): void {
    if (! Collection::find('pages')) {
        ContentSubmodule::reportMissing('pages collection');
    }

    $this->withoutVite();
    /** @var TestCase $test */
    $test = $this;
    $html = $test->get('/about')->assertOk()->getContent();

    expect($html)
        ->toContain('property="og:title"')
        ->toContain('property="og:url"')
        ->toContain('property="og:site_name"');
});
