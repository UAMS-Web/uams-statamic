<?php

declare(strict_types=1);

namespace App\Scopes;

use Statamic\Query\Builder;
use Statamic\Query\Scopes\Scope;

/**
 * Query scope restricting a `posts` query to the posts filed under a given
 * `post_categories` collection entry.
 *
 * UAMS-Web/uams-statamic-archive#1433 migrated `post_categories` from a flat taxonomy to a structured
 * collection, so a post now references its categories through the
 * `post_categories` *entries* field — an array of category entry ids — rather
 * than a taxonomy term. This scope is the reverse lookup the category archive
 * (UAMS-Web/uams-statamic-archive#1434) needs ("the posts in this category"), expressed as a
 * `whereJsonContains` over that array, which the Stache flat-file driver filters
 * natively via `array_intersect`.
 *
 * Invoked from a category entry template as
 * `{{ collection:posts query_scope="posts_in_post_category" category="{{ id }}" }}`,
 * so `$values['category']` carries the current category entry id. A missing or
 * blank id intersects no post, so an id-less context lists nothing rather than
 * every post.
 */
class PostsInPostCategory extends Scope
{
    /**
     * Constrain the query to posts whose `post_categories` field contains the
     * given category entry id.
     *
     * @param  Builder  $query  Entry query builder for the `posts` collection
     * @param  array<string, mixed>  $values  Antlers tag parameters; `category` carries the category entry id
     */
    public function apply(
        $query,
        $values
    ): void {
        $query->whereJsonContains(
            'post_categories',
            $values['category'] ?? null
        );
    }
}
