<?php

declare(strict_types=1);

use App\Models\ArticleEngagementCount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Testing\TestResponse;
use Statamic\Entries\Entry as EntryInstance;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\StacheIsolatedTestCase;

uses(StacheIsolatedTestCase::class, RefreshDatabase::class);

/**
 * The view beacon endpoint (UAMS-Web/uams-statamic-archive#287): counts one view per session for a published
 * article, filters bots, and returns the engagement snapshot.
 *
 * @covers \App\Http\Controllers\KnowledgeBase\ArticleViewController
 *
 * @command php artisan test --compact tests/Feature/Http/KnowledgeBase/ArticleViewControllerTest.php
 */
const KB_VIEW_BROWSER_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';

beforeEach(function (): void {
    foreach (['article', 'topic'] as $handle) {
        $blueprint = Blueprint::make($handle);
        $blueprint->setNamespace('collections.knowledge_bases');
        $blueprint->setContents(['fields' => [['handle' => 'title', 'field' => ['type' => 'text']]]]);
        $blueprint->save();
    }
    $knowledgeBases = CollectionFacade::make('knowledge_bases');
    $knowledgeBases->title('Knowledge Bases');
    $knowledgeBases->save();

    // A foreign collection with its own valid blueprint to prove the collection gate.
    $postBlueprint = Blueprint::make('post');
    $postBlueprint->setNamespace('collections.posts');
    $postBlueprint->setContents(['fields' => [['handle' => 'title', 'field' => ['type' => 'text']]]]);
    $postBlueprint->save();
    $posts = CollectionFacade::make('posts');
    $posts->title('Posts');
    $posts->save();

    // The rate limiter accumulates in the file cache across tests; the beacon
    // throttle is not under test here.
    $this->withoutMiddleware(ThrottleRequests::class);
});

/**
 * Persist a published knowledge_bases entry of the given blueprint.
 */
function makeViewEntry(string $blueprint = 'article', string $collection = 'knowledge_bases', bool $published = true): EntryInstance
{
    /** @var EntryInstance $entry */
    $entry = Entry::make();
    $entry->collection($collection);
    $entry->blueprint($blueprint);
    $entry->slug('slug-'.bin2hex(random_bytes(4)));
    $entry->published($published);
    $entry->data(['title' => 'Article']);
    $entry->saveQuietly();

    return $entry;
}

/**
 * POST the view beacon for an article id with a browser User-Agent by default.
 *
 * Takes the test case rather than reaching for it via `test()`: that helper is
 * typed `TestCall|HigherOrderTapProxy`, so the HTTP methods below are invisible
 * to PHPStan without an inline `@var` override (UAMS-Web/uams-statamic-archive#1719).
 *
 * @return TestResponse<Response>
 */
function postView(StacheIsolatedTestCase $test, string $id, string $userAgent = KB_VIEW_BROWSER_UA): TestResponse
{
    return $test->withHeaders(['User-Agent' => $userAgent])
        ->postJson('/!/kb/articles/'.$id.'/view');
}

it('counts a fresh view and returns the full snapshot shape', function (): void {
    $article = makeViewEntry();
    /** @var string $id */
    $id = $article->id();

    postView($this, $id)
        ->assertOk()
        ->assertJson(['views' => 1, 'likes' => 0, 'dislikes' => 0, 'net' => 0, 'reaction' => null])
        ->assertJsonStructure(['views', 'likes', 'dislikes', 'net', 'reaction']);

    expect(ArticleEngagementCount::where('entry_id', $id)->value('views'))->toBe(1);
});

it('does not double-count a view already recorded this session', function (): void {
    $article = makeViewEntry();
    /** @var string $id */
    $id = $article->id();
    ArticleEngagementCount::create(['entry_id' => $id, 'views' => 5]);

    $this->withSession(['kb_engagement.viewed' => [$id]])
        ->withHeaders(['User-Agent' => KB_VIEW_BROWSER_UA])
        ->postJson('/!/kb/articles/'.$id.'/view')
        ->assertOk()
        ->assertJson(['views' => 5]);

    expect(ArticleEngagementCount::where('entry_id', $id)->value('views'))->toBe(5);
});

it('records the view in the session so a repeat is suppressed', function (): void {
    $article = makeViewEntry();
    /** @var string $id */
    $id = $article->id();

    postView($this, $id)
        ->assertSessionHas('kb_engagement.viewed', [$id]);
});

it('does not count a bot view', function (): void {
    $article = makeViewEntry();
    /** @var string $id */
    $id = $article->id();

    postView($this, $id, 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)')
        ->assertOk()
        ->assertJson(['views' => 0]);

    expect(ArticleEngagementCount::where('entry_id', $id)->exists())->toBeFalse();
});

it('hydrates the visitor’s existing reaction into the snapshot', function (): void {
    $article = makeViewEntry();
    /** @var string $id */
    $id = $article->id();

    $this->withSession(['kb_engagement.reactions' => [$id => 'like']])
        ->withHeaders(['User-Agent' => KB_VIEW_BROWSER_UA])
        ->postJson('/!/kb/articles/'.$id.'/view')
        ->assertOk()
        ->assertJson(['reaction' => 'like']);
});

it('404s for a draft, a non-article, a foreign entry, or an unknown id', function (): void {
    /** @var string $draftId */
    $draftId = makeViewEntry(published: false)->id();
    /** @var string $topicId */
    $topicId = makeViewEntry('topic')->id();
    /** @var string $foreignId */
    $foreignId = makeViewEntry('post', 'posts')->id();

    postView($this, $draftId)->assertNotFound();
    postView($this, $topicId)->assertNotFound();
    postView($this, $foreignId)->assertNotFound();
    postView($this, 'does-not-exist')->assertNotFound();
});
