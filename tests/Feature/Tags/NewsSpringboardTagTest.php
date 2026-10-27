<?php

declare(strict_types=1);

use App\Services\Posts\NewsSpringboardService;
use App\Tags\NewsSpringboard;
use Illuminate\Support\Carbon;
use Statamic\Entries\Collection;
use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Tests\Support\StacheIsolatedTestCase;

covers(NewsSpringboard::class);

/**
 * End-to-end coverage for the {{ news_springboard }} tag (UAMS-Web/uams-statamic-archive#1158): it reads a
 * springboard entry's `sections` grid — however Antlers augments it — normalizes
 * each row, and returns the {@see NewsSpringboardService}'s deduped sections for
 * the template.
 *
 * Renders against a real augmented grid entry, so the tag's Value/Values row
 * unwrapping is exercised, not just its array path.
 *
 * Mutation: tracked under App\Tags\NewsSpringboard (--covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Tags/NewsSpringboardTagTest.php
 */
uses(StacheIsolatedTestCase::class);

/** Create a `post_categories` entry. */
function tagCategory(string $id): void
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->collection('post_categories');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->id($id);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->slug($id);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->published(true);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->data(['title' => $id]);
    $entry->saveQuietly();
}

/**
 * Create a published news post.
 *
 * @param  array<int, string>  $categories
 */
function tagPost(string $id, string $date, array $categories): void
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->collection('posts');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->id($id);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->slug($id);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->locale('news');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->published(true);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->date(Carbon::parse($date));
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->data(['title' => 'Post '.$id, 'post_categories' => $categories]);
    $entry->saveQuietly();
}

/**
 * Make a collection with the given handle/title/sites (dated optional).
 *
 * @param  array<int, string>  $sites
 */
function tagCollection(string $handle, string $title, array $sites, bool $dated = false): void
{
    /** @var Collection $collection */
    $collection = CollectionFacade::make($handle);
    /** @var Collection $collection */
    $collection = $collection->title($title);
    /** @var Collection $collection */
    $collection = $collection->sites($sites);
    /** @var Collection $collection */
    $collection = $collection->dated($dated);
    $collection->save();
}

beforeEach(function (): void {
    tagCollection('post_categories', 'Post Categories', ['default']);
    tagCollection('posts', 'Posts', ['default', 'news'], dated: true);
    tagCollection('pages', 'Pages', ['default', 'news']);

    /** @var Statamic\Fields\Blueprint $postBlueprint */
    $postBlueprint = Blueprint::makeFromFields([
        'title' => ['type' => 'text'],
        'post_categories' => ['type' => 'entries', 'collections' => ['post_categories']],
    ]);
    /** @var Statamic\Fields\Blueprint $postBlueprint */
    $postBlueprint = $postBlueprint->setHandle('post');
    /** @var Statamic\Fields\Blueprint $postBlueprint */
    $postBlueprint = $postBlueprint->setNamespace('collections.posts');
    $postBlueprint->save();

    // A springboard blueprint whose `sections` grid mirrors the shipped one, so
    // the entry augments `sections` into real grid rows.
    /** @var Statamic\Fields\Blueprint $springboardBlueprint */
    $springboardBlueprint = Blueprint::makeFromFields([
        'title' => ['type' => 'text'],
        'sections' => [
            'type' => 'grid',
            'fields' => [
                ['handle' => 'section_heading', 'field' => ['type' => 'text']],
                ['handle' => 'category', 'field' => ['type' => 'entries', 'collections' => ['post_categories'], 'max_items' => 1]],
                ['handle' => 'count', 'field' => ['type' => 'integer']],
            ],
        ],
    ]);
    /** @var Statamic\Fields\Blueprint $springboardBlueprint */
    $springboardBlueprint = $springboardBlueprint->setHandle('news_springboard');
    /** @var Statamic\Fields\Blueprint $springboardBlueprint */
    $springboardBlueprint = $springboardBlueprint->setNamespace('collections.pages');
    $springboardBlueprint->save();

    tagCategory('cat-a');
    tagCategory('cat-b');

    tagPost('p1', '2024-05-01', ['cat-a']);
    tagPost('p2', '2024-04-01', ['cat-a']);
    tagPost('p3', '2024-03-01', ['cat-b']);
    tagPost('p4', '2024-02-01', ['cat-a', 'cat-b']);
    tagPost('p5', '2024-01-01', ['cat-b']);
});

it('renders deduped sections from the augmented sections grid', function () {
    /** @var Statamic\Entries\Entry $springboard */
    $springboard = Entry::make();
    /** @var Statamic\Entries\Entry $springboard */
    $springboard = $springboard->collection('pages');
    /** @var Statamic\Entries\Entry $springboard */
    $springboard = $springboard->id('sb');
    /** @var Statamic\Entries\Entry $springboard */
    $springboard = $springboard->slug('news');
    /** @var Statamic\Entries\Entry $springboard */
    $springboard = $springboard->locale('news');
    /** @var Statamic\Entries\Entry $springboard */
    $springboard = $springboard->published(true);
    /** @var Statamic\Entries\Entry $springboard */
    $springboard = $springboard->blueprint('news_springboard');
    /** @var Statamic\Entries\Entry $springboard */
    $springboard = $springboard->data([
        'title' => 'News',
        'sections' => [
            ['section_heading' => 'Cardiology', 'category' => ['cat-a'], 'count' => 3],
            ['section_heading' => 'Oncology', 'category' => ['cat-b'], 'count' => 3],
        ],
    ]);
    $springboard->saveQuietly();

    $template = '{{ news_springboard }}{{ if no_results }}NONE{{ else }}{{ sections }}[{{ heading }}:{{ posts }}{{ id }},{{ /posts }}]{{ /sections }}{{ /if }}{{ /news_springboard }}';

    /** @var array<string, mixed> $context */
    $context = $springboard->toAugmentedArray();
    $output = (string) Antlers::parse($template, $context);

    // Cardiology (cat-a, earlier) keeps p4; Oncology (cat-b, later) drops it.
    expect($output)->toContain('[Cardiology:p1,p2,p4,]');
    expect($output)->toContain('[Oncology:p3,p5,]');
});

it('reports no_results when the springboard has no sections', function () {
    $output = (string) Antlers::parse(
        '{{ news_springboard }}{{ if no_results }}NONE{{ else }}SOME{{ /if }}{{ /news_springboard }}',
        ['sections' => []]
    );

    expect($output)->toContain('NONE');
});
