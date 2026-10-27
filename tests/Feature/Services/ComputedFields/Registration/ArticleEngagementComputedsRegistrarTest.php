<?php

declare(strict_types=1);

use App\Models\ArticleEngagementCount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry as EntryInstance;
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
