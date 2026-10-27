<?php

declare(strict_types=1);

namespace App\Scopes;

use Statamic\Entries\Entry as EntryObject;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Query\Builder;
use Statamic\Query\Scopes\Scope;
use Statamic\Sites\Site as SiteObject;
use Statamic\Stache\Query\EntryQueryBuilder;

/**
 * Query scope restricting a `posts` query to the posts filed under the
 * `post_categories` collection entry identified by a category *slug* — the shape
 * the posts listing's optional `?category=<slug>` filter needs.
 *
 * UAMS-Web/uams-statamic-archive#1433 migrated `post_categories` from a flat taxonomy to a structured
 * collection, so the listing ({@see resources/views/posts/_listing.antlers.html})
 * can no longer narrow by a taxonomy term. This scope resolves the query-string
 * slug to its category entry on the current site, then filters posts by that
 * entry id through the same `whereJsonContains('post_categories', id)` reverse
 * lookup as {@see PostsInPostCategory} (which the `post_categories/show` archive
 * uses with an already-known id).
 *
 * Two edge cases match the listing's filter contract:
 *   - A blank/absent slug adds NO constraint, so a `?category=`-less request lists
 *     every post. (The id-based {@see PostsInPostCategory} instead lists nothing on
 *     a blank id: the archive always supplies an id, whereas this filter is
 *     optional.)
 *   - A slug that resolves to no category on the current site filters by a null
 *     id, which intersects no post, so a stale/unknown `?category=` lists nothing —
 *     as the retired taxonomy filter did for an unknown term.
 *
 * Invoked from the listing as
 * `{{ collection:posts query_scope="posts_in_post_category_by_slug" category_slug="{{ $filter_category }}" }}`.
 */
class PostsInPostCategoryBySlug extends Scope
{
    /**
     * Constrain the query to posts filed under the category identified by the
     * given slug on the current site; a blank slug leaves the query unconstrained.
     *
     * @param  Builder  $query  Entry query builder for the `posts` collection
     * @param  array<string, mixed>  $values  Antlers tag parameters; `category_slug` carries the category slug from `?category=`
     */
    public function apply(
        $query,
        $values
    ): void {
        $slug = $values['category_slug'] ?? null;

        // An absent/blank filter is a no-op: the unfiltered listing lists every post.
        if (! is_string($slug) || $slug === '') {
            return;
        }

        /** @var SiteObject $site */
        $site = Site::current();

        // Resolve the slug to its category entry on the current site.
        /** @var EntryQueryBuilder $categoryQuery */
        $categoryQuery = Entry::query();
        $categoryQuery->where('collection', 'post_categories');
        $categoryQuery->where('site', $site->handle());
        $categoryQuery->where('slug', $slug);

        /** @var EntryObject|null $category */
        $category = $categoryQuery->first();

        // An unknown slug yields a null id, which intersects no post (empty listing).
        $query->whereJsonContains('post_categories', $category?->id());
    }
}
