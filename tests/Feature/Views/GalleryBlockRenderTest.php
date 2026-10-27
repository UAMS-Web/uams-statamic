<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Statamic\Fieldtypes\Radio;
use Statamic\Fieldtypes\Toggle;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Coverage for UAMS-Web/uams-statamic-archive#1514: the UAMS Gallery page-builder block rendered nothing when
 * `modal` / `more_query` were off, and — once past the guard — threw at render.
 * UAMS-Web/uams-statamic-archive#1354 had repaired the `col-`class arithmetic that crashed the block on every
 * render before the `{{ if $block_valid }}` guard, which unmasked the faults below.
 *
 * 1. The guard's `X != null` checks were PHP-loose. A `true_false` field
 *    (`modal` / `more_query`) augments to a bool, so an "off" answer is `false`, and
 *    `false != null` is false — the whole block was invalidated and rendered nothing.
 *    Because `more_query` defaults **off**, this hit the common case. The fix uses
 *    strict `!== null` (passes for `false`, rejects only an actual `null`).
 * 2. The modal path numbered images with a hand-rolled `{{? $i … ?}}` counter that
 *    read back `null` in the array-loop child scope, so `{{ $i + 1 }}` threw
 *    `[ANTLR_003] … got [null] near "+"`. The fix uses the built-in `index` (0-based,
 *    the modal id) and `count` (1-based, the "image N" label).
 * 3. `crop` is a radio: at request time it augments to a `Statamic\Fields\LabeledValue`
 *    object, not the raw numeric key. The aspect-ratio line assigned that object to
 *    `$gallery_image_ratio`, which then reached the picture partial's `$w / $ratio`
 *    division and threw `[ANTLR_003] … got [bool] near "/"` for every non-None crop.
 *    The fix reads `block:crop:value` (the numeric key). This fault was invisible to a
 *    raw-array render (raw `crop` is already numeric); the render tests below bind
 *    `crop` through the real `Radio::augment()` so they exercise the production shape.
 *
 * Two render modes are used deliberately:
 *   - The validity guard is extracted verbatim from the partial and rendered with RAW
 *     scalars — it tests the guard operators (`!==` / `&&`) on the values a hermetic
 *     array render (and unaugmented / importer input) actually presents.
 *   - The full-block tests bind `crop` / `modal` / `more_query` through the real
 *     fieldtypes' `augment()` (the production shape: radio → `LabeledValue`,
 *     toggle → bool), asserting the partial renders without throwing. Assets stay plain
 *     arrays (Glide no-ops on an array url), so no `content/` submodule is needed.
 *
 * @command  php artisan test --compact tests/Feature/Views/GalleryBlockRenderTest.php
 */
function galleryBlockRenderSource(): string
{
    return (string) file_get_contents(
        (string) realpath(__DIR__.'/../../../resources/views/page_builder/_uamsweb_block_gallery.antlers.html')
    );
}

/**
 * The full validity computation, extracted verbatim from the partial (so a file-level
 * regression is caught): `$images_count`, the `$block_valid` base guard, and the
 * `{{ if $block_valid && more_query }} … {{ /if }}` refinement that also requires the
 * `more_*` fields. Rendering it plus a `VALID`/`invalid` echo reproduces what gates the block.
 */
function galleryValidityGuard(): string
{
    $source = galleryBlockRenderSource();

    $start = strpos($source, '{{ $images_count = images');
    expect($start)->not->toBeFalse('gallery $images_count line not found in the partial');

    $ifPos = strpos($source, '{{ if $block_valid && more_query }}', (int) $start);
    expect($ifPos)->not->toBeFalse('gallery more_query refinement block not found in the partial');

    $end = strpos($source, '{{ /if }}', (int) $ifPos);
    expect($end)->not->toBeFalse('gallery more_query refinement {{ /if }} not found');

    return substr($source, (int) $start, (int) $end + strlen('{{ /if }}') - (int) $start);
}

/**
 * Just the `{{ $block_valid = … }}` assignment line, without the surrounding
 * explanatory comment — so an assertion about the operators is not confused by prose
 * that names them.
 */
function galleryValidityExpression(): string
{
    $source = galleryBlockRenderSource();

    $start = strpos($source, '{{ $block_valid = block_heading');
    expect($start)->not->toBeFalse('gallery $block_valid assignment not found in the partial');

    $end = strpos($source, '}}', (int) $start);

    return substr($source, (int) $start, (int) $end + 2 - (int) $start);
}

/**
 * Render the extracted validity guard for a field set and report whether the block
 * would render. Values are RAW (bool `false`, int `0`, `null`) — this tests the guard
 * operators, not the production augmentation layer.
 *
 * @param  array<string, mixed>  $fields
 */
function galleryBlockIsValid(array $fields): bool
{
    $template = galleryValidityGuard()."{{ \$block_valid ? 'VALID' : 'invalid' }}";

    return trim((string) Antlers::parse($template, $fields, true)) === 'VALID';
}

/**
 * A gallery image asset as a plain array (the hermetic asset stand-in — Glide no-ops
 * on an array url rather than resolving a real rendition).
 *
 * @return array<string, mixed>
 */
function galleryImageAsset(string $alt): array
{
    return ['url' => "/assets/{$alt}.jpg", 'alt' => $alt, 'caption' => "Caption {$alt}", 'creator' => 'Jane Doe'];
}

/**
 * A complete, valid RAW gallery field set (for the guard-operator tests), overridable
 * per case. `crop` defaults to the integer "None" key and `modal` to `true`.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validGalleryFields(array $overrides = []): array
{
    return array_merge([
        'block_heading' => 'My Gallery',
        'block_description' => 'A gallery description',
        'hide_block_heading' => false,
        'background_color' => 'bg-primary',
        'columns' => '3',
        'images' => [galleryImageAsset('A'), galleryImageAsset('B'), galleryImageAsset('C')],
        'crop' => 0,
        'modal' => true,
        'more_query' => false,
    ], $overrides);
}

/**
 * Render the full gallery partial with PRODUCTION-augmented field values: `crop`
 * through `Radio::augment()` (→ `LabeledValue`), `modal` / `more_query` through
 * `Toggle::augment()` (→ bool). Fields are exposed both bare and under `block:`; the
 * `more_*` fields are supplied only when `more_query` is on. Any render fault
 * propagates — a throw fails the test.
 *
 * @param  array{columns?: string, crop?: int|float|string, modal?: bool, more_query?: bool, images?: array<int, array<string, mixed>>}  $overrides
 */
function renderGalleryBlock(array $overrides = []): string
{
    $radio = new Radio;
    $toggle = new Toggle;

    $columns = $overrides['columns'] ?? '3';
    $cropRaw = $overrides['crop'] ?? 0;
    $modalRaw = $overrides['modal'] ?? true;
    $moreRaw = $overrides['more_query'] ?? false;
    $images = $overrides['images'] ?? [galleryImageAsset('A'), galleryImageAsset('B'), galleryImageAsset('C')];

    $fields = [
        'block_heading' => 'My Gallery',
        'block_description' => 'A gallery description',
        'hide_block_heading' => false,
        'background_color' => 'bg-primary',
        'columns' => $columns,
        'images' => $images,
        'crop' => $radio->augment($cropRaw),
        'modal' => $toggle->augment($modalRaw),
        'more_query' => $toggle->augment($moreRaw),
    ];

    if ($moreRaw) {
        $fields += [
            'more_heading' => 'See more images',
            'more_button_text' => 'View all',
            'more_button_url' => '/gallery',
            'more_button_target' => $toggle->augment(false),
        ];
    }

    return (string) Antlers::parse(
        '{{ partial:page_builder/_uamsweb_block_gallery }}',
        $fields + ['block' => $fields],
        true,
    );
}

// ---------------------------------------------------------------------------
// Fault 1 — the `$block_valid` guard (raw operator semantics)
// ---------------------------------------------------------------------------

it('validates a block whose falsy-but-answered required field is set', function (array $overrides): void {
    // `modal` / `more_query` off (bool false) was the real render-nothing symptom;
    // `crop` = 0 is covered for the raw / importer render path.
    /** @var array<string, mixed> $overrides */
    expect(galleryBlockIsValid(validGalleryFields($overrides)))->toBeTrue();
})->with([
    'modal off (bool false)' => [['modal' => false]],
    'more_query off (bool false)' => [['more_query' => false]],
    'crop None (int 0)' => [['crop' => 0]],
    'crop None (string "0")' => [['crop' => '0']],
    'all three falsy at once' => [['modal' => false, 'more_query' => false, 'crop' => 0]],
]);

it('rejects a block whose required field is a raw null', function (string $field): void {
    // In the hermetic array / unaugmented-input render path, an actually-absent (null)
    // required field is rejected by strict `!== null`. In production these fields
    // augment (radio → LabeledValue, toggle → bool) so they never reach the guard as
    // raw null; requiredness there is enforced by the blueprint's `validate: required`.
    expect(galleryBlockIsValid(validGalleryFields([$field => null])))->toBeFalse();
})->with(['crop', 'modal', 'more_query']);

it('rejects a block missing a non-nullable required field', function (array $overrides): void {
    /** @var array<string, mixed> $overrides */
    expect(galleryBlockIsValid(validGalleryFields($overrides)))->toBeFalse();
})->with([
    'no heading' => [['block_heading' => null]],
    'no background color' => [['background_color' => null]],
]);

it('enforces the image-count bounds inclusively (2 to 6)', function (int $count, bool $valid): void {
    // The guard is `$images_count >= 2 && $images_count <= 6`. Pin both inclusive
    // limits so a `>=`→`>` / `<=`→`<` narrowing (the same silent-empty-render class
    // as UAMS-Web/uams-statamic-archive#1514) fails here.
    $images = array_map(fn (int $n): array => galleryImageAsset((string) $n), range(1, $count));

    expect(galleryBlockIsValid(validGalleryFields(['images' => $images])))->toBe($valid);
})->with([
    '1 image (too few)' => [1, false],
    '2 images (lower bound)' => [2, true],
    '6 images (upper bound)' => [6, true],
    '7 images (too many)' => [7, false],
]);

it('requires the more_* fields only when more_query is on', function (): void {
    // more_query on but the more_* fields unanswered → invalid …
    expect(galleryBlockIsValid(validGalleryFields(['more_query' => true])))->toBeFalse();

    // … and answered → valid.
    expect(galleryBlockIsValid(validGalleryFields([
        'more_query' => true,
        'more_heading' => 'See more',
        'more_button_text' => 'View',
        'more_button_url' => '/g',
    ])))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Faults 2 & 3 + integration — the full block renders (production-augmented)
// ---------------------------------------------------------------------------

it('renders a full valid gallery block without throwing across column counts and modal on/off', function (string $columns, bool $modal): void {
    $html = renderGalleryBlock(['columns' => $columns, 'modal' => $modal, 'crop' => 0]);

    expect($html)
        ->toContain('gallery-block')
        ->toContain('image-container')
        ->toContain('<picture>');

    if ($modal) {
        expect($html)->toContain('class="modal fade"');
    } else {
        expect($html)->not->toContain('class="modal fade"');
    }
})->with(function (): array {
    $cases = [];
    foreach (['2', '3', '4', '6'] as $columns) {
        foreach (['modal on' => true, 'modal off' => false] as $label => $modal) {
            $cases["{$columns} columns, {$label}"] = [$columns, $modal];
        }
    }

    return $cases;
});

it('renders every crop option without throwing (augmented LabeledValue → ratio math)', function (int|float $crop): void {
    // The regression guard for fault 3: a radio-augmented `crop` is a LabeledValue, and
    // any non-None value flows into the picture partial's `$w / $ratio` division. With
    // modal on, this also exercises the loop counter (fault 2) on the same render.
    $html = renderGalleryBlock(['crop' => $crop, 'modal' => true, 'columns' => '3']);

    expect($html)
        ->toContain('gallery-block')
        ->toContain('<picture>')
        ->toContain('class="modal fade"');
})->with([
    'None (0)' => 0,
    '1:1 (1)' => 1,
    '16:9 (1.7778)' => 1.7778,
    '4:3 (1.3333)' => 1.3333,
    '3:4 (0.75)' => 0.75,
]);

it('renders the grid when modal and more_query are off and crop is None (the #1514 symptom)', function (): void {
    // Precisely the reported symptom: modal off + more_query off + crop None used to
    // fail the guard and render nothing.
    $html = renderGalleryBlock(['modal' => false, 'more_query' => false, 'crop' => 0, 'columns' => '3']);

    expect(trim($html))->not->toBe('');
    expect($html)
        ->toContain('gallery-block')
        ->toContain('<picture>');
    expect($html)->not->toContain('class="modal fade"');
});

it('renders the full block with more_query on, emitting the "more" CTA without throwing', function (): void {
    // Criterion 3's "modal on, more_query on" full path — exercises the block_more /
    // item_button partials that the other cases (more_query off) never reach.
    $html = renderGalleryBlock(['modal' => true, 'more_query' => true, 'crop' => 1.7778, 'columns' => '3']);

    expect($html)
        ->toContain('gallery-block')
        ->toContain('<picture>')
        ->toContain('class="modal fade"')
        ->toContain('col-12 more')
        ->toContain('See more images');
});

it('renders one matching, uniquely-identified modal per image with 1-based labels', function (): void {
    $html = renderGalleryBlock(['modal' => true, 'crop' => 1, 'columns' => '3']);

    preg_match_all('/class="modal fade" id="(modal_\d+_[^"]+)"/', $html, $ids);
    preg_match_all('/data-target="#(modal_\d+_[^"]+)"/', $html, $targets);
    preg_match_all('/aria-label="Show larger version of image (\d+)[^"]*"/', $html, $labels);

    expect($ids[1])->toHaveCount(3);
    // Every trigger points at a real modal, and every modal id is unique.
    expect($targets[1])->toEqual($ids[1]);
    expect(array_unique($ids[1]))->toEqual($ids[1]);
    // The visible counter is 1-based (index + 1 → count), in order.
    expect($labels[1])->toEqual(['1', '2', '3']);
});

// ---------------------------------------------------------------------------
// Source-level pins for the three fixes
// ---------------------------------------------------------------------------

it('no longer contains the broken hand-rolled loop counter', function (): void {
    // The `{{? $i = 0 ?}}` / `{{? $i += 1 ?}}` / `$i + 1` counter is gone; the modal
    // id/label now use the built-in `index` / `count`. (These `$i`-prefixed tokens
    // can't match the surviving `$images_count`, which never has a space, `+`, or `}`
    // immediately after the `$i`.)
    $source = galleryBlockRenderSource();

    expect($source)->not->toContain('$i =');
    expect($source)->not->toContain('$i +');
    expect($source)->not->toContain('$i }');
    expect($source)
        ->toContain('#modal_{{ index }}_')
        ->toContain('image {{ count }}');
});

it('keeps the strict null checks in the validity guard', function (): void {
    // Pin the fix: loose `!= null` (the UAMS-Web/uams-statamic-archive#1514 bug) must not creep back into the
    // expression itself (the explanatory comment names it, so assert on the line).
    $expression = galleryValidityExpression();

    expect($expression)
        ->toContain('crop !== null')
        ->toContain('modal !== null')
        ->toContain('more_query !== null');
    expect($expression)->not->toContain('!= null');
});

it('unwraps the crop LabeledValue for the aspect-ratio computation', function (): void {
    // Pin the fault-3 fix: the ratio must read `block:crop:value` (the numeric key),
    // not the bare `block:crop` LabeledValue object.
    expect(galleryBlockRenderSource())->toContain('block:crop:value');
});
