<?php

declare(strict_types=1);

use App\Scopes\LogoItemsWithLabels;
use App\Support\Entries\EntryReferenceFilter;
use Statamic\Facades\Antlers;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Fields\LabeledValue;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\Support\CollectionTreeIsolatedTestCase;
use Tests\Support\ContentSubmodule;

// Tree-isolated: saving a new entry into a structured collection appends it to that
// collection's tree, which must not touch the shared `logos_item_labels` tree.
uses(CollectionTreeIsolatedTestCase::class);

/**
 * @covers \App\Scopes\LogoItemsWithLabels
 * @covers \App\Support\Entries\EntryReferenceFilter
 *
 * The label filter UAMS-Web/uams-statamic-archive#2533 introduced when `logos_item_labels` became a site-scoped
 * structured collection: `logo_items_with_labels` carries the any / not / all logic
 * the block's three `:taxonomy:logos_item_labels:*` parameters used to express,
 * applied against the item's `logos_item_labels` entries reference. Exercised
 * against real disk-loaded content, and once through the `collection` tag the
 * block uses, chained after the category scope. Also covers the collection's site
 * scoping. Fixtures are saved and deleted quietly, for the reason
 * `SitemapTaxonomyTermsTest` records: nothing here reads a search index.
 *
 * @command  php artisan test --compact tests/Feature/Scopes/LogoItemsWithLabelsScopeTest.php
 */
const LOGO_LABEL_SCOPE_TEST_PREFIX = 'zz-logo-label-scope-test-';

/** A site the `logo_list_items` collection is enabled on. */
const LOGO_LABEL_SCOPE_ITEM_SITE = 'health_on_12th';

/** Remove every test label, category and item authored by this file (any site). */
function tearDownLogoLabelScopeFixtures(): void
{
    foreach (['logos_item_labels', 'logos_item_categories', 'logo_list_items'] as $collection) {
        /** @var EntryQueryBuilder $query */
        $query = Entry::query();
        $query->where('collection', $collection);

        /** @var Illuminate\Support\Collection<int, Statamic\Entries\Entry> $entries */
        $entries = $query->get();

        $entries
            ->filter(fn (Statamic\Entries\Entry $e): bool => is_string($e->slug()) && str_starts_with($e->slug(), LOGO_LABEL_SCOPE_TEST_PREFIX))
            ->each(fn (Statamic\Entries\Entry $e) => $e->deleteQuietly());
    }
}

/**
 * Create and quietly save a test entry in the given collection and site.
 *
 * @param  string  $collection  The collection handle.
 * @param  string  $slugSuffix  The slug suffix after the file's prefix.
 * @param  string  $site  The site to author it on.
 * @param  array<string, mixed>  $data  Extra field data.
 */
function makeLogoLabelScopeEntry(
    string $collection,
    string $slugSuffix,
    string $site = 'default',
    array $data = []
): Statamic\Entries\Entry {
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection($collection);
    $entry->locale($site);
    $entry->published(true);
    $entry->slug(LOGO_LABEL_SCOPE_TEST_PREFIX.$slugSuffix);
    $entry->data(['title' => 'ZZ '.$slugSuffix, ...$data]);
    $entry->saveQuietly();

    return $entry;
}

/**
 * The test items' slug suffixes the label scope selects, sorted.
 *
 * @param  array<string, mixed>  $values  The scope's tag parameters.
 * @return list<string>
 */
function logoItemsForLabelScope(array $values): array
{
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'logo_list_items');

    (new LogoItemsWithLabels)->apply($query, $values);

    /** @var Illuminate\Support\Collection<int, Statamic\Entries\Entry> $items */
    $items = $query->get();

    /** @var list<string> $suffixes */
    $suffixes = $items
        ->map(fn (Statamic\Entries\Entry $e): string => mustBeString($e->slug(), 'item slug'))
        ->filter(fn (string $slug): bool => str_starts_with($slug, LOGO_LABEL_SCOPE_TEST_PREFIX))
        ->map(fn (string $slug): string => substr($slug, strlen(LOGO_LABEL_SCOPE_TEST_PREFIX)))
        ->sort()
        ->values()
        ->all();

    return $suffixes;
}

/**
 * Two labels and four items carrying none, one, the other, or both.
 *
 * @return array{a: string, b: string}
 */
function seedLogoLabelScopeItems(): array
{
    $a = mustBeString(makeLogoLabelScopeEntry('logos_item_labels', 'label-a')->id(), 'label a id');
    $b = mustBeString(makeLogoLabelScopeEntry('logos_item_labels', 'label-b')->id(), 'label b id');

    makeLogoLabelScopeEntry('logo_list_items', 'item-none', LOGO_LABEL_SCOPE_ITEM_SITE);
    makeLogoLabelScopeEntry('logo_list_items', 'item-a', LOGO_LABEL_SCOPE_ITEM_SITE, ['logos_item_labels' => [$a]]);
    makeLogoLabelScopeEntry('logo_list_items', 'item-b', LOGO_LABEL_SCOPE_ITEM_SITE, ['logos_item_labels' => [$b]]);
    makeLogoLabelScopeEntry('logo_list_items', 'item-ab', LOGO_LABEL_SCOPE_ITEM_SITE, ['logos_item_labels' => [$a, $b]]);

    return ['a' => $a, 'b' => $b];
}

/**
 * Count the `logos_item_labels` entries matching one column, optionally narrowed to
 * a test slug.
 *
 * @param  string  $column  The column to match.
 * @param  mixed  $value  The value it must hold.
 * @param  string|null  $slugSuffix  The test slug suffix, when narrowing to one entry.
 */
function countLogoLabels(
    string $column,
    mixed $value,
    ?string $slugSuffix = null
): int {
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'logos_item_labels');
    $query->where($column, $value);

    // Narrow to the one test entry when a slug is given
    if ($slugSuffix !== null) {
        $query->where('slug', LOGO_LABEL_SCOPE_TEST_PREFIX.$slugSuffix);
    }

    // `count()` comes off the builder as `mixed`; narrowed by control flow
    $count = $query->count();

    if (! is_int($count)) {
        throw new RuntimeException('the entry query returned a non-integer count');
    }

    return $count;
}

beforeEach(function (): void {
    foreach (['logos_item_labels', 'logos_item_categories', 'logo_list_items'] as $handle) {
        if (! Collection::findByHandle($handle)) {
            ContentSubmodule::reportMissing("{$handle} collection");
        }
    }

    Site::setCurrent('default');
    tearDownLogoLabelScopeFixtures();
});

afterEach(function (): void {
    tearDownLogoLabelScopeFixtures();
    Site::setCurrent('default');
});

it('applies the block label logic to the entries reference', function (string $logic, array $expected): void {
    $labels = seedLogoLabelScopeItems();

    expect(logoItemsForLabelScope(['labels' => [$labels['a'], $labels['b']], 'label_logic' => $logic]))->toBe($expected);
})->with([
    // Any selected label is enough.
    'any' => ['any', ['item-a', 'item-ab', 'item-b']],
    // Every selected label must be present.
    'all' => ['all', ['item-ab']],
    // No selected label may be present.
    'not' => ['not', ['item-none']],
    // An unknown value falls back to `any`, the taxonomy parameter's default.
    'unknown' => ['sideways', ['item-a', 'item-ab', 'item-b']],
]);

it('reads the augmented radio shape of the label logic, not only a bare string', function (): void {
    $labels = seedLogoLabelScopeItems();

    // The logic field is a radio, which augments to a LabeledValue at request time.
    $logic = new LabeledValue('all', 'All');

    expect(logoItemsForLabelScope(['labels' => [$labels['a'], $labels['b']], 'label_logic' => $logic]))->toBe(['item-ab']);
});

it('treats a logic value that is neither a string nor stringable as any', function (): void {
    // A malformed parameter must fall back to the default rather than be cast.
    expect(EntryReferenceFilter::logic(['all']))->toBe('any')
        ->and(EntryReferenceFilter::logic(null))->toBe('any');
});

it('applies no constraint when no label is selected', function (): void {
    seedLogoLabelScopeItems();

    // The retired taxonomy parameters resolved to `void` with nothing selected and
    // listed every item, so an empty selection must not empty the block.
    expect(logoItemsForLabelScope(['labels' => [], 'label_logic' => 'all']))->toBe(['item-a', 'item-ab', 'item-b', 'item-none']);
});

it('ignores the category parameters, so it chains after the category scope', function (): void {
    $labels = seedLogoLabelScopeItems();

    // Category ids passed under the category scope's names must not be read as labels.
    expect(logoItemsForLabelScope(['categories' => [$labels['a']], 'logic' => 'all']))->toBe(['item-a', 'item-ab', 'item-b', 'item-none']);
});

it('filters on both categories and labels through the collection tag the block uses', function (): void {
    $labels = seedLogoLabelScopeItems();
    $category = mustBeString(makeLogoLabelScopeEntry('logos_item_categories', 'category')->id(), 'category id');

    // Two of the labeled items are also filed under the category.
    foreach (['item-b', 'item-ab'] as $suffix) {
        /** @var EntryQueryBuilder $query */
        $query = Entry::query();
        $query->where('collection', 'logo_list_items');
        $query->where('slug', LOGO_LABEL_SCOPE_TEST_PREFIX.$suffix);

        $item = mustBe(Statamic\Entries\Entry::class, $query->first(), "the {$suffix} item");
        $item->set('logos_item_categories', [$category]);
        $item->saveQuietly();
    }

    Site::setCurrent(LOGO_LABEL_SCOPE_ITEM_SITE);

    $html = (string) Antlers::parse(
        '{{ collection:logo_list_items query_scope="logo_items_in_categories|logo_items_with_labels" :categories="categories" logic="any" :labels="labels" label_logic="any" }}[{{ slug }}]{{ /collection:logo_list_items }}',
        ['categories' => [$category], 'labels' => [$labels['a']]],
        true
    );

    // Only the item filed under the category AND carrying label A survives both scopes.
    expect($html)->toContain('['.LOGO_LABEL_SCOPE_TEST_PREFIX.'item-ab]')
        ->not->toContain('['.LOGO_LABEL_SCOPE_TEST_PREFIX.'item-b]')
        ->not->toContain('['.LOGO_LABEL_SCOPE_TEST_PREFIX.'item-a]')
        ->not->toContain('['.LOGO_LABEL_SCOPE_TEST_PREFIX.'item-none]');
});

it('keeps a label on the site it was authored on', function (): void {
    $news = makeLogoLabelScopeEntry('logos_item_labels', 'news-only', 'news');

    // Present on the site it was authored on, absent from another, and never
    // localized there: the collection does not propagate.
    expect(countLogoLabels('site', 'news', 'news-only'))->toBe(1)
        ->and(countLogoLabels('site', 'default', 'news-only'))->toBe(0)
        ->and(countLogoLabels('origin', $news->id()))->toBe(0);
});
