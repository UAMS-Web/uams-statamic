<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Facades\Antlers;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Structures\CollectionStructure;
use Statamic\Structures\Page;
use Statamic\Structures\Pages;
use Statamic\Structures\Tree;
use Tests\Support\StacheIsolatedTestCase;

uses(StacheIsolatedTestCase::class);

/**
 * Coverage for the UAMS-Web/uams-statamic-archive#1434 rework of the `post_categories` archive onto the
 * structured-collection model: the hierarchical surface (breadcrumb parent chain
 * + child-category list) layered on the UAMS-Web/uams-statamic-archive#1433 collection-sourced listing, reusing
 * the UAMS-Web/uams-statamic-archive#1145 term-archive presentation. The tree/ancestry mechanics are proven
 * hermetically ({@see StacheIsolatedTestCase}); the template wiring and the
 * untouched `post_tags` surface are asserted against the app-repo files.
 *
 * @command  php artisan test --compact tests/Feature/PostCategoryArchiveTest.php
 */

/**
 * Build a parent category with two child categories (parent recorded in data,
 * like the importer/CP writes it) and the matching structure tree.
 */
function seedCategoryHierarchy(): void
{
    /** @var Statamic\Entries\Collection $categories */
    $categories = Collection::make('post_categories');
    $categories->routes("{{ depth == 1 ?= 'category' }}/{{ parent_uri }}/{{ slug }}");
    $categories->structureContents(['root' => false]);
    $categories->save();

    /** @var Statamic\Entries\Entry $parent */
    $parent = Entry::make();
    $parent->collection('post_categories');
    $parent->id('pc-parent');
    $parent->slug('sciences');
    $parent->data(['title' => 'Sciences']);
    $parent->save();

    /** @var Statamic\Entries\Entry $biology */
    $biology = Entry::make();
    $biology->collection('post_categories');
    $biology->id('pc-biology');
    $biology->slug('biology');
    $biology->data(['title' => 'Biology', 'parent' => 'pc-parent']);
    $biology->save();

    /** @var Statamic\Entries\Entry $chemistry */
    $chemistry = Entry::make();
    $chemistry->collection('post_categories');
    $chemistry->id('pc-chemistry');
    $chemistry->slug('chemistry');
    $chemistry->data(['title' => 'Chemistry', 'parent' => 'pc-parent']);
    $chemistry->save();

    /** @var Statamic\Entries\Collection $structuredCategories */
    $structuredCategories = Collection::find('post_categories');
    /** @var CollectionStructure $structure */
    $structure = $structuredCategories->structure();
    /** @var Tree $tree */
    $tree = $structure->makeTree('default', [
        ['entry' => 'pc-parent', 'children' => [
            ['entry' => 'pc-biology'],
            ['entry' => 'pc-chemistry'],
        ]],
    ]);
    $tree->save();
}

/**
 * The direct child page ids of a category in the collection structure.
 *
 * @return array<int, string>
 */
function categoryChildIds(string $parentId): array
{
    /** @var Statamic\Entries\Collection $categories */
    $categories = Collection::find('post_categories');
    /** @var CollectionStructure $structure */
    $structure = $categories->structure();
    /** @var Tree $tree */
    $tree = $structure->in('default');
    /** @var Illuminate\Support\Collection<int, Page> $flattenedPages */
    $flattenedPages = $tree->flattenedPages();

    $page = collect($flattenedPages)->first(fn (Page $p): bool => $p->id() === $parentId);

    if ($page === null) {
        return [];
    }

    /** @var Pages $childPages */
    $childPages = $page->pages();
    /** @var Illuminate\Support\Collection<int, Page> $children */
    $children = $childPages->all();

    return collect($children)
        ->map(function (Page $child): string {
            /** @var string $id */
            $id = $child->id();

            return $id;
        })
        ->sort()
        ->values()
        ->all();
}

it('surfaces a category\'s immediate child categories from the structure', function () {
    seedCategoryHierarchy();

    // The archive's `:parent:is="id"` query resolves the parent's immediate
    // children off this same structure. (The `where('parent')` form is proven on
    // disk-loaded content by KnowledgeBaseDirectChildCountTest; in the in-memory
    // Stache the structure is the reliable read, and the end-to-end render test
    // exercises the real `:parent:is` query path.)
    expect(categoryChildIds('pc-parent'))->toBe(['pc-biology', 'pc-chemistry']);
});

it('shows no child categories for a leaf category', function () {
    seedCategoryHierarchy();

    expect(categoryChildIds('pc-biology'))->toBe([]);
});

it('exposes the parent chain the breadcrumbs walk (structure ancestry)', function () {
    seedCategoryHierarchy();

    // The global header's nav:breadcrumbs walks a structured entry's parent chain;
    // proving the child resolves its parent is proving the crumb ancestry (the flat
    // taxonomy had none). The nested URL in PostCategoriesCollectionTest is the same
    // structure viewed as a path.
    /** @var Statamic\Entries\Entry $biology */
    $biology = Entry::find('pc-biology');
    /** @var Statamic\Entries\Entry|Page|null $biologyParent */
    $biologyParent = $biology->parent();
    /** @var string|null $biologyParentId */
    $biologyParentId = $biologyParent?->id();
    expect($biologyParentId)->toBe('pc-parent');

    /** @var Statamic\Entries\Entry $parentCategory */
    $parentCategory = Entry::find('pc-parent');
    /** @var Statamic\Entries\Entry|Page|null $rootParent */
    $rootParent = $parentCategory->parent();
    expect($rootParent)->toBeNull();
});

it('preserves the shared empty state when a category has no posts', function () {
    // The category archive reuses the UAMS-Web/uams-statamic-archive#1145 listing partial, so an empty category
    // still renders the descriptive page with the "no posts" state.
    $html = (string) Antlers::parse(
        '{{ partial:taxonomies/term_archive_posts }}',
        ['no_results' => true, 'posts' => []]
    );

    expect($html)->toContain('There are no posts here yet');
    expect($html)->not->toContain('schema.org/ItemList');
});

it('wires the archive to the collection sources and the hierarchical surface', function () {
    $show = File::get(resource_path('views/post_categories/show.antlers.html'));

    expect($show)
        ->toContain('partial:taxonomies/term_archive')       // #1145 shell + descriptive heading + empty state
        ->toContain('query_scope="posts_in_post_category"')  // posts via the entries reference
        ->toContain('sort="date:desc"')                      // category posts newest-first
        ->toContain('paginate="10"')                         // 10 posts per page
        ->toContain('collection:post_categories')            // child categories
        ->toContain(':parent:is="id"')                       // immediate structure children
        // Child-category titles are editor/importer input; Antlers is raw-by-default,
        // so the title must be stripped + entity-encoded (no raw markup can inject).
        ->toContain('title | strip_tags | entities');
});

it('keeps the shared term-archive partials, which the post_tags archive reuses too', function () {
    // UAMS-Web/uams-statamic-archive#1433 left tags a flat taxonomy; UAMS-Web/uams-statamic-archive#2532 converted them to a collection whose
    // archive reuses the same shared partials, keyed by an explicit `taxonomy`.
    $tags = File::get(resource_path('views/post_tags/show.antlers.html'));

    expect($tags)->toContain('partial:taxonomies/term_archive taxonomy="post_tags"')
        ->toContain('query_scope="posts_in_post_tag"')
        ->not->toContain(':taxonomy:post_tags');

    foreach (['_term_archive', '_term_archive_posts', '_term_heading'] as $partial) {
        expect(File::exists(resource_path("views/taxonomies/{$partial}.antlers.html")))->toBeTrue();
    }
});
