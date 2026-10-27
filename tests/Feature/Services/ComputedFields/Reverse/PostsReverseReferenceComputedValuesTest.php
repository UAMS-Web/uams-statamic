<?php

declare(strict_types=1);

use App\Services\ComputedFields\Reverse\PostsReverseReferenceComputedValues;
use App\Services\ComputedFields\Reverse\ReverseEntryReferenceResolver;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Entries\EntryCollection;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/*
|--------------------------------------------------------------------------
| PostsReverseReferenceComputedValues (UAMS-Web/uams-statamic-archive#1419)
|--------------------------------------------------------------------------
|
| The `posts` reverse-reference computed is thin wiring over the shared
| ReverseEntryReferenceResolver (whose real-Stache behavior — scalar/array/multi
| storage matching and the published filter — is proved in
| ReverseEntryReferenceResolverTest, and end-to-end on the posts collection in
| PostsReferencedByPostsIntegrationTest). These mock the resolver to pin the exact
| call the reverse makes: a flat top-level `resolve` on the `related_posts` pointer
| of the `posts` collection — never the nested matcher.
|
| @covers \App\Services\ComputedFields\Reverse\PostsReverseReferenceComputedValues
|
| @command php artisan test --compact tests/Feature/Services/ComputedFields/Reverse/PostsReverseReferenceComputedValuesTest.php
*/

function postsReverseEntry(?string $id): Entry
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn($id);

    return $entry;
}

it('resolves referenced-by-posts from the flat top-level related_posts pointer on the posts collection', function (): void {
    $expected = EntryCollection::make();
    $entry = postsReverseEntry('post-1');

    $resolver = Mockery::mock(ReverseEntryReferenceResolver::class);
    // A top-level `entries` override — a shallow resolve, not the nested matcher.
    $resolver->shouldReceive('resolve')
        ->once()
        ->with('posts', 'related_posts', 'post-1')
        ->andReturn($expected);
    $resolver->shouldReceive('resolveNested')->never();

    expect((new PostsReverseReferenceComputedValues($resolver))->resolveReferencedByPosts($entry))
        ->toBe($expected);
});

it('forwards the entry id straight through, including a null id for an unsaved post', function (): void {
    // The resolver owns the id-less short-circuit; the service must forward whatever
    // id() returns without pre-filtering, so an unsaved post still delegates.
    $expected = EntryCollection::make();
    $entry = postsReverseEntry(null);

    $resolver = Mockery::mock(ReverseEntryReferenceResolver::class);
    $resolver->shouldReceive('resolve')->once()->with('posts', 'related_posts', null)->andReturn($expected);
    $resolver->shouldReceive('resolveNested')->never();

    expect((new PostsReverseReferenceComputedValues($resolver))->resolveReferencedByPosts($entry))
        ->toBe($expected);
});
