<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Facades\Antlers;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\Concerns\RequiresContentSubmodule;
use Tests\Support\CollectionTreeIsolatedTestCase;
use Tests\Support\ContentSubmodule;

uses(CollectionTreeIsolatedTestCase::class, RequiresContentSubmodule::class);

const POSTS_LISTING_TEST_PREFIX = 'zz-posts-listing-test-';

/**
 * Feature coverage for UAMS-Web/uams-statamic-archive#569 (posts collection index listing) and the shared
 * listing partial reused by UAMS-Web/uams-statamic-archive#179's designated "Posts page".
 *
 * The card partial renders from a post's scope, so it is exercised hermetically
 * with synthetic post data. The listing partial's query/pagination wiring is
 * asserted against the template source (the same approach the empty-topic KB
 * test uses for its redirect guard), with a content-backed smoke render behind
 * RequiresContentSubmodule.
 *
 * @command  php artisan test --compact tests/Feature/PostsIndexListingTest.php
 */

/**
 * Render the post card partial with the given post scope.
 *
 * @param  array<string, mixed>  $post
 */
function renderPostCard(array $post): string
{
    return (string) Antlers::parse('{{ partial:posts/card }}', $post);
}

it('renders a post card with image, title, date, excerpt, and a show-page link', function (): void {
    $html = renderPostCard([
        'title' => 'Big Health News',
        'url' => '/2026/01/02/big-health-news',
        'date' => '2026-01-02',
        'excerpt' => 'A short summary of the story.',
        'blueprint' => 'post',
        'cover_image_query' => true,
        'featured_image_16_9' => [['url' => '/img/hero.jpg', 'alt' => 'Hero alt']],
        'cover_image_alt_text_override' => null,
        'alt' => 'Hero alt',
        'creator' => null,
    ]);

    expect($html)
        ->toContain('itemtype="https://schema.org/Article"')
        ->toContain('Big Health News')
        ->toContain('A short summary of the story.')
        ->toContain('<img src="/img/hero.jpg"')
        ->toContain('alt="Hero alt"')
        // The read-more link targets the post's own url, not the image url.
        ->toContain('href="/2026/01/02/big-health-news"')
        ->toContain('2026');

    // A standard post carries no post-type label.
    expect($html)->not->toContain('post-card-type');
});

it('labels a non-standard post type on its card', function (): void {
    $html = renderPostCard([
        'title' => 'Podcast Episode 5',
        'url' => '/2026/01/03/podcast-episode-5',
        'date' => '2026-01-03',
        'blueprint' => 'audio',
        'cover_image_query' => false,
        'creator' => null,
    ]);

    expect($html)
        ->toContain('post-card-type')
        ->toContain('Audio');

    // No cover image query, so no card image.
    expect($html)->not->toContain('<img');
});

it('wires the listing query, filtering, empty state, and pagination', function (): void {
    $template = File::get(resource_path('views/posts/_listing.antlers.html'));

    expect($template)
        // Site-scoped posts query, newest first, paginated.
        ->toContain('collection:posts')
        ->toContain('sort="date:desc"')
        // Bound with a colon, so the per-page count is a variable rather than the literal
        // string `$per_page` (UAMS-Web/uams-statamic-archive#2521). An unbound `paginate="$per_page"` is a substring of
        // the bound form, so the absence is asserted separately below.
        ->toContain(':paginate="$per_page"')
        // Narrowing by category/tag query params: `?category=` and `?tag=` each
        // resolve through their collection's slug scope (UAMS-Web/uams-statamic-archive#1483, UAMS-Web/uams-statamic-archive#2532), applied together.
        ->toContain('get:category')
        ->toContain('get:tag')
        ->toContain('query_scope="posts_in_post_category_by_slug|posts_in_post_tag_by_slug"')
        ->toContain('category_slug="{{ $filter_category }}"')
        ->toContain('tag_slug="{{ $filter_tag }}"')
        // A clear-filter affordance and an empty state.
        ->toContain('href="{{ current_url }}"')
        ->toContain('no_results')
        ->toContain('posts-empty')
        // Real pagination controls, gated on more than one page.
        ->toContain('paginate:total_pages > 1')
        ->toContain('paginate:prev_page')
        ->toContain('paginate:next_page');

    // Both retired taxonomy filters must be gone (each queried a now-nonexistent taxonomy).
    expect($template)->not->toContain(':taxonomy:post_categories')
        ->not->toContain(':taxonomy:post_tags');

    // No unbound `paginate` anywhere: every occurrence carries the colon.
    expect(preg_match('/(?<!:)paginate="\$per_page"/', $template))->toBe(0);
});

it('defaults to 10 posts per page and honors a positive override', function (): void {
    $template = File::get(resource_path('views/posts/_listing.antlers.html'));

    // Per-page override wins when positive; otherwise 10 (WordPress's default).
    expect($template)->toContain('( posts_per_page && posts_per_page > 0 ) ? posts_per_page : 10');
});

it('renders the listing structure without error against real content', function (): void {
    $this->requireContentCollection('posts');

    $html = (string) Antlers::parse('{{ partial:posts/listing }}', []);

    // The container row always renders; the tag executed and left no unparsed Antlers.
    expect($html)
        ->toContain('class="container-fluid"');

    expect($html)->not->toContain('{{');
});

it('renders only the given category\'s posts when filtered, and every post when not', function (): void {
    if (! Collection::findByHandle('post_categories') || ! Collection::findByHandle('posts')) {
        ContentSubmodule::reportMissing('post_categories + posts collections');
    }

    Site::setCurrent('default');

    // A behavioral render through the real listing template + the UAMS-Web/uams-statamic-archive#1483 scope: the
    // `filter_category` param drives the same path `?category=` does at runtime,
    // without the front-end `get:` cascade a partial render can't populate. Self-
    // provisions on the default site (PostCategoryArchiveRenderTest pattern) and
    // tears down by slug prefix.
    try {
        /** @var Statamic\Entries\Entry $cardiology */
        $cardiology = Entry::make();
        /** @var Statamic\Entries\Entry $cardiology */
        $cardiology = $cardiology->collection('post_categories');
        /** @var Statamic\Entries\Entry $cardiology */
        $cardiology = $cardiology->locale('default');
        /** @var Statamic\Entries\Entry $cardiology */
        $cardiology = $cardiology->slug(POSTS_LISTING_TEST_PREFIX.'cardiology');
        /** @var Statamic\Entries\Entry $cardiology */
        $cardiology = $cardiology->data(['title' => 'Cardiology']);
        $cardiology->save();

        /** @var Statamic\Entries\Entry $inCategory */
        $inCategory = Entry::make();
        /** @var Statamic\Entries\Entry $inCategory */
        $inCategory = $inCategory->collection('posts');
        /** @var Statamic\Entries\Entry $inCategory */
        $inCategory = $inCategory->locale('default');
        /** @var Statamic\Entries\Entry $inCategory */
        $inCategory = $inCategory->published(true);
        /** @var Statamic\Entries\Entry $inCategory */
        $inCategory = $inCategory->date('2026-02-01');
        /** @var Statamic\Entries\Entry $inCategory */
        $inCategory = $inCategory->slug(POSTS_LISTING_TEST_PREFIX.'in-cat');
        /** @var Statamic\Entries\Entry $inCategory */
        $inCategory = $inCategory->data(['title' => 'Heart Health Update', 'post_categories' => [$cardiology->id()]]);
        $inCategory->save();

        /** @var Statamic\Entries\Entry $other */
        $other = Entry::make();
        /** @var Statamic\Entries\Entry $other */
        $other = $other->collection('posts');
        /** @var Statamic\Entries\Entry $other */
        $other = $other->locale('default');
        /** @var Statamic\Entries\Entry $other */
        $other = $other->published(true);
        /** @var Statamic\Entries\Entry $other */
        $other = $other->date('2026-02-02');
        /** @var Statamic\Entries\Entry $other */
        $other = $other->slug(POSTS_LISTING_TEST_PREFIX.'other');
        /** @var Statamic\Entries\Entry $other */
        $other = $other->data(['title' => 'Unrelated Announcement']);
        $other->save();

        // Filtered by the category slug: only that category's post is rendered.
        $filtered = (string) Antlers::parse(
            '{{ partial:posts/listing filter_category="'.POSTS_LISTING_TEST_PREFIX.'cardiology" }}',
            []
        );
        expect($filtered)
            ->toContain('Heart Health Update');

        expect($filtered)->not->toContain('Unrelated Announcement');

        // No filter: every post should render. The omitted-parameter render is the
        // literal no-filter contract; it no longer needs the explicit-empty UAMS-Web/uams-statamic-archive#1565
        // workaround because `Tests\TestCase::setUp()` now resets the process-global
        // Antlers runtime between tests, so a prior test's warm `filter_category`
        // can no longer bleed into this parse (UAMS-Web/uams-statamic-archive#1567).
        $all = (string) Antlers::parse('{{ partial:posts/listing }}', []);
        expect($all)
            ->toContain('Heart Health Update')
            ->toContain('Unrelated Announcement');
    } finally {
        foreach (['posts', 'post_categories'] as $collection) {
            /** @var EntryQueryBuilder $query */
            $query = Entry::query();
            /** @var EntryQueryBuilder $query */
            $query = $query->where('collection', $collection);

            /** @var Illuminate\Support\Collection<int, Statamic\Entries\Entry> $entries */
            $entries = $query->get();

            $entries
                ->filter(function (Statamic\Entries\Entry $e): bool {
                    $slug = $e->slug();

                    return is_string($slug) && str_starts_with($slug, POSTS_LISTING_TEST_PREFIX);
                })
                ->each(fn (Statamic\Entries\Entry $e) => $e->delete());
        }
    }
})->group('serial');

it('renders only the given tag\'s posts when filtered, resolving the slug on the current site (#2532)', function (): void {
    if (! Collection::findByHandle('post_tags') || ! Collection::findByHandle('posts')) {
        ContentSubmodule::reportMissing('post_tags + posts collections');
    }

    Site::setCurrent('default');

    // The tag half of the category render above: `filter_tag` drives the same path
    // `?tag=` does, through `posts_in_post_tag_by_slug`. Quiet saves, because nothing
    // here reads a search index.
    try {
        /** @var Statamic\Entries\Entry $tag */
        $tag = Entry::make();
        $tag->collection('post_tags');
        $tag->locale('default');
        $tag->slug(POSTS_LISTING_TEST_PREFIX.'immunology');
        $tag->data(['title' => 'Immunology']);
        $tag->saveQuietly();

        foreach (['Immune Response Study' => [$tag->id()], 'Unrelated Announcement' => []] as $title => $tagIds) {
            /** @var Statamic\Entries\Entry $post */
            $post = Entry::make();
            $post->collection('posts');
            $post->locale('default');
            $post->published(true);
            $post->date('2026-02-01');
            $post->slug(POSTS_LISTING_TEST_PREFIX.str($title)->slug());
            $post->data(['title' => $title, 'post_tags' => $tagIds]);
            $post->saveQuietly();
        }

        $filtered = (string) Antlers::parse(
            '{{ partial:posts/listing filter_tag="'.POSTS_LISTING_TEST_PREFIX.'immunology" }}',
            []
        );
        expect($filtered)->toContain('Immune Response Study');
        expect($filtered)->not->toContain('Unrelated Announcement');

        // An unknown slug lists nothing and says so, rather than every post.
        $unknown = (string) Antlers::parse(
            '{{ partial:posts/listing filter_tag="'.POSTS_LISTING_TEST_PREFIX.'no-such-tag" }}',
            []
        );
        expect($unknown)
            ->toContain('for this filter')
            ->not->toContain('Immune Response Study');
    } finally {
        foreach (['posts', 'post_tags'] as $collection) {
            /** @var EntryQueryBuilder $query */
            $query = Entry::query();
            $query->where('collection', $collection);

            /** @var Illuminate\Support\Collection<int, Statamic\Entries\Entry> $entries */
            $entries = $query->get();

            $entries
                ->filter(fn (Statamic\Entries\Entry $e): bool => is_string($e->slug()) && str_starts_with($e->slug(), POSTS_LISTING_TEST_PREFIX))
                ->each(fn (Statamic\Entries\Entry $e) => $e->deleteQuietly());
        }
    }
})->group('serial');

it('pages the listing by posts_per_page, newest first', function (): void {
    if (! Collection::findByHandle('posts')) {
        ContentSubmodule::reportMissing('posts collection');
    }

    Site::setCurrent('default');

    // Three past-dated posts on the default site, which carries no other posts in
    // the test content, listed two to a page. An unbound `paginate` hands the tag
    // the literal string `$per_page` rather than 2, and every post lands on one
    // page with no pager (UAMS-Web/uams-statamic-archive#2521).
    try {
        foreach (['oldest' => '2026-02-01', 'middle' => '2026-02-02', 'newest' => '2026-02-03'] as $suffix => $date) {
            /** @var Statamic\Entries\Entry $post */
            $post = Entry::make();
            $post->collection('posts');
            $post->locale('default');
            $post->published(true);
            $post->date($date);
            $post->slug(POSTS_LISTING_TEST_PREFIX.'page-'.$suffix);
            $post->data(['title' => ucfirst($suffix).' paging post']);
            $post->save();
        }

        $html = (string) Antlers::parse('{{ partial:posts/listing posts_per_page="2" }}', []);

        expect($html)
            ->toContain('Page 1 of 2')
            ->toContain('Newest paging post')
            ->toContain('Middle paging post')
            ->not->toContain('Oldest paging post');
    } finally {
        /** @var EntryQueryBuilder $query */
        $query = Entry::query();
        $query->where('collection', 'posts');

        /** @var Illuminate\Support\Collection<int, Statamic\Entries\Entry> $entries */
        $entries = $query->get();

        $entries
            ->filter(function (Statamic\Entries\Entry $e): bool {
                $slug = $e->slug();

                return is_string($slug) && str_starts_with($slug, POSTS_LISTING_TEST_PREFIX);
            })
            ->each(fn (Statamic\Entries\Entry $e) => $e->delete());
    }
})->group('serial');
