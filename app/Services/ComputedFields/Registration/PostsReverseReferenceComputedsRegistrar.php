<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\Registration;

use App\Services\ComputedFields\Reverse\PostsReverseReferenceComputedValues;
use Statamic\Facades\Collection;

/**
 * Registers the `posts` reverse-reference computed (UAMS-Web/uams-statamic-archive#1419): `referenced_by_posts` —
 * the posts that name this post in their `related_posts` override — derived on read by
 * {@see PostsReverseReferenceComputedValues} (no stored back-reference; UAMS-Web/uams-statamic-archive#1155 rejected
 * bidirectional sync).
 */
final readonly class PostsReverseReferenceComputedsRegistrar
{
    /**
     * The collection that receives the reverse-reference computed.
     */
    private const string COLLECTION_HANDLE = 'posts';

    /**
     * @param  PostsReverseReferenceComputedValues  $reverse  Resolves the reverse set.
     */
    public function __construct(
        private PostsReverseReferenceComputedValues $reverse
    ) {}

    /**
     * Register the posts reverse-reference computed.
     */
    public function register(): void
    {
        // Cache the injected service for a readable first-class-callable binding.
        $reverse = $this->reverse;

        // The posts that reference this post via their related_posts override.
        Collection::computed(
            self::COLLECTION_HANDLE,
            'referenced_by_posts',
            $reverse->resolveReferencedByPosts(...)
        );
    }
}
