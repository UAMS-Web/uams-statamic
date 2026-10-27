<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Statamic\Facades\Antlers;
use Statamic\Facades\Fieldset;
use Statamic\Fields\Fields;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Coverage for UAMS-Web/uams-statamic-archive#1858: the UAMS Logo List page-builder block rendered nothing for
 * every block the WordPress importer produces. Three independent clauses of the
 * `$block_valid` guard in `_uamsweb_block_logo_list.antlers.html` rejected the
 * importer's output, so an imported page silently lost the block — no markup, no
 * error. Same defect class UAMS-Web/uams-statamic-archive#1514 repaired for the gallery block.
 *
 * 1. `logo_list_labels` sat in the base required list, so a *truthiness* test
 *    gated the block on a display toggle. Its default is `false` and the importer
 *    emits `false` whenever labels are off (the common case), which invalidated
 *    the whole block. It is a display option, not a validity condition.
 * 2. Every `X != null` presence check was PHP-loose. The affected fields
 *    (`logo_list_links`, `more_query`, `logo_list_titles`, `logo_list_shortdescr`,
 *    `logo_list_links_target`, `more_button_target`) are all `true_false`, and an
 *    "off" one fails a loose presence test on BOTH paths this file exercises: as
 *    the raw bool `false` the importer writes (`null == false`), and as the `''`
 *    the augmentation pipeline yields (`null == ''`). The fix is strict `!== null`,
 *    which passes for both and rejects only an actual `null` — and Antlers
 *    resolves an absent key to `null` too, so the presence check the clause was
 *    *written* to be is preserved.
 * 3. A *category* filter required `logo_list_filter_label_logic`, a *label*
 *    filter's field. The two are independent filters, and the importer emits the
 *    label logic only when label filters exist — so a category-only block (the
 *    common shape) failed. The clause was also unreachable-by-construction from
 *    the other side: `LogoListParser` drops a block outright when its category
 *    filter is empty, so every imported block hits it. The fix splits the clause
 *    so each filter requires only its own logic field.
 *
 * Three render modes, extending `GalleryBlockRenderTest`'s two:
 *   - The validity guard is extracted verbatim from the partial and rendered with
 *     RAW scalars, so the tests exercise the guard *operators* on the values the
 *     importer actually writes to disk.
 *   - The same guard is rendered against values augmented through the REAL
 *     fieldset — the shape a page-builder set delivers — so the fix is proven on
 *     the production path and not only on raw scalars. See
 *     {@see augmentedLogoListFields()} for why that distinction is load-bearing.
 *   - The full-block tests render the real partial and assert markup, so a block
 *     that passes the guard genuinely reaches output.
 *
 * @command  php artisan test --compact tests/Feature/Views/LogoListBlockRenderTest.php
 */
function logoListBlockRenderSource(): string
{
    return (string) file_get_contents(
        (string) realpath(__DIR__.'/../../../resources/views/page_builder/_uamsweb_block_logo_list.antlers.html')
    );
}

/**
 * The validity computation, extracted verbatim from the partial (so a file-level
 * regression is caught): the `$block_valid` base guard plus every refinement
 * through the `logo_list_links` one. The item-count and sort-option checks that
 * follow are deliberately excluded — they are a separate, working concern, and
 * including them would make every guard case depend on a sort replicator.
 */
function logoListValidityGuard(): string
{
    $source = logoListBlockRenderSource();

    $start = strpos($source, '{{ $block_valid = block_heading');
    expect($start)->not->toBeFalse('logo list $block_valid assignment not found in the partial');

    $linksIf = strpos($source, '{{ if $block_valid && logo_list_links }}', (int) $start);
    expect($linksIf)->not->toBeFalse('logo list links refinement not found in the partial');

    $end = strpos($source, '{{ /if }}', (int) $linksIf);
    expect($end)->not->toBeFalse('logo list links refinement {{ /if }} not found');

    return substr($source, (int) $start, (int) $end + strlen('{{ /if }}') - (int) $start);
}

/**
 * Render the extracted validity guard for a field set and report whether the
 * block would render. Values are RAW (bool `false`, `null`) — this tests the
 * guard operators, not the augmentation layer.
 *
 * @param  array<string, mixed>  $fields
 */
function logoListBlockIsValid(array $fields): bool
{
    $template = logoListValidityGuard()."{{ \$block_valid ? 'VALID' : 'invalid' }}";

    return trim((string) Antlers::parse($template, $fields + ['block' => $fields], true)) === 'VALID';
}

/**
 * A complete, valid RAW logo-list field set as the WordPress importer emits it:
 * every display toggle OFF (bool `false`), a category filter with its logic, and
 * NO label filter or label logic. This is the shape that rendered nothing.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function importedLogoListFields(array $overrides = []): array
{
    return array_merge([
        'block_heading' => 'Our Partners',
        'background_color' => 'bg-white',
        'logo_list_layout' => 'grid',
        'logo_list_labels' => false,
        'logo_list_links' => false,
        'more_query' => false,
        'logo_list_filter_category' => ['colleges'],
        'logo_list_filter_category_logic' => 'any',
        'logo_list_width' => 'alignfull',
        'logo_list_titles' => false,
        'logo_list_shortdescr' => false,
        'logo_list_sort' => [['sort_by' => 'title', 'sort_order' => 'asc']],
    ], $overrides);
}

/**
 * The importer's payload augmented through the REAL fieldset, which is the shape
 * a page-builder set actually hands the partial (`{{ page_builder scope="block" }}`).
 *
 * This is the load-bearing harness. Calling a fieldtype's `augment()` directly
 * yields a bare `LabeledValue`, which is an object and therefore never `null` and
 * always truthy — and reasoning from that shape says the guard's clauses are
 * no-ops and the bug cannot exist. The real pipeline is `Fields::augment()`,
 * which wraps each field in a `Statamic\Fields\Value` that Antlers UNWRAPS for
 * comparison. An "off" `true_false` therefore reaches the guard as `''`:
 * loosely equal to `null`, and falsy. That is what actually broke the block.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function augmentedLogoListFields(array $overrides = []): array
{
    $fieldset = Fieldset::find('uamsweb_block_logo_list');

    if ($fieldset === null) {
        throw new RuntimeException('The uamsweb_block_logo_list fieldset is missing.');
    }

    $withValues = $fieldset->fields()->addValues(importedLogoListFields($overrides));

    if (! $withValues instanceof Fields) {
        throw new RuntimeException('Adding values to the logo-list fieldset did not yield a Fields object.');
    }

    $augmentedFields = $withValues->augment();

    if (! $augmentedFields instanceof Fields) {
        throw new RuntimeException('Augmenting the logo-list fieldset did not yield a Fields object.');
    }

    $values = $augmentedFields->values();

    if (! $values instanceof Collection) {
        throw new RuntimeException('Augmenting the logo-list fieldset did not yield a value collection.');
    }

    /** @var array<string, mixed> $augmented */
    $augmented = $values->all();

    return $augmented;
}

/**
 * The guard's verdict for an already-augmented field set.
 *
 * @param  array<string, mixed>  $fields
 */
function augmentedLogoListBlockIsValid(array $fields): bool
{
    $template = logoListValidityGuard()."{{ \$block_valid ? 'VALID' : 'invalid' }}";

    return trim((string) Antlers::parse($template, $fields + ['block' => $fields], true)) === 'VALID';
}

// ---------------------------------------------------------------------------
// The production path — the guard as a page-builder set actually sees it
// ---------------------------------------------------------------------------

it('validates the imported block on the real augmented path', function (): void {
    // The end-to-end claim of UAMS-Web/uams-statamic-archive#1858: with the guard repaired, the block a
    // WordPress import produces reaches output through the shape the page
    // builder really delivers, not merely through raw scalars.
    expect(augmentedLogoListBlockIsValid(augmentedLogoListFields()))->toBeTrue();
});

it('rejects the imported block under the pre-fix guard on that same path', function (): void {
    // The regression anchor. Rebuild the three original clauses verbatim and
    // confirm they reject the importer's block — so if someone reverts the fix,
    // or reintroduces a loose presence check, this goes red for the ORIGINAL
    // reason rather than passing on a technicality.
    $preFixGuard = <<<'ANTLERS'
        {{ $block_valid = block_heading && background_color && logo_list_layout && logo_list_labels && logo_list_links != null && more_query != null }}
        {{ $block_valid ? 'VALID' : 'invalid' }}
        ANTLERS;

    $fields = augmentedLogoListFields();

    expect(trim((string) Antlers::parse($preFixGuard, $fields + ['block' => $fields], true)))->toBe('invalid');
});

it('shows why the old clauses failed on the augmented shapes', function (string $expression, bool $expected): void {
    // Each row is one of the three faults, isolated. An "off" `true_false`
    // augments to a `Value` that unwraps to `''` — falsy, and `'' == null` — so
    // the truthiness gate and every loose `!= null` check rejected it, while the
    // strict `!== null` the fix uses passes.
    $fields = augmentedLogoListFields();

    $rendered = trim((string) Antlers::parse(
        '{{ if '.$expression.' }}TRUE{{ else }}FALSE{{ /if }}',
        $fields + ['block' => $fields],
    ));

    expect($rendered)->toBe($expected ? 'TRUE' : 'FALSE');
})->with([
    // The display toggle the base guard used to require: falsy when off.
    ['logo_list_labels', false],
    // Loose presence checks: false for an off toggle, which is the bug.
    ['logo_list_links != null', false],
    ['more_query != null', false],
    // Strict presence checks: true for an off toggle, which is the fix.
    ['logo_list_links !== null', true],
    ['more_query !== null', true],
]);

// ---------------------------------------------------------------------------
// The importer's shapes now pass the guard
// ---------------------------------------------------------------------------

it('validates an imported block with every display toggle off', function (): void {
    expect(logoListBlockIsValid(importedLogoListFields()))->toBeTrue();
});

it('validates a category-only block, with no label filter or label logic', function (): void {
    $fields = importedLogoListFields();

    // The shape LogoListParser always produces: a category filter, no label filter.
    expect($fields)->not->toHaveKey('logo_list_filter_label');
    expect($fields)->not->toHaveKey('logo_list_filter_label_logic');

    expect(logoListBlockIsValid($fields))->toBeTrue();
});

it('validates a block carrying both category and label filters', function (): void {
    expect(logoListBlockIsValid(importedLogoListFields([
        'logo_list_filter_label' => ['founding'],
        'logo_list_filter_label_logic' => 'all',
    ])))->toBeTrue();
});

it('validates a links-enabled block whose link target is off', function (): void {
    // `logo_list_links_target` defaults TRUE, so `false` is the deliberate
    // "same window" answer — and the loose `!= null` rejected exactly that.
    expect(logoListBlockIsValid(importedLogoListFields([
        'logo_list_links' => true,
        'logo_list_links_target' => false,
    ])))->toBeTrue();
});

it('validates a more-link block whose button target is off', function (): void {
    expect(logoListBlockIsValid(importedLogoListFields([
        'more_query' => true,
        'more_heading' => 'More partners',
        'more_button_text' => 'See all',
        'more_button_url' => '/partners',
        'more_button_target' => false,
    ])))->toBeTrue();
});

// ---------------------------------------------------------------------------
// The guard is repaired, not removed — genuinely invalid blocks still fail
// ---------------------------------------------------------------------------

it('rejects a block missing a genuinely required field', function (string $missing): void {
    expect(logoListBlockIsValid(importedLogoListFields([$missing => null])))->toBeFalse();
})->with(['block_heading', 'background_color', 'logo_list_layout']);

it('rejects a category filter whose category logic is absent', function (): void {
    expect(logoListBlockIsValid(importedLogoListFields([
        'logo_list_filter_category_logic' => null,
    ])))->toBeFalse();
});

it('rejects a label filter whose label logic is absent', function (): void {
    expect(logoListBlockIsValid(importedLogoListFields([
        'logo_list_filter_label' => ['founding'],
        'logo_list_filter_label_logic' => null,
    ])))->toBeFalse();
});

it('rejects a grid block missing a grid-only display field', function (string $missing): void {
    expect(logoListBlockIsValid(importedLogoListFields([$missing => null])))->toBeFalse();
})->with(['logo_list_width', 'logo_list_titles', 'logo_list_shortdescr']);

it('rejects a links-enabled block whose link target is absent', function (): void {
    expect(logoListBlockIsValid(importedLogoListFields([
        'logo_list_links' => true,
        'logo_list_links_target' => null,
    ])))->toBeFalse();
});

// ---------------------------------------------------------------------------
// The guard clauses themselves — asserted against the file
// ---------------------------------------------------------------------------

it('uses strict presence checks throughout the guard', function (): void {
    $guard = logoListValidityGuard();

    // No loose `!= null` survives; `null == false` is what broke every toggle.
    expect($guard)->not->toContain('!= null');
    expect($guard)->toContain('!== null');
});

it('no longer gates the block on the labels display toggle', function (): void {
    $source = logoListBlockRenderSource();

    $start = (int) strpos($source, '{{ $block_valid = block_heading');
    $baseGuard = substr($source, $start, (int) strpos($source, '}}', $start) + 2 - $start);

    // A display option is not a validity condition.
    expect($baseGuard)->not->toContain('logo_list_labels');
});

it('requires each filter logic field only when its own filter is set', function (): void {
    $guard = logoListValidityGuard();

    // The category refinement no longer reaches for the label filter's logic.
    $categoryIf = (int) strpos($guard, '{{ if $block_valid && logo_list_filter_category }}');
    $categoryEnd = (int) strpos($guard, '{{ /if }}', $categoryIf);
    $categoryClause = substr($guard, $categoryIf, $categoryEnd - $categoryIf);

    expect($categoryClause)->toContain('logo_list_filter_category_logic');
    expect($categoryClause)->not->toContain('logo_list_filter_label_logic');

    // The label logic is required by its own refinement instead.
    expect($guard)->toContain('{{ if $block_valid && logo_list_filter_label }}');
});

// ---------------------------------------------------------------------------
// End-to-end — the repaired block reaches markup
// ---------------------------------------------------------------------------

it('renders block markup for an imported block with every display toggle off', function (): void {
    $fields = importedLogoListFields();

    $html = (string) Antlers::parse(
        '{{ partial:page_builder/_uamsweb_block_logo_list }}',
        $fields + ['block' => $fields],
        true,
    );

    // The block reaches output at all — the whole point of UAMS-Web/uams-statamic-archive#1858.
    expect(trim($html))->not->toBe('');
    // Its layout-derived wrapper class is emitted.
    expect($html)->toContain('logo-list-layout-grid');
    expect($html)->toContain('item-list');
});

it('passes the selected category filter and its logic to the query scope', function (): void {
    // UAMS-Web/uams-statamic-archive#1863 converted `logos_item_categories` from a flat taxonomy to a
    // structured collection, so the three mutually exclusive
    // `:taxonomy:logos_item_categories:any|not|all` parameters this block used
    // are gone; the same three-way logic now travels to the
    // `logo_items_in_categories` scope, which applies it against the entries
    // reference.
    $source = logoListBlockRenderSource();

    expect($source)->not->toContain(':taxonomy:logos_item_categories:');
    expect($source)->toContain('query_scope="logo_items_in_categories|logo_items_with_labels"');
    expect($source)->toContain(':logic="logo_list_filter_category_logic"');

    // The ids must be taken RAW. Binding the augmented field
    // (`:categories="block:logo_list_filter_category"`) hands the scope a
    // `StatusQueryBuilder` rather than a list of ids, and the block silently
    // renders zero items — so guard the `| raw` hop, not just the parameter.
    expect($source)->toContain('$logo_list_categories = logo_list_filter_category | raw');
    expect($source)->toContain(':categories="$logo_list_categories"');
    expect($source)->not->toContain(':categories="block:logo_list_filter_category"');

    // UAMS-Web/uams-statamic-archive#2533 converted `logos_item_labels` the same way, so the label filter no
    // longer uses its taxonomy parameters.
    expect($source)->not->toContain(':taxonomy:logos_item_labels:');
});

it('passes the selected label filter and its logic to the label query scope', function (): void {
    // UAMS-Web/uams-statamic-archive#2533 converted `logos_item_labels` to a site-scoped structured collection, so
    // the label filter is an entries reference applied by `logo_items_with_labels`,
    // chained after the category scope on the same tag. Its parameters are distinct
    // from the category scope's, so the two filters cannot read each other's values.
    $source = logoListBlockRenderSource();

    expect($source)->toContain(':label_logic="logo_list_filter_label_logic"');

    // Taken RAW, for the reason the category ids are: the augmented field is a
    // query builder, and the block would render an empty list.
    expect($source)->toContain('$logo_list_label_ids = logo_list_filter_label | raw');
    expect($source)->toContain(':labels="$logo_list_label_ids"');

    // Not `$logo_list_labels`: Antlers drops the `$`, so that assignment would
    // overwrite the block's own "Display Organization Labels?" toggle.
    expect($source)->not->toContain('$logo_list_labels =');
    expect($source)->not->toContain(':labels="block:logo_list_filter_label"');
});

it('renders nothing for a genuinely invalid block', function (): void {
    $fields = importedLogoListFields(['block_heading' => null]);

    $html = (string) Antlers::parse(
        '{{ partial:page_builder/_uamsweb_block_logo_list }}',
        $fields + ['block' => $fields],
        true,
    );

    expect(trim($html))->toBe('');
});
