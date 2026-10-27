<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Facades\Antlers;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Structures\CollectionStructure;
use Statamic\Structures\Tree;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Coverage for wiring a `posts_category_link` page into the primary navigation
 * (UAMS-Web/uams-statamic-archive#220): the desktop + mobile nav resolve the item's href from the selected
 * post-category's archive URL (via `{{ posts_category_link_url }}`) instead of the
 * page's own permalink, mirroring the UAMS-Web/uams-statamic-archive#219 `child_site_link` override.
 *
 * The tag's URL resolution is proven in PostsCategoryLinkUrlTagTest; here we prove
 * (a) both partials wire it identically (kept in sync, like
 * NavExcludesNonPublicEntriesTest asserts for the exclusion params) and (b) the
 * exact href-precedence the partials apply — a category link wins over the page's
 * own URL, and a plain page still uses its URL.
 *
 * @command  php artisan test --compact tests/Feature/Navigation/PostsCategoryLinkNavTest.php
 */
uses(StacheIsolatedTestCase::class);

it('wires desktop and mobile to resolve a category-link href, kept in sync', function (): void {
    foreach (['main/desktop', 'main/mobile'] as $view) {
        $markup = (string) File::get(
            resource_path("views/layout/navigation/{$view}.antlers.html")
        );

        expect($markup)
            // The category-archive href resolver (UAMS-Web/uams-statamic-archive#220), chained after the
            // child_site override and before the plain page url.
            ->toContain('$category_href = {posts_category_link_url}')
            ->toContain('$nav_item_href = $child_site_href ? $child_site_href : ( $category_href ? $category_href : url )');
    }
});

it('resolves a category-link item to the category archive URL, not the page permalink', function (): void {
    $collection = Collection::make('post_categories');
    $collection->title('Post Categories');
    $collection->sites(['default']);
    $collection->routes("{{ depth == 1 ?= 'category' }}/{{ parent_uri }}/{{ slug }}");
    $collection->structureContents(['root' => false]);
    $collection->save();

    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('post_categories');
    $entry->id('cat-cardiology');
    $entry->slug('cardiology');
    $entry->published(true);
    $entry->data(['title' => 'Cardiology']);
    $entry->save();

    /** @var Statamic\Entries\Collection $postCategories */
    $postCategories = Collection::find('post_categories');
    /** @var CollectionStructure $structure */
    $structure = $postCategories->structure();
    /** @var Tree $tree */
    $tree = $structure->makeTree('default', [['entry' => 'cat-cardiology']]);
    $tree->save();

    /** @var Statamic\Entries\Entry|null $categoryEntry */
    $categoryEntry = Entry::find('cat-cardiology');
    $categoryUrl = $categoryEntry?->url();
    expect($categoryUrl)->not->toBeNull();

    // The exact href resolution the desktop/mobile nav partials apply per item.
    $snippet = '{{ $child_site_href = {child_site_url} }}'
        .'{{ $category_href = {posts_category_link_url} }}'
        .'{{ $nav_item_href = $child_site_href ? $child_site_href : ( $category_href ? $category_href : url ) }}'
        .'{{ $nav_item_href }}';

    // A posts_category_link item (category set) links to the category archive,
    // NOT the nav page's own permalink.
    $categoryItem = (string) Antlers::parse($snippet, ['category' => 'cat-cardiology', 'url' => '/the-nav-page']);
    expect(trim($categoryItem))->toBe($categoryUrl)
        ->and(trim($categoryItem))->not->toBe('/the-nav-page');

    // A plain page (no category) still uses its own URL.
    $plainItem = (string) Antlers::parse($snippet, ['url' => '/a-normal-page']);
    expect(trim($plainItem))->toBe('/a-normal-page');
});
