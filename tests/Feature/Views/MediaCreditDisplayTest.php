<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Tests\TestCase;

// Returned to the parallel band on UAMS-Web/uams-statamic-archive#2503. This file was gated by UAMS-Web/uams-statamic-archive#1571 because it intermittently
// dropped the caption span under `--parallel` while passing 19/19 in isolation. The gate
// attributed that to "the shared, paratest-non-isolated compiled-view cache", which was
// already per worker under Laravel's `TestViews` when the flake was observed, so the cause was
// never established; the UAMS-Web/uams-statamic-archive#1300 Stache warm-up race UAMS-Web/uams-statamic-archive#1590 closed is the likeliest closer, and
// that remains a hypothesis. As an observed flake it came off only on the README's ten-run
// bar: ten consecutive `--parallel --exclude-group=serial` bands on a no-snapshot checkout
// with this file ungated produced zero failures (macOS, 2026-09-25). If it recurs, restore the
// gate with the observed failure as its reason. tests/README.md carries the audit row.
uses(TestCase::class);

/**
 * Coverage for UAMS-Web/uams-statamic-archive#1530: surfacing an asset's media credit (`creator` /
 * `creator_organization`) alongside the caption in the figure-based image blocks —
 * the shared `typography/media_credit` partial, the Image / Portrait / Portrait-wide
 * Bard sets, the Gallery block's enlarged (modal) figcaption, and the extraction of
 * the credit markup out of the page-header cover-image partial (which now consumes
 * the shared partial). The per-block `display_media_credit` toggle defaults to shown
 * and suppresses only the credit, never the caption.
 *
 * Antlers template partials — no mutable PHP surface. Rendered hermetically with the
 * `CoverImagePartialTest` array-asset pattern (assets passed as plain arrays; the
 * Glide-backed picture partial no-ops on an array but never throws).
 *
 * @command  php artisan test --compact tests/Feature/Views/MediaCreditDisplayTest.php
 */

/** Collapse runs of whitespace so structural assertions ignore Antlers indentation. */
function normalizeMediaCreditHtml(string $html): string
{
    return trim((string) preg_replace('/\s+/', ' ', $html));
}

/**
 * The Gallery block's modal `<figcaption>` block, extracted verbatim from the real
 * partial (so a regression in the file is caught) with its nested `{{ if }}`s intact.
 * Bounded by the unique opening guard and the first `{{ /if }}` after `</figcaption>`.
 */
function galleryModalFigcaptionFragment(): string
{
    $source = (string) file_get_contents(
        (string) realpath(__DIR__.'/../../../resources/views/page_builder/_uamsweb_block_gallery.antlers.html')
    );

    $start = strpos($source, '{{ if caption || ( creator');
    expect($start)->not->toBeFalse('gallery modal figcaption guard not found in the partial');

    $figClose = strpos($source, '</figcaption>', (int) $start);
    expect($figClose)->not->toBeFalse('gallery modal </figcaption> not found after the guard');

    $end = strpos($source, '{{ /if }}', (int) $figClose);
    expect($end)->not->toBeFalse('gallery modal figcaption {{ /if }} not found');

    return substr($source, (int) $start, (int) $end - (int) $start + strlen('{{ /if }}'));
}

/**
 * A credit-bearing image asset as a plain array (the hermetic asset stand-in).
 *
 * @return array<string, mixed>
 */
function creditedImageAsset(string $creator = 'Jane Doe', ?string $organization = 'ACME Photography'): array
{
    return array_filter([
        'url' => '/assets/example.jpg',
        'alt' => 'Example alt',
        'creator' => $creator,
        'creator_organization' => $organization,
    ], fn ($value): bool => $value !== null);
}

// ---------------------------------------------------------------------------
// The shared partial — typography/media_credit
// ---------------------------------------------------------------------------

it('renders the creator and organization as a photo-credit span with an sr-only label', function (): void {
    $out = (string) Antlers::parse('{{ partial:typography/media_credit }}', [
        'creator' => 'Jane Doe',
        'creator_organization' => 'ACME Photography',
    ], true);

    expect($out)->toContain('<span class="photo-credit">');
    expect($out)->toContain('<span class="sr-only">Credit: </span>');
    expect($out)->toContain('Jane Doe / ACME Photography');
});

it('renders only the creator when no organization is present', function (): void {
    $out = normalizeMediaCreditHtml((string) Antlers::parse('{{ partial:typography/media_credit }}', [
        'creator' => 'Jane Doe',
    ], true));

    expect($out)->toBe('<span class="photo-credit"><span class="sr-only">Credit: </span>Jane Doe</span>');
});

it('renders nothing without a creator', function (): void {
    $out = (string) Antlers::parse('{{ partial:typography/media_credit }}', [
        'creator_organization' => 'ACME Photography',
    ], true);

    expect(trim($out))->toBe('');
});

it('strips HTML tags from the creator and organization so no raw markup reaches the DOM', function (): void {
    $out = (string) Antlers::parse('{{ partial:typography/media_credit }}', [
        'creator' => '<script>alert(1)</script>Jane',
        'creator_organization' => '<b>ACME</b>',
    ], true);

    expect($out)->not->toContain('<script>');
    expect($out)->not->toContain('<b>');
    expect($out)->toContain('alert(1)Jane / ACME');
});

it('appends an optional modifier class to the photo-credit span', function (): void {
    $out = (string) Antlers::parse('{{ partial:typography/media_credit class="media-credit-overlay__text" }}', [
        'creator' => 'Jane Doe',
    ], true);

    expect($out)->toContain('class="photo-credit media-credit-overlay__text"');
});

// ---------------------------------------------------------------------------
// The Image Bard set — sets/image (default-on toggle)
// ---------------------------------------------------------------------------

/**
 * Render the Image set with an asset + optional caption/toggle.
 *
 * @param  array<string, mixed>|null  $asset
 */
function renderImageSet(?array $asset, ?string $caption = null, mixed $displayMediaCredit = null): string
{
    $data = ['align' => 'aligncenter', 'crop' => '', 'caption' => $caption, 'image' => $asset];

    if ($displayMediaCredit !== null) {
        $data['display_media_credit'] = $displayMediaCredit;
    }

    return normalizeMediaCreditHtml((string) Antlers::parse('{{ partial:sets/image }}', $data, true));
}

it('renders the credit beside the caption in the Image set', function (): void {
    $out = renderImageSet(creditedImageAsset(), 'A descriptive caption');

    expect($out)->toContain('<span class="caption-text">A descriptive&nbsp;caption</span>');
    expect($out)->toContain('<span class="photo-credit"><span class="sr-only">Credit: </span>Jane Doe / ACME Photography</span>');
});

it('renders the credit in the Image set even when there is no caption', function (): void {
    $out = renderImageSet(creditedImageAsset(), null);

    expect($out)->toContain('<figcaption>');
    expect($out)->toContain('class="photo-credit"');
    expect($out)->not->toContain('caption-text');
});

it('renders the caption without a credit when the asset has no creator', function (): void {
    $out = renderImageSet(['url' => '/a.jpg', 'alt' => 'Alt'], 'Just a caption');

    expect($out)->toContain('<span class="caption-text">Just a&nbsp;caption</span>');
    expect($out)->not->toContain('photo-credit');
});

it('emits no figcaption when the Image set has neither a caption nor a credit', function (): void {
    $out = renderImageSet(['url' => '/a.jpg', 'alt' => 'Alt'], null);

    expect($out)->not->toContain('<figcaption>');
    expect($out)->not->toContain('photo-credit');
});

it('suppresses the Image-set credit when the toggle is explicitly off, keeping the caption', function (): void {
    $out = renderImageSet(creditedImageAsset(), 'Kept caption', false);

    expect($out)->toContain('<span class="caption-text">Kept&nbsp;caption</span>');
    expect($out)->not->toContain('photo-credit');
});

it('shows the Image-set credit for legacy content where the toggle is unset (default-on)', function (): void {
    // No `display_media_credit` key at all — the augmentation default / legacy path.
    $out = renderImageSet(creditedImageAsset(), 'Caption');

    expect($out)->toContain('photo-credit');
});

// ---------------------------------------------------------------------------
// Portrait / Portrait-wide sets inherit via sets/image
// ---------------------------------------------------------------------------

it('inherits the credit rendering in the Portrait and Portrait-wide sets via sets/image', function (string $partial): void {
    $out = normalizeMediaCreditHtml((string) Antlers::parse("{{ partial:$partial }}", [
        'align' => 'aligncenter',
        'image' => creditedImageAsset(),
        'caption' => 'Portrait caption',
    ], true));

    expect($out)->toContain('<span class="photo-credit"><span class="sr-only">Credit: </span>Jane Doe / ACME Photography</span>');
})->with([
    'portrait' => ['sets/portrait'],
    'portrait_wide' => ['sets/portrait_wide'],
]);

it('lets the Portrait and Portrait-wide sets suppress the credit via their own toggle', function (string $partial, string $fieldset): void {
    // The suppress toggle exists on the portrait fieldsets (default shown, matching Image) …
    $config = (string) file_get_contents(resource_path("fieldsets/$fieldset.yaml"));
    expect($config)->toContain('handle: display_media_credit');
    expect($config)->toMatch('/handle: display_media_credit.*?default: true/s');

    // … and rendering with it off suppresses the credit while the caption stays.
    $out = normalizeMediaCreditHtml((string) Antlers::parse("{{ partial:$partial }}", [
        'align' => 'aligncenter',
        'image' => creditedImageAsset(),
        'caption' => 'Portrait caption',
        'display_media_credit' => false,
    ], true));

    expect($out)->toContain('caption-text');
    expect($out)->not->toContain('photo-credit');
})->with([
    'portrait' => ['sets/portrait', 'uamsweb_set_portrait'],
    'portrait_wide' => ['sets/portrait_wide', 'uamsweb_set_portrait_wide'],
]);

// ---------------------------------------------------------------------------
// Gallery block — the modal figcaption (default-on toggle)
// ---------------------------------------------------------------------------

it('renders the per-image credit in the Gallery block modal figcaption', function (): void {
    $out = normalizeMediaCreditHtml((string) Antlers::parse(galleryModalFigcaptionFragment(), [
        'caption' => 'Modal caption',
        'creator' => 'Jane Doe',
        'creator_organization' => 'ACME Photography',
    ], true));

    expect($out)->toContain('<span class="caption-text">Modal&nbsp;caption</span>');
    expect($out)->toContain('<span class="photo-credit"><span class="sr-only">Credit: </span>Jane Doe / ACME Photography</span>');
});

it('suppresses the Gallery credit when the block toggle is off', function (): void {
    $out = normalizeMediaCreditHtml((string) Antlers::parse(galleryModalFigcaptionFragment(), [
        'caption' => 'Modal caption',
        'creator' => 'Jane Doe',
        'display_media_credit' => false,
    ], true));

    expect($out)->toContain('caption-text');
    expect($out)->not->toContain('photo-credit');
});

it('emits no Gallery figcaption for an uncredited, uncaptioned image', function (): void {
    $out = normalizeMediaCreditHtml((string) Antlers::parse(galleryModalFigcaptionFragment(), [], true));

    expect($out)->not->toContain('<figcaption>');
    expect($out)->not->toContain('photo-credit');
});

// ---------------------------------------------------------------------------
// Cover-image regression — now consumes the shared partial
// ---------------------------------------------------------------------------

it('still renders the cover-image credit through the extracted shared partial', function (): void {
    $out = (string) Antlers::parse('{{ partial:layout/page-header/cover-image }}', [
        'cover_image' => [
            'url' => '/a.jpg',
            'alt' => 'A',
            'exempt_from_alt' => false,
            'caption' => 'Cover caption',
            'creator' => 'Jane Doe',
            'creator_organization' => 'ACME Photography',
        ],
        'cover_image_alt_text_override' => null,
        'cover_image_caption_override' => null,
    ], true);

    expect($out)->toContain('<span class="photo-credit"><span class="sr-only">Credit: </span>Jane Doe / ACME Photography</span>');
});
