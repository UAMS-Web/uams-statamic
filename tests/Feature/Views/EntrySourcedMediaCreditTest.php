<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Tests\TestCase;

// Returned to the parallel band on UAMS-Web/uams-statamic-archive#2291. This file was gated to the serial tail on
// "the shared, paratest-non-isolated compiled-view cache" (the UAMS-Web/uams-statamic-archive#1300 / UAMS-Web/uams-statamic-archive#1310 / UAMS-Web/uams-statamic-archive#1447 /
// UAMS-Web/uams-statamic-archive#1452 render-flake class). That directory is per worker under Laravel's `TestViews`
// -- `storage/framework/views/test_<token>`, the one UAMS-Web/uams-statamic-archive#2442 patched for concurrent bands
// -- so the hazard the gate named does not exist. Three parity-matched parallel bands
// with this file in them produced zero failures (macOS, 2026-09-16); runs refute and
// never establish, and tests/README.md carries the audit row.
uses(TestCase::class);

/**
 * Coverage for UAMS-Web/uams-statamic-archive#1532: the entry-level `featured_image_credit_query` control that
 * decides whether a *source entry's* featured-image photo credit is surfaced when
 * another block renders that image (news listings, posts / clinical-resource /
 * knowledge-base card lists). The credit reuses the shared `typography/media_credit`
 * partial from UAMS-Web/uams-statamic-archive#1530 / UAMS-Web/uams-statamic-archive#1531 and appears only when the entry opts in AND the asset
 * carries a `creator`. The self-contained figure/background blocks (UAMS-Web/uams-statamic-archive#1530 / UAMS-Web/uams-statamic-archive#1531)
 * keep their own block-level toggle and are out of scope here; `people` portraits
 * and out-of-scope collections never surface the credit.
 *
 * Rendered hermetically with the array-asset pattern (assets as plain arrays; the
 * Glide/picture markup no-ops on an array but never throws).
 *
 * @command  php artisan test --compact tests/Feature/Views/EntrySourcedMediaCreditTest.php
 */

/**
 * A credit-bearing featured-image asset as a plain array (the hermetic stand-in).
 *
 * @return array<string, mixed>
 */
function entrySourcedCreditedImage(?string $creator = 'Jane Doe', ?string $organization = 'ACME Photography'): array
{
    return array_filter([
        'url' => '/assets/featured.jpg',
        'alt' => 'Featured alt',
        'creator' => $creator,
        'creator_organization' => $organization,
    ], fn ($value): bool => $value !== null);
}

// ---------------------------------------------------------------------------
// The shared card-image partial — components/card_image (the KB card path)
// ---------------------------------------------------------------------------

/**
 * Render the shared card-image partial with an asset and an optional
 * `display_media_credit` toggle (the entry-level control the consumer forwards).
 *
 * @param  array<string, mixed>  $image
 */
function renderCardImage(array $image, mixed $displayMediaCredit = null): string
{
    $data = ['image' => $image];

    if ($displayMediaCredit !== null) {
        $data['display_media_credit'] = $displayMediaCredit;
    }

    return (string) Antlers::parse('{{ partial:page_builder/components/card_image :image="image" }}'
        // Forward the toggle only when the test set one (mirrors an entry with no field → null).
        , $data, true);
}

it('renders the credit under the card image when the entry opts in', function (): void {
    $out = renderCardImage(entrySourcedCreditedImage(), true);

    expect($out)->toContain('<span class="photo-credit">');
    expect($out)->toContain('Jane Doe / ACME Photography');
});

it('renders no credit on the card image when the entry opts out', function (): void {
    $out = renderCardImage(entrySourcedCreditedImage(), false);

    expect($out)->not->toContain('photo-credit');
});

it('renders no card-image credit when the toggle is on but the asset has no creator', function (): void {
    $out = renderCardImage(['url' => '/a.jpg', 'alt' => 'Alt'], true);

    expect($out)->not->toContain('photo-credit');
});

it('renders no card-image credit for an author-chosen image with no toggle (the stacked-block path)', function (): void {
    // No `display_media_credit` at all — a block whose image is author-chosen (e.g. the
    // Stacked block) forwards nothing, so the entry-level control never applies.
    $out = renderCardImage(entrySourcedCreditedImage());

    expect($out)->not->toContain('photo-credit');
});

// ---------------------------------------------------------------------------
// posts/_card — the posts listing / search / archive card
// ---------------------------------------------------------------------------

/**
 * Render the posts listing card for one post with the given fields.
 *
 * @param  array<string, mixed>  $fields
 */
function renderMediaCreditPostCard(array $fields): string
{
    return (string) Antlers::parse('{{ partial:posts/_card }}', array_merge([
        'title' => 'A Post',
        'url' => '/news/a-post',
        'cover_image_query' => true,
    ], $fields), true);
}

it('renders the posts-card credit when the post opts in', function (): void {
    $out = renderMediaCreditPostCard([
        'featured_image_16_9' => entrySourcedCreditedImage(),
        'featured_image_credit_query' => true,
    ]);

    expect($out)->toContain('<span class="photo-credit">');
    expect($out)->toContain('Jane Doe / ACME Photography');
});

it('renders no posts-card credit when the post opts out', function (): void {
    $out = renderMediaCreditPostCard([
        'featured_image_16_9' => entrySourcedCreditedImage(),
        'featured_image_credit_query' => false,
    ]);

    expect($out)->not->toContain('photo-credit');
    // The image itself still renders — only the credit is suppressed.
    expect($out)->toContain('card-img-top');
});

it('renders no posts-card credit when the control field is absent (legacy / unset)', function (): void {
    $out = renderMediaCreditPostCard([
        'featured_image_16_9' => entrySourcedCreditedImage(),
        // featured_image_credit_query intentionally omitted.
    ]);

    expect($out)->not->toContain('photo-credit');
});

// ---------------------------------------------------------------------------
// clinical_resources/_card — the clinical-resource listing / FaD-block card
// ---------------------------------------------------------------------------

/**
 * Render the clinical-resource listing card for one resource.
 *
 * @param  array<string, mixed>  $fields
 */
function renderClinicalResourceCard(array $fields): string
{
    return (string) Antlers::parse('{{ partial:clinical_resources/_card }}', array_merge([
        'title' => 'A Resource',
        'url' => '/clinical-resources/a-resource',
        'blueprint' => 'article',
        'cover_image_query' => true,
    ], $fields), true);
}

it('renders the clinical-resource-card credit when the resource opts in', function (): void {
    $out = renderClinicalResourceCard([
        'featured_image_16_9' => entrySourcedCreditedImage(),
        'featured_image_credit_query' => true,
    ]);

    expect($out)->toContain('<span class="photo-credit">');
});

it('renders no clinical-resource-card credit when the resource opts out', function (): void {
    $out = renderClinicalResourceCard([
        'featured_image_16_9' => entrySourcedCreditedImage(),
        'featured_image_credit_query' => false,
    ]);

    expect($out)->not->toContain('photo-credit');
});

// ---------------------------------------------------------------------------
// Fall-through regression: a bare `creator` inside {{ featured_image_16_9 }}
// would cascade to the entry's byline `creator` (an array) when the asset has
// no credit, fataling `strip_tags`. The scoped `featured_image_16_9:creator`
// idiom must resolve the asset's (absent) credit instead — no credit, no fatal.
// A NON-EMPTY byline is essential: an empty `[]` is falsy and masks the path.
// ---------------------------------------------------------------------------

it('does not fatal or leak the byline when a post has authors but an uncredited featured image', function (): void {
    $out = renderMediaCreditPostCard([
        'featured_image_16_9' => ['url' => '/a.jpg', 'alt' => 'Alt'], // no creator on the asset
        'featured_image_credit_query' => true,
        'creator' => [['name' => 'Dr. Author'], ['name' => 'Dr. Second']], // non-empty entry byline
    ]);

    expect($out)->not->toContain('photo-credit')
        ->and($out)->toContain('card-img-top'); // the image still renders
});

it('does not fatal or leak the byline on a clinical resource with authors but an uncredited featured image', function (): void {
    $out = renderClinicalResourceCard([
        'featured_image_16_9' => ['url' => '/a.jpg', 'alt' => 'Alt'],
        'featured_image_credit_query' => true,
        'creator' => [['name' => 'Dr. Author']],
    ]);

    expect($out)->not->toContain('photo-credit');
});

it('still shows the posts-card credit for a credited featured image even when a byline is present', function (): void {
    $out = renderMediaCreditPostCard([
        'featured_image_16_9' => entrySourcedCreditedImage(),
        'featured_image_credit_query' => true,
        'creator' => [['name' => 'Dr. Author']], // byline present, but the asset credit wins its own scope
    ]);

    expect($out)->toContain('Jane Doe / ACME Photography');
});

// ---------------------------------------------------------------------------
// knowledge_bases/_article_card — the entry-level control forwarded to card_image
// ---------------------------------------------------------------------------

/**
 * Render the KB article card for one article.
 *
 * @param  array<string, mixed>  $fields
 */
function renderKbArticleCard(array $fields): string
{
    return (string) Antlers::parse('{{ partial:knowledge_bases/_article_card }}', array_merge([
        'id' => '1',
        'title' => 'An Article',
        'url' => '/kb/an-article',
        'resolved_topic_image_query' => true,
    ], $fields), true);
}

it('forwards the entry-level control through the KB article card to the card image', function (): void {
    $out = renderKbArticleCard([
        'featured_image_16_9' => entrySourcedCreditedImage(),
        'featured_image_credit_query' => true,
    ]);

    expect($out)->toContain('photo-credit');
});

it('renders no KB article-card credit when the article opts out', function (): void {
    $out = renderKbArticleCard([
        'featured_image_16_9' => entrySourcedCreditedImage(),
        'featured_image_credit_query' => false,
    ]);

    expect($out)->not->toContain('photo-credit');
});

// ---------------------------------------------------------------------------
// Out-of-scope: a credited image on a collection whose card was NOT wired
// ---------------------------------------------------------------------------

it('never surfaces the credit on an out-of-scope collection card (clinical_expertise)', function (): void {
    // clinical_expertise is not one of the four in-scope collections; its card was
    // not wired, so even a credited featured image renders no photo credit.
    $out = (string) Antlers::parse('{{ partial:clinical_expertise/_card }}', [
        'title' => 'An Area of Expertise',
        'url' => '/expertise/an-area',
        'featured_image_16_9' => entrySourcedCreditedImage(),
        // The control field does not exist on this collection, so even if forwarded it is null.
        'featured_image_credit_query' => true,
    ], true);

    expect($out)->not->toContain('photo-credit');
});
