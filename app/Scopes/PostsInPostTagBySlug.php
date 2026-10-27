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
 * Query scope restricting a `posts` query to the posts tagged with the `post_tags`
 * collection entry identified by a tag *slug* — the shape the posts listing's
 * optional `?tag=<slug>` filter needs.
 *
 * UAMS-Web/uams-statamic-archive#2532 converted `post_tags` from a flat taxonomy to a site-scoped structured
 * collection, so the listing ({@see resources/views/posts/_listing.antlers.html})
 * can no longer narrow with a `:taxonomy:post_tags` parameter. This scope resolves
 * the slug to its tag entry on the current site, then filters posts by that entry
 * id through the same reverse lookup as {@see PostsInPostTag}. It mirrors
 * {@see PostsInPostCategoryBySlug}, and the listing applies both.
 *
 * Two edge cases match the listing's filter contract:
 *   - A blank/absent slug adds NO constraint, so a `?tag=`-less request lists
 *     every post.
 *   - A slug that resolves to no tag on the current site filters by a null id,
 *     which intersects no post, so a stale or unknown `?tag=` lists nothing — as
 *     the retired taxonomy filter did for an unknown term. Because the lookup is
 *     scoped to the current site, a tag that exists only on another site is
 *     unknown here, which is the point of the conversion.
 */
class PostsInPostTagBySlug extends Scope
{
    /**
     * Constrain the query to posts tagged with the tag identified by the given slug
     * on the current site; a blank slug leaves the query unconstrained.
     *
     * @param  Builder  $query  Entry query builder for the `posts` collection
     * @param  array<string, mixed>  $values  Antlers tag parameters; `tag_slug` carries the tag slug from `?tag=`
     */
    public function apply(
        $query,
        $values
    ): void {
        $slug = $values['tag_slug'] ?? null;

        // An absent/blank filter is a no-op: the unfiltered listing lists every post.
        if (! is_string($slug) || $slug === '') {
            return;
        }

        /** @var SiteObject $site */
        $site = Site::current();

        // Resolve the slug to its tag entry on the current site.
        /** @var EntryQueryBuilder $tagQuery */
        $tagQuery = Entry::query();
        $tagQuery->where('collection', 'post_tags');
        $tagQuery->where('site', $site->handle());
        $tagQuery->where('slug', $slug);

        /** @var EntryObject|null $tag */
        $tag = $tagQuery->first();

        // An unknown slug yields a null id, which intersects no post (empty listing).
        $query->whereJsonContains('post_tags', $tag?->id());
    }
}
