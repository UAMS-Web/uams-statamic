<?php

declare(strict_types=1);

use App\Models\ArticleEngagementCount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry as EntryInstance;
use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Verifies that the knowledge base topic box's "Most Viewed" / "Most Liked" sorts
 * (`_block_kb_topic_box.antlers.html`) rank by the engagement computeds surfaced
 * from the sideband store (UAMS-Web/uams-statamic-archive#286, UAMS-Web/uams-statamic-archive#287) — i.e. that Statamic's `{{ entries sort= }}`
 * array sort resolves a *computed* value, which the whole sort design depends on.
 *
 * Real article entries are built in an isolated Stache and their tallies seeded in
 * the store, then the exact `as="entries"` + `{{ entries sort= }}` pattern the topic
 * box uses is rendered with augmentation enabled.
 *
 * @command php artisan test --compact tests/Feature/Views/KbTopicBoxEngagementSortTest.php
 */
uses(StacheIsolatedTestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    // A minimal `article` blueprint so entries carry the article blueprint handle
    // (the engagement computeds only surface real values for article entries).
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::make('article');
    $blueprint->setNamespace('collections.knowledge_bases');
    $blueprint->setContents(['fields' => [['handle' => 'title', 'field' => ['type' => 'text']]]]);
    $blueprint->save();

    /** @var Collection $collection */
    $collection = CollectionFacade::make('knowledge_bases');
    $collection->title('Knowledge Bases');
    $collection->save();
});

/**
 * Persist a published knowledge_bases article entry.
 */
function makeEngagementArticle(string $slug, string $title): EntryInstance
{
    /** @var EntryInstance $entry */
    $entry = Entry::make();
    $entry->collection('knowledge_bases');
    $entry->blueprint('article');
    $entry->slug($slug);
    $entry->published(true);
    $entry->data(['title' => $title]);
    $entry->saveQuietly();

    return $entry;
}

it('ranks the topic-box "Most Viewed" list by the view_count computed', function (): void {
    $a = makeEngagementArticle('a', 'Article A');
    $b = makeEngagementArticle('b', 'Article B');
    $c = makeEngagementArticle('c', 'Article C');

    ArticleEngagementCount::create(['entry_id' => $a->id(), 'views' => 5]);
    ArticleEngagementCount::create(['entry_id' => $b->id(), 'views' => 20]);
    ArticleEngagementCount::create(['entry_id' => $c->id(), 'views' => 10]);

    // The exact pattern from _block_kb_topic_box.antlers.html: alias the results,
    // then loop with sort. Rendered with augmentation so the computed resolves.
    $out = (string) Antlers::parse(
        '{{ collection:knowledge_bases blueprint:is="article" status:is="published" as="entries" }}'
        .'{{ entries sort="view_count:desc" }}[{{ title }}:{{ view_count }}]{{ /entries }}'
        .'{{ /collection:knowledge_bases }}',
        [],
        true,
    );

    // Descending by views: B(20), C(10), A(5)
    expect($out)->toBe('[Article B:20][Article C:10][Article A:5]');
});

it('ranks the topic-box "Most Liked" list by the like_count_net computed', function (): void {
    $a = makeEngagementArticle('a', 'Article A');
    $b = makeEngagementArticle('b', 'Article B');
    $c = makeEngagementArticle('c', 'Article C');

    // Net = likes - dislikes: A=+2, B=+9, C=-1
    ArticleEngagementCount::create(['entry_id' => $a->id(), 'likes' => 5, 'dislikes' => 3]);
    ArticleEngagementCount::create(['entry_id' => $b->id(), 'likes' => 10, 'dislikes' => 1]);
    ArticleEngagementCount::create(['entry_id' => $c->id(), 'likes' => 2, 'dislikes' => 3]);

    $out = (string) Antlers::parse(
        '{{ collection:knowledge_bases blueprint:is="article" status:is="published" as="entries" }}'
        .'{{ entries sort="like_count_net:desc" }}[{{ title }}:{{ like_count_net }}]{{ /entries }}'
        .'{{ /collection:knowledge_bases }}',
        [],
        true,
    );

    // Descending by net like score: B(9), A(2), C(-1)
    expect($out)->toBe('[Article B:9][Article A:2][Article C:-1]');
});
