<?php

declare(strict_types=1);

use App\Services\ComputedFields\Reverse\PostsReverseReferenceComputedValues;
use Statamic\Facades\Blink;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(TestCase::class);

/*
|--------------------------------------------------------------------------
| referenced_by_posts — real inverse resolution (UAMS-Web/uams-statamic-archive#1419)
|--------------------------------------------------------------------------
|
| End-to-end proof of the AC on the REAL posts collection: post A listing B in
| its `related_posts` override makes B's `referenced_by_posts` contain A, with the
| manual override never mutated. Uses real saved posts (matched by a slug prefix and
| deleted afterward), so it exercises the actual `posts` + `related_posts` handles the
| service reads — not a mock. Skipped when the content/ submodule (and thus the posts
| collection) is absent.
|
| @covers \App\Services\ComputedFields\Reverse\PostsReverseReferenceComputedValues
|
| @command php artisan test --compact tests/Feature/Services/ComputedFields/Reverse/PostsReferencedByPostsIntegrationTest.php
*/

const REFERENCED_BY_PREFIX = 'zz-referenced-by-';

function referencedByTearDown(): void
{
    if (! Collection::find('posts')) {
        return;
    }

    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    /** @var EntryQueryBuilder $query */
    $query = $query->where('collection', 'posts');

    /** @var Illuminate\Support\Collection<int, Statamic\Entries\Entry> $entries */
    $entries = $query->get();

    $entries
        ->filter(function (Statamic\Entries\Entry $entry): bool {
            /** @var string|null $slug */
            $slug = $entry->slug();

            return str_starts_with((string) $slug, REFERENCED_BY_PREFIX);
        })
        ->each(fn (Statamic\Entries\Entry $entry) => $entry->delete());
}

/**
 * Save a prefixed post carrying the given `related_posts` value, published unless told otherwise.
 *
 * @param  ?array<int, mixed>  $relatedIds
 */
function referencedByMakePost(string $suffix, ?array $relatedIds, bool $published = true): Statamic\Entries\Entry
{
    $data = ['title' => 'RefBy '.$suffix];

    if ($relatedIds !== null) {
        $data['related_posts'] = $relatedIds;
    }

    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->collection('posts');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->locale('default');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->slug(REFERENCED_BY_PREFIX.$suffix);
    // `posts` is a dated collection (past: public) — a past date is required for a
    // published entry to reach `published` status and be seen by the reverse query.
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->date('2020-01-01');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->published($published);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->data($data);
    $entry->save();

    return $entry;
}

/**
 * The ids of a resolved reverse set.
 *
 * @param  iterable<mixed>  $set
 * @return list<mixed>
 */
function referencedByIds(iterable $set): array
{
    $ids = [];
    foreach ($set as $entry) {
        /** @var Statamic\Entries\Entry $entry */
        $ids[] = $entry->id();
    }

    return $ids;
}

beforeEach(function (): void {
    if (! Collection::find('posts')) {
        ContentSubmodule::reportMissing('posts collection');
    }

    Blink::flush();
    referencedByTearDown();
});

afterEach(fn () => referencedByTearDown());

it('resolves the inverse: A lists B as related, so B is referenced-by A', function (): void {
    $b = referencedByMakePost('b', null);
    $a = referencedByMakePost('a', [$b->id()]);

    // Saving a post resolves reverses through a request-scoped Blink memo, so clear it
    // after the setup writes to resolve against the final indexed state (production reads
    // B in a separate request, so it never sees a mid-write memo).
    Blink::flush();
    $service = app(PostsReverseReferenceComputedValues::class);

    // B's reverse contains A (which points at B); A's reverse is empty (nobody points at A).
    expect(referencedByIds($service->resolveReferencedByPosts($b)))->toContain($a->id())
        ->and($service->resolveReferencedByPosts($a))->toBeEmpty();
})->group('serial');

it('does not mutate the manual related_posts override when resolving the reverse', function (): void {
    $b = referencedByMakePost('b', null);
    $a = referencedByMakePost('a', [$b->id()]);

    Blink::flush();
    app(PostsReverseReferenceComputedValues::class)->resolveReferencedByPosts($b);

    // The override on A is untouched, and no back-reference was written onto B (UAMS-Web/uams-statamic-archive#1155: no sync).
    /** @var Statamic\Entries\Entry $foundA */
    $foundA = Entry::find($a->id());
    /** @var list<string> $relatedA */
    $relatedA = $foundA->get('related_posts');
    /** @var Statamic\Entries\Entry $foundB */
    $foundB = Entry::find($b->id());
    /** @var list<string>|null $relatedB */
    $relatedB = $foundB->get('related_posts');

    expect($relatedA)->toBe([$b->id()])
        ->and($relatedB)->toBeNull();
})->group('serial');

it('excludes an unpublished referencing post from the inverse', function (): void {
    $b = referencedByMakePost('b', null);
    referencedByMakePost('a', [$b->id()], published: false);

    Blink::flush();
    // The resolver restricts the reverse to published pointing entries by default.
    expect(app(PostsReverseReferenceComputedValues::class)->resolveReferencedByPosts($b))->toBeEmpty();
})->group('serial');
