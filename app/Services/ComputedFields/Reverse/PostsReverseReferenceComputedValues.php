<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\Reverse;

use Statamic\Entries\Entry;
use Statamic\Entries\EntryCollection;

/**
 * The reverse-reference computed for the `posts` collection (UAMS-Web/uams-statamic-archive#1419).
 *
 * `related_posts` is a directional, per-post editorial override: the editor of post A
 * hand-picks A's related stories (an `entries` pointer into `posts`). Bidirectional
 * A↔B sync was deliberately rejected (UAMS-Web/uams-statamic-archive#1155) — editing A's list must not silently
 * mutate B's stored override and corrupt its meaning. The useful inverse — "which
 * posts reference me as a related story" — is therefore a read-only computed derived
 * on read, never stored as a second edge, matching the Wave-116 computed-reverse-
 * reference pattern (UAMS-Web/uams-statamic-archive#1372/UAMS-Web/uams-statamic-archive#1373/UAMS-Web/uams-statamic-archive#1374).
 *
 * Thin wiring over the shared {@see ReverseEntryReferenceResolver}, whose real-Stache
 * behavior — matching an id stored as a scalar, a single-element array, or a
 * multi-element array, and honoring the published filter — is proven in
 * ReverseEntryReferenceResolverTest.
 */
class PostsReverseReferenceComputedValues
{
    /**
     * The collection whose entries carry the `related_posts` override.
     */
    private const string SOURCE_COLLECTION = 'posts';

    /**
     * The `entries` override matched for the reverse.
     */
    private const string RELATED_POSTS_FIELD = 'related_posts';

    /**
     * @param  ReverseEntryReferenceResolver  $resolver  Resolves the reverse pointer.
     */
    public function __construct(
        private ReverseEntryReferenceResolver $resolver
    ) {}

    /**
     * The published `posts` entries whose `related_posts` override names this post —
     * the read-only inverse of the manual override, with nothing stored back.
     *
     * @return EntryCollection<int, Entry>
     */
    public function resolveReferencedByPosts(
        Entry $entry
    ): EntryCollection {
        return $this->resolver->resolve(
            self::SOURCE_COLLECTION,
            self::RELATED_POSTS_FIELD,
            $entry->id()
        );
    }
}
