<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Coverage for UAMS-Web/uams-statamic-archive#1531: overlaying an asset's media credit (`creator` /
 * `creator_organization`) on the background-image page-builder blocks (Side-by-Side,
 * Text & Image Overlay, Call-Out, CTA) and page headers (Hero, Graphic, Marketing
 * Landing Page), which paint the image as a CSS background / behind-text `<picture>`
 * and so have no `<figcaption>` to hang a credit on. The credit renders as an
 * absolutely-positioned `.media-credit-overlay` with a scrim guaranteeing WCAG 2.1 AA
 * contrast, gated by a per-block/-header/-slide `display_media_credit` toggle that
 * defaults to hidden (opt-in).
 *
 * Antlers template partials + SCSS — no mutable PHP surface. The overlay markup is
 * shared and identical across surfaces; the per-surface `{{ if … }}` guard fragment is
 * extracted verbatim from each real partial (the `GalleryBlockColumnsTest` pattern) and
 * rendered hermetically, because the full block/header render drives Glide on a real
 * Asset (an array stand-in cannot). The AA guarantee of each scrim pairing is a
 * self-checking computation below, not a claim.
 *
 * @command  php artisan test --compact tests/Feature/Views/MediaCreditOverlayTest.php
 */

/** Collapse whitespace so structural assertions ignore Antlers indentation. */
function normalizeOverlayHtml(string $html): string
{
    return trim((string) preg_replace('/\s+/', ' ', $html));
}

/**
 * Extract a surface's `{{ if <guard> }} … {{ /if }}` overlay block verbatim from its
 * real partial (single-level `{{ if }}`, so the first `{{ /if }}` after the guard is
 * the match), so a regression in the actual file is caught.
 */
function mediaCreditOverlayFragment(string $relPath, string $guard): string
{
    $source = (string) file_get_contents(resource_path("views/$relPath"));

    $start = strpos($source, $guard);
    expect($start)->not->toBeFalse("overlay guard not found in $relPath");

    $end = strpos($source, '{{ /if }}', (int) $start);
    expect($end)->not->toBeFalse("overlay {{ /if }} not found in $relPath");

    return substr($source, (int) $start, (int) $end - (int) $start + strlen('{{ /if }}'));
}

/**
 * WCAG relative luminance of an sRGB [r,g,b] triple.
 *
 * @param  array<int, int|float>  $rgb
 */
function srgbLuminance(array $rgb): float
{
    $channel = static function (float $v): float {
        $v /= 255;

        return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
    };

    return 0.2126 * $channel($rgb[0]) + 0.7152 * $channel($rgb[1]) + 0.0722 * $channel($rgb[2]);
}

/**
 * WCAG contrast ratio between two sRGB triples.
 *
 * @param  array<int, int|float>  $a
 * @param  array<int, int|float>  $b
 */
function wcagContrast(array $a, array $b): float
{
    $la = srgbLuminance($a);
    $lb = srgbLuminance($b);
    [$hi, $lo] = $la > $lb ? [$la, $lb] : [$lb, $la];

    return ($hi + 0.05) / ($lo + 0.05);
}

// ---------------------------------------------------------------------------
// AA guarantee — self-checking contrast of every scrim DEFINED IN THE SCSS
// ---------------------------------------------------------------------------

/**
 * Resolve a path under `resources/` from the test file itself — usable at data-provider
 * resolution time, before the application (and `resource_path()`) is booted.
 */
function mediaCreditResourcePath(string $relative): string
{
    return dirname(__DIR__, 3).'/resources/'.$relative;
}

/**
 * The `$name: #hex;` color variables declared in variables.scss, keyed by `$name`.
 *
 * @return array<string, string>
 */
function scssColorVars(): array
{
    $src = (string) file_get_contents(mediaCreditResourcePath('scss/bootstrap/uams_2020/uams-supporting/variables.scss'));
    preg_match_all('/^\$([\w-]+):\s*(#[0-9a-fA-F]{3,6})\s*;/m', $src, $matches, PREG_SET_ORDER);

    $map = [];
    foreach ($matches as $match) {
        $map['$'.$match[1]] = $match[2];
    }

    return $map;
}

/** Expand `#abc` shorthand to a full `#aabbcc`. */
function expandHex(string $hex): string
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }

    return '#'.$hex;
}

/**
 * Every scrim pairing `[hexColor, alpha]` actually defined in media-credit.scss, resolved
 * against the SCSS color variables. Deriving the pairings from the shipped SCSS (rather than a
 * hardcoded mirror) is what makes the contrast test below bind to reality — an AA-breaking
 * opacity change (e.g. `rgba($color, 0.9)` → `0.6`) or a lightened `$*-darkest` brand stop
 * turns the suite red instead of shipping green.
 *
 * @return array<string, array{0: string, 1: float}>
 */
function mediaCreditScrimPairings(): array
{
    $scss = (string) file_get_contents(mediaCreditResourcePath('scss/bootstrap/uams_2020/uams-supporting/uams-modules/media-credit.scss'));
    $vars = scssColorVars();

    // Every `rgba($var, alpha)`: the neutral base scrim (a concrete color var) and the
    // per-color `@each` variant (the `$color` loop var).
    preg_match_all('/rgba\((\$[\w-]+),\s*([\d.]+)\)/', $scss, $rgbas, PREG_SET_ORDER);

    $neutral = null;
    $variantAlpha = null;
    foreach ($rgbas as $rgba) {
        if ($rgba[1] === '$color') {
            $variantAlpha = (float) $rgba[2];
        } else {
            $neutral = [$rgba[1], (float) $rgba[2]];
        }
    }

    if ($neutral === null) {
        throw new RuntimeException('neutral rgba() scrim not found in media-credit.scss');
    }
    if ($variantAlpha === null) {
        throw new RuntimeException('variant rgba($color, …) alpha not found in media-credit.scss');
    }

    // The `@each` color-stop variables (`'bg-red': $red-darkest`, …). Single-quoted so `\$`
    // survives into the pattern — a double-quoted PHP string would collapse `\$` to a bare
    // `$` end-anchor and match nothing, silently dropping every color variant.
    preg_match_all('/\'[\w-]+\':\s*(\$[\w-]+)/', $scss, $stops);

    $resolve = static function (string $var) use ($vars): string {
        if (! isset($vars[$var])) {
            throw new RuntimeException("SCSS color {$var} not resolvable from variables.scss");
        }

        return expandHex($vars[$var]);
    };

    $pairings = ['neutral '.$neutral[0] => [$resolve($neutral[0]), $neutral[1]]];
    foreach (array_unique($stops[1]) as $var) {
        $pairings[$var] = [$resolve($var), $variantAlpha];
    }

    return $pairings;
}

it('guarantees AA contrast for every scrim defined in the SCSS over the lightest possible image', function (string $hex, float $alpha): void {
    // White credit text sits on the scrim `rgba($hex, $alpha)` composited over a pure WHITE
    // image pixel — the lightest bleed, i.e. the worst case for white text. Any real image
    // pixel darkens the effective background, only raising contrast.
    /** @var list<int> $rgb */
    $rgb = sscanf(ltrim($hex, '#'), '%2x%2x%2x');
    $effective = array_map(static fn (int $c): int => (int) round($alpha * $c + (1 - $alpha) * 255), $rgb);

    expect(wcagContrast([255, 255, 255], $effective))->toBeGreaterThanOrEqual(4.5);
})->with(fn (): array => mediaCreditScrimPairings());

// ---------------------------------------------------------------------------
// Behavioral — a background-image block (Call-Out) and a page header (Graphic)
// ---------------------------------------------------------------------------

it('renders the Call-Out overlay credit only when the toggle is on and the image is credited', function (array $context, bool $expectCredit): void {
    $fragment = mediaCreditOverlayFragment(
        'page_builder/_uamsweb_block_call_out.antlers.html',
        '{{ if background_image_query && display_media_credit && background_image:creator }}',
    );

    $out = normalizeOverlayHtml((string) Antlers::parse($fragment, $context, true));

    if ($expectCredit) {
        expect($out)->toContain('<p class="media-credit-overlay">');
        expect($out)->toContain('<span class="photo-credit"><span class="sr-only">Credit: </span>Jane Doe / ACME</span>');
    } else {
        expect($out)->toBe('');
    }
})->with([
    'toggle on + credited' => [
        ['background_image_query' => true, 'display_media_credit' => true, 'background_image' => ['creator' => 'Jane Doe', 'creator_organization' => 'ACME']],
        true,
    ],
    'toggle off + credited' => [
        ['background_image_query' => true, 'display_media_credit' => false, 'background_image' => ['creator' => 'Jane Doe', 'creator_organization' => 'ACME']],
        false,
    ],
    'toggle unset + credited (default hidden)' => [
        ['background_image_query' => true, 'background_image' => ['creator' => 'Jane Doe', 'creator_organization' => 'ACME']],
        false,
    ],
    'toggle on + uncredited' => [
        ['background_image_query' => true, 'display_media_credit' => true, 'background_image' => ['url' => '/a.jpg']],
        false,
    ],
]);

it('renders the Graphic-header overlay credit from the header group when toggled on', function (array $context, bool $expectCredit): void {
    $fragment = mediaCreditOverlayFragment(
        'layout/page-header/_graphic.antlers.html',
        '{{ if header_graphic:display_media_credit && header_graphic:image:creator }}',
    );

    $out = normalizeOverlayHtml((string) Antlers::parse($fragment, $context, true));

    if ($expectCredit) {
        expect($out)->toContain('<p class="media-credit-overlay">');
        expect($out)->toContain('Jane Doe / ACME');
    } else {
        expect($out)->toBe('');
    }
})->with([
    'toggle on + credited' => [
        ['header_graphic' => ['display_media_credit' => true, 'image' => ['creator' => 'Jane Doe', 'creator_organization' => 'ACME']]],
        true,
    ],
    'toggle off + credited' => [
        ['header_graphic' => ['display_media_credit' => false, 'image' => ['creator' => 'Jane Doe', 'creator_organization' => 'ACME']]],
        false,
    ],
    'toggle on + uncredited' => [
        ['header_graphic' => ['display_media_credit' => true, 'image' => ['url' => '/a.jpg']]],
        false,
    ],
]);

it('renders the per-slide Hero overlay credit from the slide image when toggled on', function (): void {
    $fragment = mediaCreditOverlayFragment(
        'layout/page-header/_hero-content.antlers.html',
        '{{ if display_media_credit && $image_desktop:creator }}',
    );

    $on = normalizeOverlayHtml((string) Antlers::parse($fragment, ['display_media_credit' => true, 'image_desktop' => ['creator' => 'Jane Doe', 'creator_organization' => 'ACME']], true));
    expect($on)->toContain('<p class="media-credit-overlay">');
    expect($on)->toContain('Jane Doe / ACME');

    $off = normalizeOverlayHtml((string) Antlers::parse($fragment, ['display_media_credit' => false, 'image_desktop' => ['creator' => 'Jane Doe']], true));
    expect($off)->toBe('');
});

// ---------------------------------------------------------------------------
// Wiring — every surface wires the overlay + the shared partial
// ---------------------------------------------------------------------------

it('wires the media-credit overlay and shared partial into every background-image surface', function (string $relPath): void {
    $source = (string) file_get_contents(resource_path("views/$relPath"));

    expect($source)->toContain('<p class="media-credit-overlay">');
    expect($source)->toContain('partial:typography/media_credit');
})->with([
    'call_out' => ['page_builder/_uamsweb_block_call_out.antlers.html'],
    'cta' => ['page_builder/_uamsweb_block_cta.antlers.html'],
    'overlay' => ['page_builder/_uamsweb_block_overlay.antlers.html'],
    'side_by_side' => ['page_builder/_uamsweb_block_image_side_by_side.antlers.html'],
    'hero' => ['layout/page-header/_hero-content.antlers.html'],
    'graphic' => ['layout/page-header/_graphic.antlers.html'],
    'marketing_landing_page' => ['layout/page-header/_marketing-landing-page.antlers.html'],
]);

// ---------------------------------------------------------------------------
// Fieldsets — the toggle is present and defaults to hidden (opt-in)
// ---------------------------------------------------------------------------

it('adds an opt-in (default-off) display_media_credit toggle to every surface fieldset', function (string $fieldset): void {
    $source = (string) file_get_contents(resource_path("fieldsets/$fieldset.yaml"));

    expect($source)->toContain('handle: display_media_credit');
    // The toggle must default OFF — background-image credits are opt-in.
    expect($source)->toMatch('/handle: display_media_credit.*?default: false/s');
})->with([
    'call_out' => ['uamsweb_block_call_out'],
    'cta' => ['uamsweb_block_cta'],
    'overlay' => ['uamsweb_block_overlay'],
    'side_by_side' => ['uamsweb_block_image_side_by_side'],
    'graphic' => ['uamsweb_header_graphic'],
    'marketing_landing_page' => ['uamsweb_header_marketing_landing_page'],
    'hero' => ['uamsweb_header_hero'],
]);

// ---------------------------------------------------------------------------
// SCSS — the scrim partial exists, defines the neutral + color variants, is imported
// ---------------------------------------------------------------------------

it('defines the neutral and color-matched scrims and imports the partial', function (): void {
    $scss = (string) file_get_contents(resource_path('scss/bootstrap/uams_2020/uams-supporting/uams-modules/media-credit.scss'));

    // Base neutral scrim + white text.
    expect($scss)->toContain('.media-credit-overlay');
    expect($scss)->toContain('rgba($black, 0.72)');
    expect($scss)->toContain('color: $white');

    // The color-variant scrim opacity is pinned (a lightening tweak here would break AA and
    // must fail the suite, not ship green).
    expect($scss)->toContain('rgba($color, 0.9)');

    // A color-matched scrim per dark palette color (block `bg-*` classes), each tied to a
    // dark `$*-darkest` brand stop (or `$black`).
    foreach (['bg-red', 'bg-blue', 'bg-green', 'bg-teal', 'bg-eggplant', 'bg-orange', 'bg-black'] as $class) {
        expect($scss)->toContain("'$class'");
    }
    foreach (['$red-darkest', '$blue-darkest', '$green-darkest', '$teal-darkest', '$eggplant-darkest', '$orange-darkest'] as $stop) {
        expect($scss)->toContain($stop);
    }
    // … and the hero's bare auto-color classes.
    expect($scss)->toContain('.carousel-item.#{$class}');

    // The partial is wired into the module import chain.
    $modules = (string) file_get_contents(resource_path('scss/bootstrap/uams_2020/uams-supporting/uams-modules.scss'));
    expect($modules)->toContain('@import "uams-modules/media-credit"');
});
