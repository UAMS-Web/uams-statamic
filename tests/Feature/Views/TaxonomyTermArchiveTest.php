<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Statamic\Facades\Antlers;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Structures\CollectionStructure;
use Statamic\Structures\Tree;
use Tests\Concerns\RequiresContentSubmodule;
use Tests\Support\CollectionTreeIsolatedTestCase;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(CollectionTreeIsolatedTestCase::class, RequiresContentSubmodule::class);

const POST_TAGS_ARCHIVE_TEST_PREFIX = 'zz-posttags-archive-test-';

/** Remove every self-provisioned post_tags and posts test entry (keeps the 0-entries gates valid). */
function tearDownPostTagsArchiveFixtures(): void
{
    // Content-less checkouts have no post_tags collection, so querying its entries
    // is pointless; this file's hermetic (content-independent) tests must still run there.
    if (! CollectionFacade::find('post_tags')) {
        return;
    }

    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->whereIn('collection', ['post_tags', 'posts']);

    /** @var Collection<int, Statamic\Entries\Entry> $tags */
    $tags = $query->get();

    $tags
        ->filter(fn (Statamic\Entries\Entry $tag): bool => is_string($tag->slug()) && str_starts_with($tag->slug(), POST_TAGS_ARCHIVE_TEST_PREFIX))
        // Delete *quietly* — skip the EntryDeleted search-reindex cascade the test
        // `sync` queue would otherwise run inline; see the provisioning in the render
        // test for the full rationale.
        ->each(fn (Statamic\Entries\Entry $tag) => $tag->deleteQuietly());
}

/**
 * Coverage for the tag archive pages (UAMS-Web/uams-statamic-archive#1145 route + view; UAMS-Web/uams-statamic-archive#1171 empty tag; UAMS-Web/uams-statamic-archive#1172
 * populated tag). The descriptive, tag-named title/`<h1>` come from the shared
 * `taxonomies/_term_heading` partial, which the layout `<title>` reuses.
 *
 * Both archives that used to be taxonomy term pages are collection entries now.
 * `post_categories` migrated first (UAMS-Web/uams-statamic-archive#1433) and is covered by PostCategoryArchiveTest
 * / PostCategoryArchiveRenderTest. `post_tags` followed as a site-scoped structured
 * collection (UAMS-Web/uams-statamic-archive#2532), still routed at `/post-tags/{slug}`, and anchors the route
 * render here. An entry page carries no `taxonomy` cascade variable, so the layout
 * has an explicit `post_tags` branch for the title, as it does for categories.
 *
 * Cost note: the dominant cost of this file was never the render (~2.5s) but the
 * term lifecycle — a normal save/delete fires the `ShouldQueue` search-index
 * listeners inline under the test `sync` queue, ~170s per term across the 255-site
 * `collection:*` index set. This test provisions the term with `saveQuietly()` /
 * `deleteQuietly()` to skip that cascade (nothing it asserts is search-driven), and
 * keeps exactly ONE end-to-end HTTP render as the integration anchor that catches a
 * missing template or a broken route (the original UAMS-Web/uams-statamic-archive#1145 bug). Every other assertion
 * (populated listing, tag phrasing, term-title escaping, and the layout `<title>`
 * non-term branch) is exercised hermetically via `Antlers::parse`, following the
 * LayoutMarkupAttributesTest pattern: render the logic in isolation and guard the
 * real layout's wiring at the source, because the full layout cannot render
 * headlessly.
 *
 * @command  php artisan test --compact tests/Feature/Views/TaxonomyTermArchiveTest.php
 */
it('renders a self-provisioned post_tags archive with a descriptive title, heading, and empty state', function (): void {
    if (! CollectionFacade::find('post_tags')) {
        ContentSubmodule::reportMissing('post_tags collection config');
    }

    // Clean any entry a prior crashed run may have left behind before provisioning,
    // so the empty-state assertion below sees a genuinely empty tag. Only this test
    // provisions a post_tags entry, so the teardown is scoped here (via the finally
    // below) rather than paid in a global before/afterEach on every test.
    tearDownPostTagsArchiveFixtures();

    try {
        // The shipping content submodule has zero post_tags entries, so self-provision
        // one (rather than skip) to exercise the only end-to-end HTTP render of a tag
        // archive through the full layout — incl. the layout's `post_tags`
        // descriptive-title branch.
        /** @var Statamic\Entries\Entry $tag */
        $tag = Entry::make();
        $tag->collection('post_tags');
        $tag->locale('default');
        $tag->slug(POST_TAGS_ARCHIVE_TEST_PREFIX.'immunology');
        $tag->data(['title' => 'ZZ Immunology Tag']);
        // Save *quietly*. A normal save/delete fires EntrySaved/EntryDeleted, whose
        // Statamic search-index listener (UpdateItemIndexes) is `ShouldQueue` — async
        // in production, but run inline under the test `sync` queue, where rebuilding
        // the `collection:*` local index across the site index set costs minutes per
        // lifecycle. This render reads the Stache (entry data + the posts query),
        // never the search index, so skipping those events changes nothing it asserts.
        $tag->saveQuietly();

        // A structured collection routes only the entries in its tree, so place the
        // tag there. This file's base re-roots the tree store to a throwaway directory.
        /** @var Statamic\Entries\Collection $tagCollection */
        $tagCollection = CollectionFacade::findByHandle('post_tags');
        /** @var CollectionStructure $tagStructure */
        $tagStructure = $tagCollection->structure();
        /** @var Tree $tagTree */
        $tagTree = $tagStructure->in('default');
        $tagTree->tree([['entry' => $tag->id()]]);
        $tagTree->save();

        $this->withoutVite();

        /** @var TestCase $test */
        $test = $this;

        /** @var string $url */
        $url = $tag->url();

        // Same URL shape the taxonomy term page had, so existing links still resolve.
        expect($url)->toEndWith('/post-tags/'.POST_TAGS_ARCHIVE_TEST_PREFIX.'immunology');

        $html = $test->get($url)->assertOk()->getContent();

        // A test-authored plain title lets us match the full descriptive phrasing (no
        // strip_tags|entities surprise). The two assertions target distinct sinks — the
        // <head> <title> (the layout's `post_tags` branch) and the page <h1> — so
        // neither subsumes the other.
        expect($html)
            // Descriptive, tag-keyed <head> title (not a bare " | {site}") — UAMS-Web/uams-statamic-archive#1171.
            ->toContain('<title>Posts Tagged with ZZ Immunology Tag')
            // Descriptive <h1> naming the taxonomy — UAMS-Web/uams-statamic-archive#1171.
            ->toContain('<h1 class="page-title">Posts Tagged with ZZ Immunology Tag')
            // No posts under the term, so the archive renders its empty state end-to-end.
            ->toContain('There are no posts here yet');

        // Populated: a post carrying the tag is listed through `posts_in_post_tag`,
        // and a post without it is not.
        foreach (['tagged' => [$tag->id()], 'untagged' => []] as $suffix => $tagIds) {
            /** @var Statamic\Entries\Entry $post */
            $post = Entry::make();
            $post->collection('posts');
            $post->locale('default');
            $post->published(true);
            $post->date('2026-02-01');
            $post->slug(POST_TAGS_ARCHIVE_TEST_PREFIX.$suffix);
            $post->data(['title' => 'ZZ Archive Post '.ucfirst($suffix), 'post_tags' => $tagIds]);
            $post->saveQuietly();
        }

        $populated = $test->get($url)->assertOk()->getContent();

        expect($populated)
            ->toContain('ZZ Archive Post Tagged')
            ->not->toContain('ZZ Archive Post Untagged')
            ->not->toContain('There are no posts here yet');
    } finally {
        tearDownPostTagsArchiveFixtures();
    }
})->group('serial');

/**
 * Render the shared term-archive listing partial with the given scope.
 *
 * @param  array<string, mixed>  $scope
 */
function renderTermArchivePosts(array $scope): string
{
    return (string) Antlers::parse('{{ partial:taxonomies/term_archive_posts }}', $scope);
}

it('lists the term posts as cards when the term has posts (#1172)', function (): void {
    $html = renderTermArchivePosts([
        'no_results' => false,
        'posts' => [
            ['title' => 'First Study', 'url' => '/2026/01/02/first-study', 'date' => '2026-01-02', 'blueprint' => 'post'],
            ['title' => 'Second Study', 'url' => '/2026/01/03/second-study', 'date' => '2026-01-03', 'blueprint' => 'post'],
        ],
    ]);

    expect($html)
        ->toContain('First Study')
        ->toContain('Second Study')
        ->toContain('itemtype="https://schema.org/ItemList"');
    expect($html)->not->toContain('There are no posts here yet');
});

it('shows the empty state when the term has no posts (#1171)', function (): void {
    $html = renderTermArchivePosts(['no_results' => true, 'posts' => []]);

    expect($html)->toContain('There are no posts here yet');
    expect($html)->not->toContain('schema.org/ItemList');
});

it('strips and encodes an arbitrary term title so it cannot inject markup', function (): void {
    // The term title is arbitrary editor input; the shared heading must strip tags
    // and entity-encode it before it lands in the <h1> (and, identically, the <title>).
    $html = (string) Antlers::parse(
        '{{ partial:taxonomies/term_archive }}listing{{ /partial:taxonomies/term_archive }}',
        [
            'taxonomy' => 'post_categories',
            'title' => 'Cardiology <script>alert(1)</script>',
            'site' => ['name' => 'UAMS Health'],
        ]
    );

    expect($html)
        ->toContain('Posts in Category Cardiology')
        // The slot (the listing) still renders.
        ->toContain('listing');

    // The raw <script> must not survive into the rendered markup.
    expect($html)->not->toContain('<script>alert(1)</script>');
});

it('wires the shared heading and the post_tags show template to its taxonomy and phrasing', function (): void {
    $heading = File::get(resource_path('views/taxonomies/_term_heading.antlers.html'));
    $tag = File::get(resource_path('views/post_tags/show.antlers.html'));

    // The phrasing + escaping live in one shared partial, keyed off the taxonomy.
    // The heading still carries both phrasings: the migrated `post_categories`
    // *collection* archive reuses this same partial for its "Posts in Category X"
    // heading (asserted in PostCategoryArchiveTest), while `post_tags` uses it here.
    expect($heading)
        ->toContain('Posts in Category ')
        ->toContain('Posts Tagged with ')
        ->toContain('strip_tags')
        ->toContain('entities');

    // `post_tags` is a collection since UAMS-Web/uams-statamic-archive#2532: its archive passes `taxonomy`
    // explicitly (an entry page has no `taxonomy` cascade variable) and lists posts
    // through the id-based reverse-lookup scope. `post_categories`' wiring is
    // asserted in PostCategoryArchiveTest.
    expect($tag)
        ->toContain('partial:taxonomies/term_archive taxonomy="post_tags"')
        ->toContain('query_scope="posts_in_post_tag" tag="{{ id }}"')
        ->not->toContain(':taxonomy:post_tags');
});

/**
 * Mirror of the layout `<title>` branch (resources/views/layout.antlers.html),
 * rendered in isolation. The full layout pulls in vite/schema/admin-bar tags that
 * cannot render headlessly, so — following LayoutMarkupAttributesTest — the branch
 * logic is mirrored here and guarded against the real source below. The four-way
 * branch: a term page (`taxonomy` in scope) → the descriptive term heading; a
 * `post_categories` collection entry → the category heading; a `post_tags`
 * collection entry → the tag heading (UAMS-Web/uams-statamic-archive#2532); every other page → the entry title,
 * escaped.
 *
 * @param  array<string, mixed>  $scope
 */
function renderLayoutTitle(array $scope): string
{
    $branch = <<<'ANT'
{{ if taxonomy }}{{ $h = {partial:taxonomies/term_heading} /}}<title>{{ $h | trim }} | {{ site:name }}</title>{{ elseif collection:handle == 'post_categories' }}{{ $c = {partial:taxonomies/term_heading taxonomy="post_categories"} /}}<title>{{ $c | trim }} | {{ site:name }}</title>{{ elseif collection:handle == 'post_tags' }}{{ $t = {partial:taxonomies/term_heading taxonomy="post_tags"} /}}<title>{{ $t | trim }} | {{ site:name }}</title>{{ else }}<title>{{ ( document_title ?? title ?? site:name ) | strip_tags | entities }}</title>{{ /if }}
ANT;

    return trim((string) Antlers::parse($branch, $scope, true));
}

it('renders a normal page title on the non-term branch, escaping editor input (regression)', function (): void {
    // The layout <title> branches on the term-only `taxonomy` variable (UAMS-Web/uams-statamic-archive#1171). A
    // non-term, non-category page must take the `{{ else }}` branch and render its
    // own entry title — the pre-#1171 behavior — never a term phrasing. (The taxonomy
    // and category branches are covered end-to-end by the term render above and by
    // PostCategoryArchiveRenderTest respectively.)
    $normal = renderLayoutTitle([
        'title' => 'About UAMS',
        'site' => ['name' => 'UAMS Health'],
    ]);

    // Each `->not->toContain()` is its own `expect()` — chaining a `->not` after a
    // `->toContain()` breaks Pest's return-type inference under PHPStan level 10.
    expect($normal)->toContain('<title>About UAMS</title>');
    expect($normal)->not->toContain('Posts Tagged with ');
    expect($normal)->not->toContain('Posts in Category ');

    // The else branch is a raw-by-default sink for editor/importer `title` input, so
    // it must strip tags + entity-encode there too (no test-title break-out).
    $hostile = renderLayoutTitle([
        'title' => 'Onc & Heme </title><script>alert(1)</script>',
        'site' => ['name' => 'UAMS Health'],
    ]);

    expect($hostile)->toContain('Onc &amp; Heme');
    expect($hostile)->not->toContain('<script>alert(1)</script>');
    expect($hostile)->not->toContain('</title><script>');

    // Guard the mirror against the real layout: the term-only `{{ if taxonomy }}`
    // gate and the escaped entry-title else branch must still be wired in source, so
    // a divergence between this mirror and the layout is caught.
    $layout = File::get(resource_path('views/layout.antlers.html'));
    expect($layout)
        ->toContain('{{ if taxonomy }}')
        ->toContain("{{ elseif collection:handle == 'post_tags' }}")
        ->toContain('{partial:taxonomies/term_heading taxonomy="post_tags"}')
        ->toContain('( document_title ?? title ?? site:name ) | strip_tags | entities');
});

it('renders the tag heading as the title of a post_tags entry page, escaping its title', function (): void {
    // A `post_tags` entry page has no `taxonomy` cascade variable, so without its own
    // branch it would fall through to the bare entry title (UAMS-Web/uams-statamic-archive#2532).
    $tag = renderLayoutTitle([
        'collection' => ['handle' => 'post_tags'],
        'title' => 'Immunology <script>alert(1)</script>',
        'site' => ['name' => 'UAMS Health'],
    ]);

    expect($tag)->toContain('<title>Posts Tagged with Immunology');
    expect($tag)->not->toContain('<script>alert(1)</script>');
});
