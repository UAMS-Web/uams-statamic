<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Statamic\Entries\Entry as EntryInstance;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Fields\Value;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Regression: the engagement computeds are GLOBAL, so they augment every
 * knowledge_bases article. When the `article_engagement_counts` table is absent —
 * a KB page rendered before the migration has run (fresh environment / deploy
 * ordering), or any test that augments an article without a migrated database — the
 * read must degrade to zero, never throw `no such table`.
 *
 * This is the full-suite-only fatal the per-file, `RefreshDatabase` tests missed:
 * the bulk of the suite augments file-fixture articles with no migrated engagement
 * table, and an unguarded read there aborts the whole `phpunit` job.
 *
 * @command php artisan test --compact tests/Feature/Services/KnowledgeBase/ArticleEngagementMissingTableTest.php
 */
uses(StacheIsolatedTestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $blueprint = Blueprint::make('article');
    $blueprint->setNamespace('collections.knowledge_bases');
    $blueprint->setContents(['fields' => [['handle' => 'title', 'field' => ['type' => 'text']]]]);
    $blueprint->save();

    $knowledgeBases = CollectionFacade::make('knowledge_bases');
    $knowledgeBases->title('Knowledge Bases');
    $knowledgeBases->save();
});

it('augments the engagement computeds to zero when the table is absent, without throwing', function (): void {
    // Reproduce the missing-table condition. RefreshDatabase migrated the table;
    // dropping it inside the test's transaction is rolled back afterward, so no
    // other test sees the drop.
    Schema::drop('article_engagement_counts');
    expect(Schema::hasTable('article_engagement_counts'))->toBeFalse();

    /** @var EntryInstance $article */
    $article = Entry::make();
    $article->collection('knowledge_bases');
    $article->blueprint('article');
    $article->slug('a');
    $article->published(true);
    $article->data(['title' => 'A']);
    $article->saveQuietly();

    // The global computeds resolve without touching the missing table.
    /** @var Value $viewCount */
    $viewCount = $article->augmentedValue('view_count');
    /** @var Value $likeCount */
    $likeCount = $article->augmentedValue('like_count');
    /** @var Value $dislikeCount */
    $dislikeCount = $article->augmentedValue('dislike_count');
    /** @var Value $likeCountNet */
    $likeCountNet = $article->augmentedValue('like_count_net');

    expect($viewCount->value())->toBe(0)
        ->and($likeCount->value())->toBe(0)
        ->and($dislikeCount->value())->toBe(0)
        ->and($likeCountNet->value())->toBe(0);
});
