<?php

declare(strict_types=1);

use App\Models\ArticleEngagementCount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry as EntryInstance;
use Statamic\Facades\Blink;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Fields\Value;
use Tests\Support\StacheIsolatedTestCase;

uses(StacheIsolatedTestCase::class, RefreshDatabase::class);

/**
 * The engagement computeds (UAMS-Web/uams-statamic-archive#286, UAMS-Web/uams-statamic-archive#287) surface the sideband store through the
 * article blueprint's `view_count` / `like_count` / `dislike_count` /
 * `like_count_net` fields, and read as zero for non-article entries.
 *
 * @covers \App\Services\ComputedFields\Registration\ArticleEngagementComputedsRegistrar
 *
 * @command php artisan test --compact tests/Feature/Services/ComputedFields/Registration/ArticleEngagementComputedsRegistrarTest.php
 */
beforeEach(function (): void {
    // Pad Pest's mutant-child timeout once per process under `--mutate`.
    static $padded = false;
    if (! $padded && runningUnderMutate()) {
        usleep(20_000_000);
        $padded = true;
    }

    foreach (['article', 'topic'] as $handle) {
        /** @var Statamic\Fields\Blueprint $blueprint */
        $blueprint = Blueprint::make($handle);
        /** @var Statamic\Fields\Blueprint $blueprint */
        $blueprint = $blueprint->setNamespace('collections.knowledge_bases');
        /** @var Statamic\Fields\Blueprint $blueprint */
        $blueprint = $blueprint->setContents(['fields' => [['handle' => 'title', 'field' => ['type' => 'text']]]]);
        $blueprint->save();
    }

    /** @var Collection $collection */
    $collection = CollectionFacade::make('knowledge_bases');
    /** @var Collection $collection */
    $collection = $collection->title('Knowledge Bases');
    $collection->save();
});

/**
 * Persist a published knowledge_bases entry of the given blueprint.
 */
function makeComputedEntry(string $blueprint, string $slug): EntryInstance
{
    /** @var EntryInstance $entry */
    $entry = Entry::make();
    /** @var EntryInstance $entry */
    $entry = $entry->collection('knowledge_bases');
    /** @var EntryInstance $entry */
    $entry = $entry->blueprint($blueprint);
    /** @var EntryInstance $entry */
    $entry = $entry->slug($slug);
    /** @var EntryInstance $entry */
    $entry = $entry->published(true);
    /** @var EntryInstance $entry */
    $entry = $entry->data(['title' => ucfirst($slug)]);
    $entry->saveQuietly();

    return $entry;
}

it('surfaces the store tallies through the article computeds', function (): void {
    $article = makeComputedEntry('article', 'a');
    ArticleEngagementCount::create([
        'entry_id' => $article->id(),
        'views' => 42,
        'likes' => 7,
        'dislikes' => 2,
    ]);

    /** @var Value $viewCount */
    $viewCount = $article->augmentedValue('view_count');
    /** @var Value $likeCount */
    $likeCount = $article->augmentedValue('like_count');
    /** @var Value $dislikeCount */
    $dislikeCount = $article->augmentedValue('dislike_count');
    /** @var Value $likeCountNet */
    $likeCountNet = $article->augmentedValue('like_count_net');

    expect($viewCount->value())->toBe(42)
        ->and($likeCount->value())->toBe(7)
        ->and($dislikeCount->value())->toBe(2)
        ->and($likeCountNet->value())->toBe(5);
});

it('reads zero for an article with no stored engagement', function (): void {
    $article = makeComputedEntry('article', 'fresh');

    /** @var Value $viewCount */
    $viewCount = $article->augmentedValue('view_count');
    /** @var Value $likeCountNet */
    $likeCountNet = $article->augmentedValue('like_count_net');

    expect($viewCount->value())->toBe(0)
        ->and($likeCountNet->value())->toBe(0);
});

it('reads zero for a non-article entry even if a row exists for its id', function (): void {
    $topic = makeComputedEntry('topic', 't');

    // A stray row keyed by the topic id must not leak into the topic's computeds:
    // engagement is an article-only concept.
    ArticleEngagementCount::create([
        'entry_id' => $topic->id(),
        'views' => 99,
        'likes' => 99,
    ]);

    /** @var Value $viewCount */
    $viewCount = $topic->augmentedValue('view_count');
    /** @var Value $likeCount */
    $likeCount = $topic->augmentedValue('like_count');

    expect($viewCount->value())->toBe(0)
        ->and($likeCount->value())->toBe(0);
});

/**
 * The four engagement computeds of an entry, keyed by handle.
 *
 * @return array<string, mixed>
 */
function engagementValues(EntryInstance $entry): array
{
    $values = [];
    foreach (['view_count', 'like_count', 'dislike_count', 'like_count_net'] as $handle) {
        /** @var Value $value */
        $value = $entry->augmentedValue($handle);
        $values[$handle] = $value->value();
    }

    return $values;
}

/**
 * The request-memo key the registrar reads a saved entry's tallies under.
 */
function engagementCacheKey(EntryInstance $entry): string
{
    $id = $entry->id();
    expect($id)->toBeString();

    return 'kb_engagement_counts_'.(is_string($id) ? $id : '');
}

it('reads the tallies memoized for the request under the entry id', function (): void {
    $article = makeComputedEntry('article', 'memoized');
    // The store holds nothing, so only the memoized read can supply these.
    Blink::put(engagementCacheKey($article), ['views' => 11, 'likes' => 4, 'dislikes' => 1, 'net' => 3]);

    expect(engagementValues($article))->toBe([
        'view_count' => 11,
        'like_count' => 4,
        'dislike_count' => 1,
        'like_count_net' => 3,
    ]);
});

it('reads zero for each tally the memoized row lacks', function (): void {
    $article = makeComputedEntry('article', 'partial');
    Blink::put(engagementCacheKey($article), []);

    expect(engagementValues($article))->toBe([
        'view_count' => 0,
        'like_count' => 0,
        'dislike_count' => 0,
        'like_count_net' => 0,
    ]);
});

it('coerces numeric strings and zeroes non-numeric tallies', function (): void {
    $article = makeComputedEntry('article', 'strings');
    Blink::put(engagementCacheKey($article), ['views' => '12', 'likes' => 'many', 'dislikes' => null, 'net' => '-2']);

    expect(engagementValues($article))->toBe([
        'view_count' => 12,
        'like_count' => 0,
        'dislike_count' => 0,
        'like_count_net' => -2,
    ]);
});

it('reads zero when the memoized value is not a row', function (): void {
    $article = makeComputedEntry('article', 'not-a-row');
    Blink::put(engagementCacheKey($article), 'oops');

    expect(engagementValues($article))->toBe([
        'view_count' => 0,
        'like_count' => 0,
        'dislike_count' => 0,
        'like_count_net' => 0,
    ]);
});

it('reads zero for an unsaved article, which has no id to key the store on', function (): void {
    /** @var EntryInstance $article */
    $article = Entry::make();
    $article->collection('knowledge_bases');
    $article->blueprint('article');
    $article->data(['title' => 'Unsaved']);

    expect($article->id())->toBeNull()
        ->and(engagementValues($article))->toBe([
            'view_count' => 0,
            'like_count' => 0,
            'dislike_count' => 0,
            'like_count_net' => 0,
        ]);
});
