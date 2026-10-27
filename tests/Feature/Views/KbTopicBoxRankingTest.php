<?php

declare(strict_types=1);

use App\Models\ArticleEngagementCount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry as EntryInstance;
use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Ranking coverage for the knowledge base topic box's dynamic "Most Viewed" /
 * "Most Liked" modes (UAMS-Web/uams-statamic-archive#286, UAMS-Web/uams-statamic-archive#287).
 *
 * The topic box lists articles with `{{ entries sort=… limit=… }}`, where `entries`
 * is aliased array data — so Antlers applies the two parameters as modifiers
 * left-to-right. If `limit` runs before `sort`, the set is truncated to the first N
 * in the collection's default order and only that slice is ranked, silently dropping
 * the genuinely most-viewed / most-liked articles whenever a topic has more articles
 * than the limit (the normal case; the dynamic boxes hardcode a limit of 4). This
 * verifies the order is sort-then-limit, both behaviorally and in the partial itself.
 *
 * @command php artisan test --compact tests/Feature/Views/KbTopicBoxRankingTest.php
 */
uses(StacheIsolatedTestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
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
 * Persist five published articles A..E and seed each id's engagement from the given
 * map, returning the entries keyed by slug.
 *
 * @param  array<string, array{views?: int, likes?: int, dislikes?: int}>  $engagement
 * @return array<string, EntryInstance>
 */
function seedRankingArticles(array $engagement): array
{
    $articles = [];
    foreach (['a', 'b', 'c', 'd', 'e'] as $slug) {
        /** @var EntryInstance $entry */
        $entry = Entry::make();
        $entry->collection('knowledge_bases');
        $entry->blueprint('article');
        $entry->slug($slug);
        $entry->published(true);
        $entry->data(['title' => 'Article '.strtoupper($slug)]);
        $entry->saveQuietly();
        $articles[$slug] = $entry;

        ArticleEngagementCount::create([
            'entry_id' => $entry->id(),
            ...$engagement[$slug],
        ]);
    }

    return $articles;
}

/**
 * Render the article titles from an aliased-collection loop. The outer tag pins the
 * default order to title:asc (A..E) so the two orderings below are deterministic.
 *
 * @return list<string>
 */
function renderEntriesLoop(string $innerLoop): array
{
    $out = (string) Antlers::parse(
        '{{ collection:knowledge_bases blueprint:is="article" status:is="published" sort="title:asc" as="entries" }}'
        .$innerLoop
        .'{{ /collection:knowledge_bases }}',
        [],
        true,
    );

    preg_match_all('/\[([^\]]+)\]/', $out, $matches);

    return $matches[1];
}

it('sort-then-limit takes the true top-N by views (the shipped order)', function (): void {
    // Views: the two highest (d=100, e=50) are LAST in the default title order.
    seedRankingArticles([
        'a' => ['views' => 1], 'b' => ['views' => 2], 'c' => ['views' => 3],
        'd' => ['views' => 100], 'e' => ['views' => 50],
    ]);

    expect(renderEntriesLoop('{{ entries sort="view_count:desc" limit="2" }}[{{ title }}]{{ /entries }}'))
        ->toBe(['Article D', 'Article E']);
});

it('sort-then-limit takes the true top-N by net like score', function (): void {
    // Net = likes - dislikes: d=+90, e=+40 are the top two, again last in default order.
    seedRankingArticles([
        'a' => ['likes' => 1], 'b' => ['likes' => 2], 'c' => ['likes' => 3],
        'd' => ['likes' => 95, 'dislikes' => 5], 'e' => ['likes' => 45, 'dislikes' => 5],
    ]);

    expect(renderEntriesLoop('{{ entries sort="like_count_net:desc" limit="2" }}[{{ title }}]{{ /entries }}'))
        ->toBe(['Article D', 'Article E']);
});

it('demonstrates that limit-then-sort would rank the WRONG articles', function (): void {
    // The trap the shipped order avoids: limiting first slices the default-order
    // prefix (A, B) and sorts only those, so the true top-2 (D, E) never appear.
    seedRankingArticles([
        'a' => ['views' => 1], 'b' => ['views' => 2], 'c' => ['views' => 3],
        'd' => ['views' => 100], 'e' => ['views' => 50],
    ]);

    $titles = renderEntriesLoop('{{ entries limit="2" sort="view_count:desc" }}[{{ title }}]{{ /entries }}');
    expect($titles)->toBe(['Article B', 'Article A']);
    expect($titles)->not->toBe(['Article D', 'Article E']);
});

it('renders the topic-box partial with sort before limit and the right sort keys', function (): void {
    // Guard the actual partial against a re-introduced limit-before-sort order or a
    // mistyped sort key (mirrors the template-content assertions in
    // KnowledgeBaseEmptyTopicTest).
    $template = File::get(resource_path('views/page_builder/components/_block_kb_topic_box.antlers.html'));

    // The entries loop sorts before it limits.
    expect($template)->toMatch('/\{\{\s*entries\s+sort="\{sort_order\}"\s+limit="\{topic_article_limit\}"\s*\}\}/');

    // And never the reverse (the ranking-breaking order).
    expect($template)->not->toMatch('/\{\{\s*entries\s+limit="\{topic_article_limit\}"\s+sort=/');

    // The dynamic modes map to the engagement computeds.
    expect($template)->toContain("'top_views' ) => 'view_count:desc'")
        ->and($template)->toContain("'top_likes' ) => 'like_count_net:desc'");
});
