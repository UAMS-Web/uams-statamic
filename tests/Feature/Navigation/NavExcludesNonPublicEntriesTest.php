<?php

declare(strict_types=1);

use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Nav;
use Statamic\Structures\CollectionStructure;
use Statamic\Structures\Tree;
use Statamic\Tags\Nav as NavTag;
use Tests\Support\StacheIsolatedTestCase;

uses(StacheIsolatedTestCase::class);

/**
 * Functional coverage for excluding non-public entries from the site's
 * automatically generated primary navigation (UAMS-Web/uams-statamic-archive#223).
 *
 * `private` and `password_protected` entries stay Statamic-`published` (they are
 * live, just access-restricted), so unlike `draft` / `pending` they are NOT
 * removed by the nav tag's status filter. The `desktop.antlers.html` /
 * `mobile.antlers.html` `{{ nav }}` call adds explicit
 * `status_and_visibility:isnt="private" password_protected:isnt="true"` (plus the
 * pre-existing `hide_from_menu:isnt="true"`) to drop them.
 *
 * These tests build a throwaway structured collection and a manual nav on an
 * isolated Stache ({@see StacheIsolatedTestCase}), so they run without the
 * content/ submodule and never touch committed content. They drive the **real**
 * `Statamic\Tags\Nav` tag —
 * the exact class the layout invokes — via `index()` rather than an
 * `Antlers::parse('{{ nav }}')` render (the tag does not execute standalone in a
 * bare parse; the same reason `SubpagesNavFloatClearTest` tests the subpages
 * partial's source wiring instead of rendering it). Driving the tag is what
 * proves the exclusion; a source-string assertion alone would stay green even if
 * the tag silently ignored the params.
 *
 * Scope: the tag applies these conditions on the default `collection::pages`
 * path (via `Structure::queryForCollection` → `queryConditions`). On a manual
 * `primary_navigation` Nav — `Structure::queryForNav`, which skips
 * `queryConditions` — the params are ignored; that tag-level gap is characterized
 * below. It is closed at the template level by the desktop/mobile `{{ unless }}`
 * guard (UAMS-Web/uams-statamic-archive#1151), whose behavior is proven in `NavPrimaryNavExclusionTest`.
 *
 * @command  php artisan test --compact tests/Feature/Navigation/NavExcludesNonPublicEntriesTest.php
 */

/**
 * Build a public / private / password-protected / hidden-from-menu set of
 * sibling pages in a throwaway structured collection, plus a manual nav
 * referencing the same four. Fixtures are removed in `afterEach`.
 */
function navExclusionBuildFixture(): void
{
    $collection = Collection::make('nav_exclusion_pages');
    $collection->routes('{slug}');
    $collection->structureContents(['root' => true]);
    $collection->save();

    $entries = [
        ['id' => 'nav-excl-root', 'slug' => 'home', 'title' => 'Home', 'data' => ['status_and_visibility' => 'published']],
        ['id' => 'nav-excl-public', 'slug' => 'public', 'title' => 'Public Page', 'data' => ['status_and_visibility' => 'published']],
        ['id' => 'nav-excl-private', 'slug' => 'private', 'title' => 'Private Page', 'data' => ['status_and_visibility' => 'private']],
        ['id' => 'nav-excl-password', 'slug' => 'password', 'title' => 'Password Page', 'data' => ['status_and_visibility' => 'published', 'password_protected' => true]],
        ['id' => 'nav-excl-hidden', 'slug' => 'hidden', 'title' => 'Hidden Page', 'data' => ['status_and_visibility' => 'published', 'hide_from_menu' => true]],
    ];

    foreach ($entries as $entry) {
        /** @var Statamic\Entries\Entry $entryModel */
        $entryModel = Entry::make();
        $entryModel->collection('nav_exclusion_pages');
        $entryModel->id($entry['id']);
        $entryModel->slug($entry['slug']);
        $entryModel->published(true);
        $entryModel->data(array_merge(['title' => $entry['title']], $entry['data']));
        $entryModel->save();
    }

    // The four top-level menu items, in the order the nav renders them.
    $items = [
        ['entry' => 'nav-excl-public'],
        ['entry' => 'nav-excl-private'],
        ['entry' => 'nav-excl-password'],
        ['entry' => 'nav-excl-hidden'],
    ];

    // A `root => true` structure (like the real `pages`) puts the root/home at
    // tree[0]; tree[1..] are the top-level items the nav renders.
    /** @var Statamic\Entries\Collection $navCollection */
    $navCollection = Collection::find('nav_exclusion_pages');
    /** @var CollectionStructure $navStructure */
    $navStructure = $navCollection->structure();
    /** @var Tree $navTree */
    $navTree = $navStructure->makeTree('default', array_merge([['entry' => 'nav-excl-root']], $items));
    $navTree->save();

    /** @var Statamic\Structures\Nav $nav */
    $nav = Nav::make('nav_exclusion_manual');
    $nav->title('Nav Exclusion Manual');
    $nav->save();
    // A manual nav has no root; the four pages are the top-level items directly.
    /** @var Tree $navManualTree */
    $navManualTree = $nav->makeTree('default', $items);
    $navManualTree->save();
}

/**
 * Drive the real `{{ nav }}` tag against a structure handle and return the
 * rendered top-level item titles, optionally applying the layout's exclusion
 * filter params. Uses the tag's `index()` — the same code path the layout hits.
 *
 * @return list<string>
 */
function navExclusionTitles(string $handle, bool $applyFilters): array
{
    $params = ['handle' => $handle];

    if ($applyFilters) {
        // Identical to the desktop/mobile layout's `{{ nav }}` params, so this
        // breaks if the layout and this drift apart.
        $params['hide_from_menu:isnt'] = 'true';
        $params['status_and_visibility:isnt'] = 'private';
        $params['password_protected:isnt'] = 'true';
    }

    $tag = new NavTag;
    $tag->setContext([]);
    $tag->setParameters($params);

    $pages = $tag->index();

    return array_values(
        collect(is_array($pages) ? $pages : [])
            ->map(function ($page): string {
                $title = is_array($page) ? ($page['title'] ?? null) : null;

                return match (true) {
                    is_string($title) => $title,
                    $title instanceof Stringable => (string) $title,
                    default => '',
                };
            })
            ->all()
    );
}

beforeEach(function (): void {
    navExclusionBuildFixture();
});

afterEach(function (): void {
    foreach (['nav-excl-root', 'nav-excl-public', 'nav-excl-private', 'nav-excl-password', 'nav-excl-hidden'] as $id) {
        /** @var Statamic\Entries\Entry|null $found */
        $found = Entry::find($id);
        $found?->delete();
    }

    /** @var Statamic\Structures\Nav|null $manualNav */
    $manualNav = Nav::findByHandle('nav_exclusion_manual');
    $manualNav?->delete();
    Collection::find('nav_exclusion_pages')?->delete();
});

it('excludes private, password-protected and hidden entries from the collection-backed primary nav', function (): void {
    $titles = navExclusionTitles('collection::nav_exclusion_pages', applyFilters: true);

    expect($titles)->toBe(['Public Page']);
});

it('renders every entry on the collection-backed nav without the filter params', function (): void {
    // The false-green guard: proves the fixture surfaces all four in the nav, so
    // the exclusions above are the filter's doing and not an empty/broken tree.
    $titles = navExclusionTitles('collection::nav_exclusion_pages', applyFilters: false);

    expect($titles)->toBe(['Public Page', 'Private Page', 'Password Page', 'Hidden Page']);
});

it('does not filter a manual nav via the tag params (core limitation, closed by the template guard in #1151)', function (): void {
    // Characterizes Statamic's `Structure::queryForNav`, which — unlike
    // `queryForCollection` — ignores `field:isnt` conditions. On the manual
    // `primary_navigation` path the params are inert, so the tag alone still leaks
    // non-public entries; the desktop/mobile `{{ unless }}` guard (UAMS-Web/uams-statamic-archive#1151) is what
    // actually excludes them there (see NavPrimaryNavExclusionTest). If a future
    // Statamic release starts applying conditions to navs, this test flips.
    $titles = navExclusionTitles('nav_exclusion_manual', applyFilters: true);

    expect($titles)->toBe(['Public Page', 'Private Page', 'Password Page', 'Hidden Page']);
});

it('wires desktop and mobile to query-filter hidden/password and template-guard private', function (): void {
    // UAMS-Web/uams-statamic-archive#223 filtered all three at the `{{ nav }}` query. UAMS-Web/uams-statamic-archive#1151/UAMS-Web/uams-statamic-archive#1152 moved the work
    // into a template `{{ unless }}` guard (behavior proven in
    // NavPrimaryNavExclusionTest): `hide_from_menu` / `password_protected` stay
    // query-filtered for the collection path, but `private` is dropped from the
    // query so an admin/editor sees it via the guard's per-item permission check.
    foreach (['main/desktop', 'main/mobile'] as $view) {
        $markup = (string) file_get_contents(
            resource_path("views/layout/navigation/{$view}.antlers.html")
        );

        expect($markup)
            ->toContain('hide_from_menu:isnt="true"')
            ->toContain('password_protected:isnt="true"')
            // The per-item guard that closes the manual-nav leak and reveals private
            // to privileged users.
            ->toContain('can_view_private_entry :id="entry_id"');

        // `private` is no longer filtered at the query (it is template-guarded).
        expect($markup)->not->toContain('status_and_visibility:isnt="private"');
    }
});

it('guards non-public children in every subpages unless-block', function (): void {
    // `_subpages.antlers.html` carries the exclusion guard in FOUR `{{ unless }}`
    // blocks — two visibility-counting loops and two item-rendering loops. A plain
    // `toContain` stays green if the guard is dropped from a render loop while a
    // counting loop keeps it (a leak to guests), so assert it appears in all four.
    // If the block count legitimately changes, update this canary deliberately.
    $markup = (string) file_get_contents(
        resource_path('views/layout/navigation/subpages/_subpages.antlers.html')
    );

    expect(substr_count($markup, 'status_and_visibility == "private"'))->toBe(4)
        ->and(substr_count($markup, 'password_protected'))->toBe(4);
});
