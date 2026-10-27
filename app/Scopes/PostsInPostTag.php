<?php

declare(strict_types=1);

namespace App\Scopes;

use Statamic\Query\Builder;
use Statamic\Query\Scopes\Scope;

/**
 * Query scope restricting a `posts` query to the posts tagged with a given
 * `post_tags` collection entry.
 *
 * UAMS-Web/uams-statamic-archive#2532 converted `post_tags` from a flat taxonomy to a site-scoped structured
 * collection, so a post now references its tags through the `post_tags` *entries*
 * field — an array of tag entry ids — rather than a taxonomy term. This scope is
 * the reverse lookup the tag archive needs ("the posts with this tag"), mirroring
 * {@see PostsInPostCategory}.
 *
 * Invoked from the tag entry template as
 * `{{ collection:posts query_scope="posts_in_post_tag" tag="{{ id }}" }}`, so
 * `$values['tag']` carries the current tag entry id. A missing or blank id
 * intersects no post, so an id-less context lists nothing rather than every post.
 */
class PostsInPostTag extends Scope
{
    /**
     * Constrain the query to posts whose `post_tags` field contains the given tag
     * entry id.
     *
     * @param  Builder  $query  Entry query builder for the `posts` collection
     * @param  array<string, mixed>  $values  Antlers tag parameters; `tag` carries the tag entry id
     */
    public function apply(
        $query,
        $values
    ): void {
        $query->whereJsonContains(
            'post_tags',
            $values['tag'] ?? null
        );
    }
}
