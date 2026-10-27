<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Structures\CollectionStructure;
use Statamic\Structures\Tree;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Coverage for the `{{ posts_category_link_url }}` tag (UAMS-Web/uams-statamic-archive#220): in the primary-nav
 * loop it resolves a `posts_category_link` item's selected post-category (the
 * `category` field in context) to its archive URL, and returns an empty string
 * for a normal page so the nav falls back to the page's own URL. Mirrors
 * ChildSiteUrlTagTest for the UAMS-Web/uams-statamic-archive#219 child_site_link precedent.
 *
 * The routed, structured `post_categories` collection is built on an isolated
 * Stache ({@see StacheIsolatedTestCase}), so this runs without the content/
 * submodule and never touches committed content.
 *
 * @covers \App\Tags\PostsCategoryLinkUrl
 *
 * @command  php artisan test --compact tests/Feature/Tags/PostsCategoryLinkUrlTagTest.php
 */
uses(StacheIsolatedTestCase::class);

beforeEach(function (): void {
    /** @var Statamic\Entries\Collection $collection */
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

    /** @var Statamic\Entries\Collection $categoryCollection */
    $categoryCollection = Collection::find('post_categories');
    /** @var CollectionStructure $structure */
    $structure = $categoryCollection->structure();
    /** @var Tree $tree */
    $tree = $structure->makeTree('default', [['entry' => 'cat-cardiology']]);
    $tree->save();
});

it('resolves the selected category id in context to its archive URL', function (): void {
    /** @var Statamic\Entries\Entry|null $categoryEntry */
    $categoryEntry = Entry::find('cat-cardiology');
    $expected = $categoryEntry?->url();

    // The routed structured collection gives the category a real archive URL.
    expect($expected)->not->toBeNull();

    $out = (string) Antlers::parse('{{ posts_category_link_url }}', ['category' => 'cat-cardiology']);

    expect(trim($out))->toBe($expected);
});

it('returns an empty string when there is no category in context', function (): void {
    $out = (string) Antlers::parse('{{ posts_category_link_url }}', ['title' => 'A normal page']);

    expect(trim($out))->toBe('');
});

it('returns an empty string for an unknown category id', function (): void {
    $out = (string) Antlers::parse('{{ posts_category_link_url }}', ['category' => 'definitely-not-a-category']);

    expect(trim($out))->toBe('');
});

it('returns an empty string for an entry outside the post_categories collection', function (): void {
    // Defense-in-depth: only a post_categories entry is ever linked, even if some
    // other collection's entry id reaches the tag.
    /** @var Statamic\Entries\Collection $pages */
    $pages = Collection::make('pages');
    $pages->title('Pages');
    $pages->sites(['default']);
    $pages->save();

    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('pages');
    $entry->id('some-page');
    $entry->slug('a-page');
    $entry->published(true);
    $entry->data(['title' => 'A Page']);
    $entry->save();

    $out = (string) Antlers::parse('{{ posts_category_link_url }}', ['category' => 'some-page']);

    expect(trim($out))->toBe('');
});

it('resolves an already-augmented Entry object in context', function (): void {
    // An `entries` field augments to an Entry, so the nav-item context may hold the
    // Entry itself rather than an id string.
    /** @var Statamic\Entries\Entry|null $entry */
    $entry = Entry::find('cat-cardiology');
    expect($entry)->not->toBeNull();

    $out = (string) Antlers::parse('{{ posts_category_link_url }}', ['category' => $entry]);

    expect(trim($out))->toBe($entry?->url());
});

it('resolves the first category from an iterable of ids', function (): void {
    $out = (string) Antlers::parse('{{ posts_category_link_url }}', ['category' => ['cat-cardiology']]);

    /** @var Statamic\Entries\Entry|null $category */
    $category = Entry::find('cat-cardiology');
    expect(trim($out))->toBe($category?->url());
});
