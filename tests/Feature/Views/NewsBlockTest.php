<?php

declare(strict_types=1);

use App\Tags\UamsNews;
use Statamic\Facades\Antlers;
use Symfony\Component\Yaml\Yaml;
use Tests\Feature\Views\StubUamsNewsTag;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Structural coverage for the UAMS News page-builder block (UAMS-Web/uams-statamic-archive#76): the fieldset
 * schema and the Antlers template wiring. The block's *behavior* is covered by
 * UamsNewsTest (the {{ uams_news }} tag) and NewsSyndicationQueryServiceTest (the
 * query core); this pins the editor-facing field shape and the template's format
 * branches + the CSS class contract the vendored `uams-2020` syndication SCSS
 * targets, neither of which those behavioral tests reach.
 *
 * These assertions run everywhere (no content/ submodule needed) — they read the
 * shipped source files directly, mirroring the repo's other block-wiring tests
 * (e.g. FadOntologyListBlockTest).
 *
 * @command  php artisan test --compact tests/Feature/Views/NewsBlockTest.php
 */

/**
 * The parsed News block fieldset.
 *
 * @return array<string, mixed>
 */
function newsBlockFieldset(): array
{
    /** @var array<string, mixed> $fieldset */
    $fieldset = Yaml::parseFile((string) realpath(__DIR__.'/../../../resources/fieldsets/uamsweb_block_news.yaml'));

    return $fieldset;
}

/**
 * The News block fieldset's fields, keyed by handle.
 *
 * @return array<string, array<string, mixed>>
 */
function newsBlockFields(): array
{
    $fields = [];
    /** @var array<int, array<string, mixed>> $rawFields */
    $rawFields = newsBlockFieldset()['fields'];
    foreach ($rawFields as $field) {
        /** @var string $handle */
        $handle = $field['handle'];
        $fields[$handle] = $field;
    }

    return $fields;
}

/** The News block template source. */
function newsBlockTemplate(): string
{
    return (string) file_get_contents((string) realpath(__DIR__.'/../../../resources/views/page_builder/_uamsweb_block_news.antlers.html'));
}

// ---------------------------------------------------------------------------
// Fieldset schema
// ---------------------------------------------------------------------------

it('offers all six syndication formats', function (): void {
    /** @var array<string, mixed> $format */
    $format = newsBlockFields()['format']['field'];
    /** @var array<int, array<string, mixed>> $options */
    $options = $format['options'];
    $keys = array_column($options, 'key');

    expect($format['type'])->toBe('radio')
        ->and($keys)->toEqualCanonicalizing(['headlines', 'excerpts', 'cards', 'full', 'grid', 'side']);
});

it('selects source sites via a required checkboxes field defaulting to news', function (): void {
    /** @var array<string, mixed> $source */
    $source = newsBlockFields()['source_sites']['field'];

    expect($source['type'])->toBe('checkboxes')
        ->and($source['options'])->toHaveKeys(['news', 'current'])
        ->and($source['default'])->toBe(['news'])
        ->and($source['validate'])->toContain('required');
});

it('selects the featured article via an entries picker scoped to posts', function (): void {
    /** @var array<string, mixed> $article */
    $article = newsBlockFields()['article_id']['field'];

    expect($article['type'])->toBe('entries')
        ->and($article['collections'])->toBe(['posts'])
        ->and($article['max_items'])->toBe(1)
        ->and($article['if'])->toBe(['format' => 'equals full']);
});

it('selects the category via an entries picker scoped to the post_categories collection', function (): void {
    /** @var array<string, mixed> $category */
    $category = newsBlockFields()['category']['field'];

    expect($category['type'])->toBe('entries')
        ->and($category['collections'])->toBe(['post_categories']);
});

it('drops the legacy WordPress-only fields', function (): void {
    // The remote-syndication era `local` boolean and `advanced_cat` field are gone;
    // source selection is now `source_sites` and category is a real entries picker.
    expect(newsBlockFields())->not->toHaveKeys(['local', 'advanced_cat']);
});

// ---------------------------------------------------------------------------
// Template wiring
// ---------------------------------------------------------------------------

it('delegates to the uams_news tag with the block field bindings', function (): void {
    $src = newsBlockTemplate();

    expect($src)->toContain('{{ uams_news')
        ->and($src)->toContain(':format="format"')
        ->and($src)->toContain(':source_sites="source_sites"')
        ->and($src)->toContain(':category="category"')
        ->and($src)->toContain(':article_id="article_id"')
        ->and($src)->toContain('as="news_item"');
});

it('guards against an empty result set', function (): void {
    // A no-results render must degrade quietly, never emit an empty shell or fatal.
    expect(newsBlockTemplate())->toContain('{{ if no_results }}');
});

it('renders through Antlers without an unregistered modifier (regression #76)', function (): void {
    // The block captured `count | int` / `offset | int`, but `int` is not a
    // registered Statamic modifier, so every page rendering the block 500'd with
    // ModifierNotFoundException before the uams_news tag (which already coerces both
    // to non-negative ints) ever ran. Rendering the real partial with the tag stubbed
    // to no-results exercises the count/offset capture lines hermetically — no
    // content/ submodule — and fails if the invalid modifier is ever reintroduced.
    app()->bind(UamsNews::class, StubUamsNewsTag::class);

    $rendered = (string) Antlers::parse(newsBlockTemplate(), [
        'format' => 'headlines',
        'count' => '5',
        'offset' => '2',
    ], true);

    // The stubbed no-results branch degrades to blank; the point is that parsing the
    // capture lines no longer throws.
    expect(trim($rendered))->toBe('');
});

dataset('news_format_wrappers', [
    'headlines' => ['uamswp-news-syndication-headlines'],
    'excerpts' => ['uamswp-news-syndication-excerpts'],
    'cards' => ['uamswp-news-syndication-cards'],
    'grid' => ['uamswp-news-syndication-grid'],
    'side' => ['uamswp-news-syndication-side'],
    'full' => ['uamswp-news-syndication-full'],
]);

it('renders each format wrapper class the vendored SCSS targets', function (string $class): void {
    expect(newsBlockTemplate())->toContain($class);
})->with('news_format_wrappers');

it('renders the headlines list with the bulletless list/item classes', function (): void {
    $src = newsBlockTemplate();

    // These are the exact classes the headlines.scss reset keys off.
    expect($src)->toContain('uamswp-news-syndication-list')
        ->and($src)->toContain('uamswp-news-syndication-item');
});

it('marks up each article-rendering format with schema.org NewsArticle metadata', function (): void {
    // The four article-card formats emit the itemtype in five spots (excerpts,
    // cards, grid's featured + secondary items, full); headlines (bare links) and
    // side (a hero) do not. Pinning the exact count catches a regression that
    // strips the microdata from a branch — a bare toContain would not.
    expect(substr_count(newsBlockTemplate(), 'itemtype="https://schema.org/NewsArticle"'))->toBe(5);
});

it('offers each visibility toggle only for formats whose branch renders that element', function (): void {
    // Regression guard for the adversarial-review finding: a toggle offered for a
    // format that renders no image/date/byline is an inert CP control. Each `if`
    // now tracks exactly the branches that render (and guard) that element.
    $fields = newsBlockFields();

    // Image: excerpts, cards, grid, and full render a `$hide_img`-guarded image.
    /** @var array{if: array<string, mixed>} $hideImgConfig */
    $hideImgConfig = $fields['hide_img']['config'];
    expect($hideImgConfig['if']['format'])->toBe('contains_any excerpts, cards, grid, full');

    // Date + creator byline: only excerpts and full render them.
    /** @var array{if: array<string, mixed>} $hideDateConfig */
    $hideDateConfig = $fields['hide_date']['config'];
    /** @var array{if: array<string, mixed>} $hideCreatorConfig */
    $hideCreatorConfig = $fields['hide_creator_query']['config'];
    expect($hideDateConfig['if']['format'])->toBe('contains_any excerpts, full')
        ->and($hideCreatorConfig['if']['format'])->toBe('contains_any excerpts, full');
});

it('gates the require-featured-image toggle to the image-led layouts (#1154)', function (): void {
    // The filter only makes sense where the layout requires an image slot.
    $field = newsBlockFields()['require_featured_image'];
    /** @var array{if: array<string, mixed>, default: mixed} $config */
    $config = $field['config'];

    expect($field['field'])->toBe('uamsweb_common.true_false')
        ->and($config['if']['format'])->toBe('contains_any cards, grid, side')
        ->and($config['default'])->toBeFalse();
});

it('gates the hide-meta toggle to the byline-rendering layouts (#1175)', function (): void {
    // Author + date only render on excerpts and full, so the combined meta
    // toggle is offered exactly there.
    $field = newsBlockFields()['hide_meta'];
    /** @var array{if: array<string, mixed>, default: mixed} $config */
    $config = $field['config'];

    expect($field['field'])->toBe('uamsweb_common.true_false')
        ->and($config['if']['format'])->toBe('contains_any excerpts, full')
        ->and($config['default'])->toBeFalse();
});
