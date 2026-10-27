<?php

declare(strict_types=1);

use App\Http\Controllers\KnowledgeBase\ArticleReactionController;
use App\Models\ArticleEngagementCount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Testing\TestResponse;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry as EntryInstance;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Structures\CollectionStructure;
use Statamic\Structures\CollectionTree;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\StacheIsolatedTestCase;

uses(StacheIsolatedTestCase::class, RefreshDatabase::class);

covers(ArticleReactionController::class);

/**
 * The reaction endpoint (UAMS-Web/uams-statamic-archive#286): records a like / dislike relative to the visitor's
 * session vote (fresh / toggle-off / switch), gated to published articles whose
 * knowledge base has ratings enabled, with bots ignored. The rating gate is
 * exercised through a real structured knowledge base (root → article) rather than a
 * mock, so the ancestor `article_rating_query` resolution is proven end to end.
 *
 * @command php artisan test --compact tests/Feature/Http/KnowledgeBase/ArticleReactionControllerTest.php
 */
const KB_REACT_BROWSER_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';

beforeEach(function (): void {
    static $padded = false;
    if (! $padded) {
        if (runningUnderMutate()) {
            usleep(20_000_000);
            $padded = true;
        }
    }

    foreach (['knowledge_base', 'article', 'topic'] as $handle) {
        $blueprint = Blueprint::make($handle);
        $blueprint->setNamespace('collections.knowledge_bases');
        $blueprint->setContents(['fields' => [['handle' => 'title', 'field' => ['type' => 'text']]]]);
        $blueprint->save();
    }
    $knowledgeBases = CollectionFacade::make('knowledge_bases');
    $knowledgeBases->title('Knowledge Bases');
    $knowledgeBases->structureContents(['root' => false]);
    $knowledgeBases->save();

    $posts = CollectionFacade::make('posts');
    $posts->title('Posts');
    $posts->save();

    // The rate limiter accumulates in the file cache across tests; the throttle is
    // not under test here.
    $this->withoutMiddleware(ThrottleRequests::class);
});

/**
 * Persist a published knowledge_bases entry of the given blueprint (not placed in
 * any structure tree, so it has no rating-enabling ancestor).
 */
function makeReactEntry(string $blueprint = 'article', string $collection = 'knowledge_bases'): EntryInstance
{
    /** @var EntryInstance $entry */
    $entry = Entry::make();
    $entry->collection($collection);
    $entry->blueprint($blueprint);
    $entry->slug('slug-'.bin2hex(random_bytes(4)));
    $entry->published(true);
    $entry->data(['title' => 'Article']);
    $entry->saveQuietly();

    return $entry;
}

/**
 * Build a valid knowledge base hierarchy — root (ratings toggled by $enabled) →
 * topic → article — and return the freshly-resolved article so its ancestor walk
 * sees the tree. The rating toggle lives on the knowledge_base root, mirroring the
 * ancestor-inherited `article_rating_query` computed.
 */
function ratingArticle(bool $enabled = true): EntryInstance
{
    /** @var EntryInstance $root */
    $root = Entry::make();
    $root->collection('knowledge_bases');
    $root->blueprint('knowledge_base');
    $root->slug('kb-'.bin2hex(random_bytes(4)));
    $root->published(true);
    $root->data(['title' => 'KB', 'article_rating_query' => $enabled]);
    $root->saveQuietly();

    /** @var EntryInstance $topic */
    $topic = Entry::make();
    $topic->collection('knowledge_bases');
    $topic->blueprint('topic');
    $topic->slug('topic-'.bin2hex(random_bytes(4)));
    $topic->published(true);
    $topic->data(['title' => 'Topic']);
    $topic->saveQuietly();

    $article = makeReactEntry();

    /** @var Collection $collection */
    $collection = CollectionFacade::find('knowledge_bases');
    /** @var CollectionStructure $structure */
    $structure = $collection->structure();
    /** @var CollectionTree $tree */
    $tree = $structure->makeTree('default', [
        ['entry' => $root->id(), 'children' => [
            ['entry' => $topic->id(), 'children' => [
                ['entry' => $article->id()],
            ]],
        ]],
    ]);
    $tree->save();

    /** @var EntryInstance $resolved */
    $resolved = Entry::find($article->id());

    return $resolved;
}

/**
 * POST a reaction for an article id with a browser User-Agent by default.
 *
 * Takes the test case rather than reaching for it via `test()`: that helper is
 * typed `TestCall|HigherOrderTapProxy`, so the HTTP methods below are invisible
 * to PHPStan without an inline `@var` override (UAMS-Web/uams-statamic-archive#1719).
 *
 * @return TestResponse<Response>
 */
function postReact(StacheIsolatedTestCase $case, string $id, ?string $reaction, string $userAgent = KB_REACT_BROWSER_UA): TestResponse
{
    return $case->withHeaders(['User-Agent' => $userAgent])
        ->postJson('/!/kb/articles/'.$id.'/react', $reaction === null ? [] : ['reaction' => $reaction]);
}

it('records a fresh like and returns the snapshot with the active reaction', function (): void {
    $article = ratingArticle();
    /** @var string $id */
    $id = $article->id();

    postReact($this, $id, 'like')
        ->assertOk()
        ->assertJson(['likes' => 1, 'dislikes' => 0, 'net' => 1, 'reaction' => 'like'])
        ->assertSessionHas('kb_engagement.reactions', [$id => 'like']);

    expect(ArticleEngagementCount::where('entry_id', $id)->value('likes'))->toBe(1);
});

it('toggles an identical reaction off', function (): void {
    $article = ratingArticle();
    /** @var string $id */
    $id = $article->id();
    ArticleEngagementCount::create(['entry_id' => $id, 'likes' => 1]);

    $this->withSession(['kb_engagement.reactions' => [$id => 'like']])
        ->withHeaders(['User-Agent' => KB_REACT_BROWSER_UA])
        ->postJson('/!/kb/articles/'.$id.'/react', ['reaction' => 'like'])
        ->assertOk()
        ->assertJson(['likes' => 0, 'reaction' => null]);
});

it('switches the vote from like to dislike', function (): void {
    $article = ratingArticle();
    /** @var string $id */
    $id = $article->id();
    ArticleEngagementCount::create(['entry_id' => $id, 'likes' => 1]);

    $this->withSession(['kb_engagement.reactions' => [$id => 'like']])
        ->withHeaders(['User-Agent' => KB_REACT_BROWSER_UA])
        ->postJson('/!/kb/articles/'.$id.'/react', ['reaction' => 'dislike'])
        ->assertOk()
        ->assertJson(['likes' => 0, 'dislikes' => 1, 'net' => -1, 'reaction' => 'dislike']);
});

it('422s an unrecognized reaction', function (): void {
    $article = ratingArticle();
    /** @var string $id */
    $id = $article->id();

    // RemoveArrayItem on the error payload would drop the message key.
    postReact($this, $id, 'love')
        ->assertStatus(422)
        ->assertJson(['error' => 'Invalid reaction.']);
    postReact($this, $id, null)
        ->assertStatus(422)
        ->assertJson(['error' => 'Invalid reaction.']);
});

it('ignores a bot vote, leaving the counters untouched', function (): void {
    $article = ratingArticle();
    /** @var string $id */
    $id = $article->id();

    postReact($this, $id, 'like', 'curl/8.4.0')
        ->assertOk()
        ->assertJson(['likes' => 0, 'reaction' => null]);

    expect(ArticleEngagementCount::where('entry_id', $id)->exists())->toBeFalse();
});

it('403s when the knowledge base has ratings disabled', function (): void {
    $article = ratingArticle(enabled: false);
    /** @var string $id */
    $id = $article->id();

    // RemoveArrayItem on the error payload would drop the message key.
    postReact($this, $id, 'like')
        ->assertStatus(403)
        ->assertJson(['error' => 'Ratings are not enabled for this article.']);
});

it('404s for a non-article or unknown id', function (): void {
    /** @var string $topicId */
    $topicId = makeReactEntry('topic')->id();
    postReact($this, $topicId, 'like')->assertNotFound();
    postReact($this, 'does-not-exist', 'like')->assertNotFound();
});
