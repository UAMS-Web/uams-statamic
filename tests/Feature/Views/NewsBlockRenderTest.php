<?php

declare(strict_types=1);

use App\Tags\UamsNews;
use Illuminate\Support\Carbon;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Antlers;
use Statamic\Facades\Blink;
use Statamic\Facades\Entry;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Support\Str;
use Tests\Feature\Views\ConfigurableNewsItemsTag;
use Tests\TestCase;

// Returned to the parallel band on UAMS-Web/uams-statamic-archive#2503. This file was gated by UAMS-Web/uams-statamic-archive#1457 because the image-slot
// assertions flaked under `--parallel` while passing deterministically in isolation (UAMS-Web/uams-statamic-archive#1452).
// The gate attributed that to "the shared, paratest-non-isolated compiled-view cache", which
// was already per worker under Laravel's `TestViews` when the flake was observed, so the cause
// was never established; the UAMS-Web/uams-statamic-archive#1300 Stache warm-up race UAMS-Web/uams-statamic-archive#1590 closed is the likeliest closer,
// and that remains a hypothesis. As an observed flake it came off only on the README's ten-run
// bar: ten consecutive `--parallel --exclude-group=serial` bands on a no-snapshot checkout
// with this file ungated produced zero failures (macOS, 2026-09-25). If it recurs, restore the
// gate with the observed failure as its reason. tests/README.md carries the audit row.
uses(TestCase::class);

/**
 * Behavioral render coverage for the UAMS News block's format branches, driven
 * with fixture items via {@see ConfigurableNewsItemsTag} (the {{ uams_news }} tag
 * stubbed to return controllable posts). NewsBlockTest pins the *structure*
 * (fieldset + template source); this renders the branches to prove what each
 * layout actually emits per item — the 4:3 side image (UAMS-Web/uams-statamic-archive#1154), per-item category
 * (UAMS-Web/uams-statamic-archive#1154), hide-meta (UAMS-Web/uams-statamic-archive#1175), the fallback images (UAMS-Web/uams-statamic-archive#1178/UAMS-Web/uams-statamic-archive#1179), and the block
 * description (UAMS-Web/uams-statamic-archive#1191).
 *
 * @command  php artisan test --compact tests/Feature/Views/NewsBlockRenderTest.php
 */
// Flush the request cache so a resolved block-header partial (block:description
// et al.) from a prior case can never leak into the next render.
beforeEach(function (): void {
    Blink::flush();
    ConfigurableNewsItemsTag::reset();
});

afterEach(fn () => ConfigurableNewsItemsTag::reset());

/** The News block template source. */
function newsBlockPartialSource(): string
{
    $path = realpath(__DIR__.'/../../../resources/views/page_builder/_uamsweb_block_news.antlers.html');

    if ($path === false) {
        return '';
    }

    $contents = file_get_contents($path);

    return $contents === false ? '' : $contents;
}

/**
 * A fixture news item with sensible defaults, overridable per test.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function newsItem(array $overrides = []): array
{
    return array_merge([
        'url' => '/news/example',
        'title' => 'Example Story',
        'excerpt' => 'A short excerpt.',
        'date' => Carbon::parse('2026-07-01'),
        'cover_image_query' => false,
        'post_categories' => [],
        'creator' => [],
    ], $overrides);
}

/**
 * A real, unsaved `posts` entry — the shape `{{ uams_news }}` hands the loop.
 *
 * `Entry::make()` is declared to return the *contract*, which has no `collection()`,
 * so the concrete type is narrowed by control flow rather than by an `assert()` or an
 * inline `@var` (UAMS-Web/uams-statamic-archive#1733 / UAMS-Web/uams-statamic-archive#1865). Named for this file to avoid colliding with another
 * test file's global helper — Pest declares these in the GLOBAL namespace.
 *
 * @param  array<int, string>  $categoryIds  Stored `post_categories` ids, if any.
 * @param  ?string  $locale  Site handle. An `entries` relation only resolves ids on the
 *                           entry's OWN site, so a category on another site is stored but
 *                           yields nothing — one of the two ways `| count` alone misleads.
 */
function newsBlockRealPostEntry(string $title, array $categoryIds = [], ?string $locale = null): StatamicEntry
{
    $entry = Entry::make();

    if (! $entry instanceof StatamicEntry) {
        throw new RuntimeException('Entry::make() did not return '.StatamicEntry::class.'.');
    }

    // Not chained: `collection()` is a fluent getter/setter declared to return `mixed`,
    // so `->blueprint()` off its result is uncheckable.
    $entry->collection('posts');
    $entry->blueprint('post');

    if ($locale !== null) {
        $entry->locale($locale);
    }
    $entry->set('title', $title);
    $entry->set('slug', Str::slug($title));

    if ($categoryIds !== []) {
        $entry->set('post_categories', $categoryIds);
    }

    return $entry;
}

/**
 * Render the News block partial for a format with the given items + block fields.
 *
 * @param  array<int, array<string, mixed>|StatamicEntry>  $items  Array fixtures, or real
 *                                                                 entries as production supplies them — `UamsNews::result()` returns
 *                                                                 `array<int, Entry>`, so the entry arm is the SHIPPING shape, not a test-only one.
 * @param  array<string, mixed>  $block  block-level fields exposed under the `block:` scope
 * @param  array<string, mixed>  $fields  block-level fields read bare by the partial (toggles, format)
 */
function renderNewsBlock(string $format, array $items, array $block = [], array $fields = []): string
{
    /** @var int $render */
    static $render = 0;
    $render++;

    ConfigurableNewsItemsTag::$items = $items;

    app()->bind(UamsNews::class, ConfigurableNewsItemsTag::class);

    // Render through the PARTIAL, not by parsing the template's source string.
    // This is not a style choice — it decides whether the suite can see a whole class
    // of defect (UAMS-Web/uams-statamic-archive#2031).
    //
    // `Antlers::parse($string, $vars)` defaults to `$trusted = false`, which sets
    // `GlobalRuntimeState::$isEvaluatingUserData = true` — Antlers' user-content
    // sandbox. Production never renders a template that way: `Engine::get()` goes
    // through `RuntimeParser::parseView()`, which forces that flag OFF. In the sandbox
    // an augmented relation reaching a `{{ pair }}` is silently reduced, so an
    // empty-relation fall-through cannot occur and a test for one passes no matter
    // what the template says. Parsing a `{{ partial: }}` tag puts the partial body on
    // the trusted path, which is what PostCardPrimaryCategoryTest has always had —
    // and why the card's defect was reproducible while this file's was not.
    //
    // The per-render Antlers comment still uniquifies the OUTER parse, so a prior
    // render's resolved block-header `description` cannot leak into the next.
    $source = '{{ partial:page_builder/uamsweb_block_news }}'."\n{{# render-{$render} #}}";

    // Pass the block-level toggles the partial reads *bare* from the cascade
    // (`hide_img`, `hide_meta`, `hide_date`, `hide_creator_query`, `include_link`
    // — see `{{ $hide_img = hide_img }}` etc. at the top of the partial) with
    // explicit falsy defaults. Statamic's Antlers runtime is process-global, so a
    // *prior* render in the same process — e.g. a full homepage GET in an earlier
    // serial-group test — can leave one of these set, and a bare read then picks
    // up that stale value (a truthy `hide_img` drops the whole image column,
    // silently turning the excerpts-image assertions red). Making them explicit
    // here shadows any leaked cascade/runtime value; `$fields` still overrides so
    // the per-toggle tests keep working. This is why SideBySideBlockTest — which
    // passes every field explicitly — is immune to the same serial neighbor.
    return (string) Antlers::parse($source, array_merge([
        'format' => $format,
        'hide_img' => false,
        'hide_meta' => false,
        'hide_date' => false,
        'hide_creator_query' => false,
        'include_link' => false,
        'block' => array_merge(['block_heading' => 'Newsroom', 'hide_block_heading' => false], $block),
    ], $fields));
}

// ---------------------------------------------------------------------------
// UAMS-Web/uams-statamic-archive#1154 — side-by-side 4:3 override
// ---------------------------------------------------------------------------

it('uses the 4:3 featured image for the side format when the editor supplied one', function (): void {
    $html = renderNewsBlock('side', [newsItem([
        'cover_image_query' => true,
        'featured_image_4_3' => ['url' => '/img/four-three.jpg', 'alt' => 'Four three'],
        'featured_image_16_9' => ['url' => '/img/sixteen-nine.jpg', 'alt' => 'Sixteen nine'],
    ])]);

    expect($html)->toContain("background-image: url('/img/four-three.jpg')")
        ->and($html)->not->toContain('/img/sixteen-nine.jpg');
});

it('falls back to the 16:9 featured image for the side format when no 4:3 is set', function (): void {
    $html = renderNewsBlock('side', [newsItem([
        'cover_image_query' => true,
        'featured_image_16_9' => ['url' => '/img/sixteen-nine.jpg', 'alt' => 'Sixteen nine'],
    ])]);

    expect($html)->toContain("background-image: url('/img/sixteen-nine.jpg')");
});

// ---------------------------------------------------------------------------
// UAMS-Web/uams-statamic-archive#1154 — per-item category label on cards
// ---------------------------------------------------------------------------

it('renders each card item its own category label', function (): void {
    $html = renderNewsBlock('cards', [
        newsItem(['title' => 'Has Category', 'post_categories' => [['title' => 'Research']]]),
        newsItem(['title' => 'No Category', 'post_categories' => []]),
    ]);

    // The categorized card shows its category; the label markup appears exactly once.
    expect($html)->toContain('news-item-category')
        ->and($html)->toContain('Research')
        ->and(substr_count($html, 'news-item-category'))->toBe(1);
});

it('escapes a markup-bearing card category label', function (): void {
    // Category titles are editor/importer input rendered in raw-by-default Antlers,
    // so the card label strips tags + entity-encodes (stored-XSS guard, UAMS-Web/uams-statamic-archive#1144).
    $html = renderNewsBlock('cards', [
        newsItem(['title' => 'Story', 'post_categories' => [['title' => 'R&D <img src=x onerror=alert(1)>']]]),
    ]);

    expect($html)->toContain('R&amp;D')
        ->and($html)->not->toContain('<img')
        ->and($html)->not->toContain('onerror');
});

// ---------------------------------------------------------------------------
// UAMS-Web/uams-statamic-archive#2031 — empty-relation fall-through on the per-item category label
// ---------------------------------------------------------------------------

it('keeps the card category label off when a real entry relation resolves to nothing', function (): void {
    // The array fixtures above CANNOT express this case, which is why UAMS-Web/uams-statamic-archive#2026 fixed
    // the shared post card but had to leave the block open. `newsItem()` passes
    // `post_categories => []` — a falsy array the loop simply skips. Production
    // never sees that shape: `UamsNews::result()` returns `array<int, Entry>`, and
    // a real augmented entry never OMITS a blueprint handle
    // (`AugmentedEntry::keys()` merges every one), so an empty relation arrives as
    // a `Value` wrapping a `StatusQueryBuilder` — a TRUTHY object that yields
    // nothing. Looping it emits the body once against the PARENT scope, where
    // `title` is the post's own headline, printing the story's title as its own
    // category.
    //
    // Seeding a REAL entry is the whole point: it is the only way to reach the
    // production shape through this harness (UAMS-Web/uams-statamic-archive#2031).
    $entry = newsBlockRealPostEntry('UAMS Health Talk Episode 12');

    $html = renderNewsBlock('cards', [$entry]);

    // Assert the card actually rendered before asserting the label is absent —
    // otherwise a render that emitted nothing at all would pass vacuously.
    expect($html)->toContain('card-title')
        ->and($html)->toContain('UAMS Health Talk Episode 12')
        ->and($html)->not->toContain('news-item-category');
});

it('keeps the card category label off when the relation holds only ids that resolve to nothing', function (): void {
    // The case a bare `| count` would miss on the post card: ids ARE stored, so a
    // count of the raw field is non-zero, while the query yields nothing because the
    // targets are unpublished, on another site, or gone. Covered here because the
    // block's safety must not depend on the relation being *empty* — only on the
    // loop path flattening it (UAMS-Web/uams-statamic-archive#2031).
    $entry = newsBlockRealPostEntry('UAMS Health Talk Episode 12', ['no-such-category-id', 'also-missing']);

    $html = renderNewsBlock('cards', [$entry]);

    expect($html)->toContain('card-title')
        ->and($html)->toContain('UAMS Health Talk Episode 12')
        ->and($html)->not->toContain('news-item-category');
});

it('still renders the card category label when the relation resolves to a real category', function (): void {
    // The over-suppression control. Without it the two tests above are satisfiable by a
    // gate that simply never lets the label render — `{{ if false }}` would pass both.
    // The array-fixture test further up does not cover this: `| count` over
    // `[['title' => 'Research']]` is 1 whatever the gate does with a query builder, so
    // it never exercises the gate against a real one.
    // Narrowed by control flow rather than an inline `@var`: `Entry::query()` and `id()`
    // are both declared against contracts that do not carry what is called on them.
    $query = Entry::query();

    if (! $query instanceof EntryQueryBuilder) {
        $this->markTestSkipped('Entry::query() did not return '.EntryQueryBuilder::class.'.');
    }

    // Unchained: `where()` is declared to return `mixed`, so `->first()` off it is
    // uncheckable no matter how the builder itself is narrowed.
    $query->where('collection', 'post_categories');
    $category = $query->first();

    if (! $category instanceof StatamicEntry) {
        $this->markTestSkipped('Requires at least one entry in the post_categories collection.');
    }

    $categoryId = $category->id();

    if (! is_string($categoryId)) {
        $this->markTestSkipped('The post_categories entry has no string id.');
    }

    $categoryLocale = $category->locale();

    if (! is_string($categoryLocale)) {
        $this->markTestSkipped('The post_categories entry has no string locale.');
    }

    $entry = newsBlockRealPostEntry('UAMS Health Talk Episode 12', [$categoryId], $categoryLocale);

    $html = renderNewsBlock('cards', [$entry]);

    expect($html)->toContain('card-title')
        ->and($html)->toContain('news-item-category');
});

// ---------------------------------------------------------------------------
// UAMS-Web/uams-statamic-archive#1175 — hide-meta (author + date)
// ---------------------------------------------------------------------------

// The `news-item-byline` wrapper holds BOTH the creator (author) row and the
// date span (`news-item-byline-date`); asserting on those markup hooks is
// timezone-independent, unlike the human-formatted date string.

it('renders the meta byline (author + date) when hide_meta is off', function (): void {
    $html = renderNewsBlock('excerpts', [newsItem(['date' => Carbon::parse('2026-07-01')])], fields: ['hide_meta' => false]);

    expect($html)->toContain('news-item-byline')
        ->and($html)->toContain('news-item-byline-date');
});

it('hides the whole meta byline (author and date) when hide_meta is on', function (): void {
    $html = renderNewsBlock('excerpts', [newsItem(['date' => Carbon::parse('2026-07-01')])], fields: ['hide_meta' => true]);

    expect($html)->not->toContain('news-item-byline')
        ->and($html)->not->toContain('news-item-byline-date');
});

it('hide_meta is the combined control, not an alias of hide_date', function (): void {
    // hide_date alone drops the date span but keeps the byline (the author row
    // is still shown), so the byline wrapper survives — proving hide_meta differs.
    $html = renderNewsBlock('excerpts', [newsItem(['date' => Carbon::parse('2026-07-01')])], fields: ['hide_date' => true, 'hide_meta' => false]);

    expect($html)->toContain('news-item-byline')
        ->and($html)->not->toContain('news-item-byline-date');
});

// ---------------------------------------------------------------------------
// UAMS-Web/uams-statamic-archive#1178 — 16:9 card fallback image
// ---------------------------------------------------------------------------

/**
 * A news item that would render its own cover image.
 *
 * @return array<string, mixed>
 */
function newsImageItem(string $url = '/img/cover.jpg'): array
{
    return newsItem([
        'cover_image_query' => true,
        'featured_image_16_9' => ['url' => $url, 'alt' => 'Cover'],
    ]);
}

it('renders the 16:9 fallback for an image-less card', function (): void {
    $html = renderNewsBlock('cards', [newsItem(['title' => 'No Image'])]);

    expect($html)->toContain('card-img-top-fallback')
        ->and($html)->toContain('news-item-fallback-image');
});

it('renders a card post its own image, not the fallback', function (): void {
    $html = renderNewsBlock('cards', [newsImageItem('/img/real.jpg')]);

    expect($html)->toContain('/img/real.jpg')
        ->and($html)->not->toContain('news-item-fallback-image');
});

it('uses the fallback only for image-less cards in a mixed card block', function (): void {
    $html = renderNewsBlock('cards', [
        newsImageItem('/img/has.jpg'),
        newsItem(['title' => 'No Image']),
    ]);

    // Exactly one fallback (the image-less card) and the real image both present.
    expect(substr_count($html, 'news-item-fallback-image'))->toBe(1)
        ->and($html)->toContain('/img/has.jpg');
});

it('renders no card image or fallback when hide_img is on', function (): void {
    $html = renderNewsBlock('cards', [newsItem(['title' => 'No Image'])], fields: ['hide_img' => true]);

    expect($html)->not->toContain('news-item-fallback-image')
        ->and($html)->not->toContain('card-img-top');
});

// ---------------------------------------------------------------------------
// UAMS-Web/uams-statamic-archive#1179 — excerpts fallback featured image
// ---------------------------------------------------------------------------

it('renders the fallback in the excerpts image slot for an image-less post', function (): void {
    $html = renderNewsBlock('excerpts', [newsItem(['title' => 'No Image'])]);

    // The fallback fills the image column (no offset-only text layout).
    expect($html)->toContain('image-container-fallback')
        ->and($html)->toContain('news-item-fallback-image')
        ->and($html)->not->toContain('offset-sm-4');
});

it('renders an excerpts post its own image, not the fallback', function (): void {
    $html = renderNewsBlock('excerpts', [newsImageItem('/img/excerpt.jpg')]);

    expect($html)->toContain('/img/excerpt.jpg')
        ->and($html)->not->toContain('news-item-fallback-image');
});

it('gives every excerpt an image in a mixed block', function (): void {
    $html = renderNewsBlock('excerpts', [
        newsImageItem('/img/has.jpg'),
        newsItem(['title' => 'No Image']),
    ]);

    // One real image + one fallback = an image for every excerpt.
    expect($html)->toContain('/img/has.jpg')
        ->and(substr_count($html, 'news-item-fallback-image'))->toBe(1);
});

it('keeps the offset (image-less) excerpt layout when hide_img is on', function (): void {
    $html = renderNewsBlock('excerpts', [newsItem(['title' => 'No Image'])], fields: ['hide_img' => true]);

    expect($html)->not->toContain('news-item-fallback-image')
        ->and($html)->toContain('offset-sm-4');
});

// ---------------------------------------------------------------------------
// UAMS-Web/uams-statamic-archive#1191 — block description in a description-capable layout
// ---------------------------------------------------------------------------
//
// The block already exposes an optional description field (`block_description`),
// rendered under the heading by the shared `block_header` for the list layouts
// (headlines/excerpts/cards/grid) — exactly the "supporting copy where the layout
// allows" UAMS-Web/uams-statamic-archive#1191 asks for. These lock that behavior in for the cards layout.

it('renders the block description in the cards layout when set', function (): void {
    $html = renderNewsBlock('cards', [newsImageItem()], block: ['block_description' => 'Curated stories for you.']);

    // `widont` rewrites the final space to &nbsp;, so assert on the stable prefix.
    expect($html)->toContain('module-description')
        ->and($html)->toContain('Curated stories');
});

it('renders no description markup when the block description is empty', function (): void {
    $html = renderNewsBlock('cards', [newsImageItem()], block: ['block_description' => '']);

    expect($html)->not->toContain('module-description');
});

// ---------------------------------------------------------------------------
// UAMS-Web/uams-statamic-archive#1532 — entry-level featured-image media credit on the entry-sourced image
// ---------------------------------------------------------------------------
//
// A syndicated post's featured image belongs to *that* post's owner, so whether its
// photo credit shows is controlled at the source entry via `featured_image_credit_query`
// (not on the consuming block). The credit reuses the shared `typography/media_credit`
// partial and appears only when the entry opts in AND the asset carries a `creator`.

/**
 * A news item whose featured image carries a photo credit, plus the entry-level toggle.
 *
 * @return array<string, mixed>
 */
function newsCreditedItem(bool $creditQuery = true, ?string $creator = 'Jane Doe'): array
{
    return newsItem([
        'cover_image_query' => true,
        'featured_image_16_9' => array_filter([
            'url' => '/img/cover.jpg',
            'alt' => 'Cover',
            'creator' => $creator,
            'creator_organization' => 'ACME Photography',
        ], fn ($v): bool => $v !== null),
        'featured_image_4_3' => array_filter([
            'url' => '/img/cover-43.jpg',
            'alt' => 'Cover 4:3',
            'creator' => $creator,
            'creator_organization' => 'ACME Photography',
        ], fn ($v): bool => $v !== null),
        'featured_image_credit_query' => $creditQuery,
    ]);
}

it('renders the featured-image credit in the image formats when the post opts in', function (string $format): void {
    $html = renderNewsBlock($format, [newsCreditedItem()]);

    expect($html)->toContain('<span class="photo-credit">')
        ->and($html)->toContain('Jane Doe / ACME Photography');
})->with(['excerpts', 'cards', 'grid', 'full', 'side']);

it('renders no featured-image credit when the post opts out', function (string $format): void {
    $html = renderNewsBlock($format, [newsCreditedItem(creditQuery: false)]);

    expect($html)->not->toContain('photo-credit');
})->with(['excerpts', 'cards', 'grid', 'full', 'side']);

it('renders no featured-image credit when the credited toggle is on but the asset has no creator', function (): void {
    $html = renderNewsBlock('cards', [newsCreditedItem(creator: null)]);

    expect($html)->not->toContain('photo-credit');
});

it('does not fatal or leak the author byline when a post has authors but an uncredited featured image', function (string $format): void {
    // Fall-through regression: a bare `creator` inside {{ featured_image_16_9 }} would
    // cascade to the post's non-empty byline `creator` (an array) and fatal `strip_tags`.
    // The scoped `featured_image_16_9:creator` idiom must resolve the asset's absent
    // credit instead — no credit, no exception.
    $html = renderNewsBlock($format, [newsItem([
        'cover_image_query' => true,
        'featured_image_16_9' => ['url' => '/img/cover.jpg', 'alt' => 'Cover'], // no creator
        'featured_image_4_3' => ['url' => '/img/cover-43.jpg', 'alt' => 'Cover 4:3'],
        'featured_image_credit_query' => true,
        'creator' => [['name' => 'Dr. Author'], ['name' => 'Dr. Second']], // non-empty byline
    ])]);

    expect($html)->not->toContain('photo-credit');
})->with(['excerpts', 'cards', 'grid', 'full', 'side']);
