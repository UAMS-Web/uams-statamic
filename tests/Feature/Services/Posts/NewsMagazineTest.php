<?php

declare(strict_types=1);

use App\Services\Posts\NewsSpringboardService;
use App\Services\Posts\NewsSyndicationQueryService;
use App\Tags\NewsHome;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Entries\Entry as EntryObject;
use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Sites\Site as SiteModel;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Coverage for the news-magazine home composition (UAMS-Web/uams-statamic-archive#1612): the sticky-first lead
 * ({@see NewsSyndicationQueryService::queryLead()}), the lead→sections page-wide
 * dedup ({@see NewsSpringboardService::resolveMagazine()}), and the {{ news_home }}
 * tag that renders the whole newsroom (no org filter).
 *
 * Fixtures live in an isolated Stache (real `posts` / `post_categories`, throwaway
 * disk). Helpers are prefixed `mag*` so they never collide with other suites'
 * top-level functions.
 *
 * Mutation: tracked under App\Tags\NewsHome (--covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Services/Posts/NewsMagazineTest.php
 */
uses(StacheIsolatedTestCase::class, MockeryPHPUnitIntegration::class);

covers(NewsHome::class);

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``.
 */
beforeEach(function (): void {
    static $padded = false;
    if ($padded) {
        return;
    }

    if (runningUnderMutate()) {
        usleep(20_000_000);
        $padded = true;
    }
});

/** Resolve the springboard (magazine) service. */
function magSvc(): NewsSpringboardService
{
    return app(NewsSpringboardService::class);
}

/** Resolve the news query service. */
function magNews(): NewsSyndicationQueryService
{
    return app(NewsSyndicationQueryService::class);
}

/** Create a `post_categories` entry. */
function magCategory(string $id): void
{
    /** @var EntryObject $entry */
    $entry = Entry::make();
    /** @var EntryObject $entry */
    $entry = $entry->collection('post_categories');
    /** @var EntryObject $entry */
    $entry = $entry->id($id);
    /** @var EntryObject $entry */
    $entry = $entry->slug($id);
    /** @var EntryObject $entry */
    $entry = $entry->published(true);
    /** @var EntryObject $entry */
    $entry = $entry->data(['title' => ucfirst($id)]);
    $entry->saveQuietly();
}

/**
 * Create a published news post with a date, sticky flag, and categories.
 *
 * @param  array<int, string>  $categories
 */
function magPost(string $id, string $date, bool $sticky, array $categories = []): void
{
    $data = ['title' => 'Post '.$id, 'sticky_query' => $sticky];
    if ($categories !== []) {
        $data['post_categories'] = $categories;
    }

    /** @var EntryObject $entry */
    $entry = Entry::make();
    /** @var EntryObject $entry */
    $entry = $entry->collection('posts');
    /** @var EntryObject $entry */
    $entry = $entry->id($id);
    /** @var EntryObject $entry */
    $entry = $entry->slug($id);
    /** @var EntryObject $entry */
    $entry = $entry->locale('news');
    /** @var EntryObject $entry */
    $entry = $entry->published(true);
    /** @var EntryObject $entry */
    $entry = $entry->date(Carbon::parse($date));
    /** @var EntryObject $entry */
    $entry = $entry->data($data);
    $entry->saveQuietly();
}

/**
 * Wrap entries in the list collection the magazine service returns.
 *
 * @param  EntryObject  ...$entries  The entries, in order.
 * @return Collection<int, EntryObject> The entries as a list collection.
 */
function magEntries(EntryObject ...$entries): Collection
{
    return collect(array_values($entries));
}

/**
 * Map an entry collection (or a magazine result value) to ids (order-sensitive).
 *
 * @return array<int, string>
 */
function magIds(mixed $entries): array
{
    if (! is_iterable($entries)) {
        return [];
    }

    $ids = [];

    foreach ($entries as $entry) {
        if ($entry instanceof EntryObject && \is_string($entry->id())) {
            $ids[] = $entry->id();
        }
    }

    return $ids;
}

beforeEach(function (): void {
    /** @var Statamic\Entries\Collection $postCategories */
    $postCategories = CollectionFacade::make('post_categories');
    /** @var Statamic\Entries\Collection $postCategories */
    $postCategories = $postCategories->title('Post Categories');
    /** @var Statamic\Entries\Collection $postCategories */
    $postCategories = $postCategories->sites(['default']);
    $postCategories->save();

    /** @var Statamic\Entries\Collection $posts */
    $posts = CollectionFacade::make('posts');
    /** @var Statamic\Entries\Collection $posts */
    $posts = $posts->title('Posts');
    /** @var Statamic\Entries\Collection $posts */
    $posts = $posts->sites(['default', 'news']);
    /** @var Statamic\Entries\Collection $posts */
    $posts = $posts->dated(true);
    $posts->save();

    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::makeFromFields([
        'title' => ['type' => 'text'],
        'post_categories' => ['type' => 'entries', 'collections' => ['post_categories']],
        'sticky_query' => ['type' => 'toggle'],
    ]);
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = $blueprint->setHandle('post');
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = $blueprint->setNamespace('collections.posts');
    $blueprint->save();

    // A `pages` collection + the news-home blueprint, so a real entry augments
    // `lead_count` / `sections` the way the shipped template sees them.
    /** @var Statamic\Entries\Collection $pages */
    $pages = CollectionFacade::make('pages');
    /** @var Statamic\Entries\Collection $pages */
    $pages = $pages->title('Pages');
    /** @var Statamic\Entries\Collection $pages */
    $pages = $pages->sites(['default', 'news']);
    $pages->save();

    /** @var Statamic\Fields\Blueprint $homeBlueprint */
    $homeBlueprint = Blueprint::makeFromFields([
        'title' => ['type' => 'text'],
        'lead_count' => ['type' => 'integer'],
        'sections' => [
            'type' => 'grid',
            'fields' => [
                ['handle' => 'section_heading', 'field' => ['type' => 'text']],
                ['handle' => 'category', 'field' => ['type' => 'entries', 'collections' => ['post_categories'], 'max_items' => 1]],
                ['handle' => 'count', 'field' => ['type' => 'integer']],
            ],
        ],
    ]);
    /** @var Statamic\Fields\Blueprint $homeBlueprint */
    $homeBlueprint = $homeBlueprint->setHandle('news_home');
    /** @var Statamic\Fields\Blueprint $homeBlueprint */
    $homeBlueprint = $homeBlueprint->setNamespace('collections.pages');
    $homeBlueprint->save();

    magCategory('cat-a');
    magCategory('cat-b');
});

it('orders the lead sticky-first then newest, capped at the count', function (): void {
    magPost('new-plain', '2024-05-01', sticky: false);
    magPost('old-sticky', '2024-01-01', sticky: true);
    magPost('mid-plain', '2024-03-01', sticky: false);

    // Sticky (old-sticky) first, then newest plain (new-plain); capped at 2.
    expect(magIds(magNews()->queryLead(['news'], [], 2)))->toBe(['old-sticky', 'new-plain']);
});

it('keeps a lead post out of its category section (page-wide dedup)', function (): void {
    magPost('p1', '2024-05-01', sticky: true, categories: ['cat-a']);
    magPost('p2', '2024-04-01', sticky: false, categories: ['cat-a']);
    magPost('p3', '2024-03-01', sticky: false, categories: ['cat-a']);

    $magazine = magSvc()->resolveMagazine(
        ['lead_count' => 1, 'sections' => [['category' => ['cat-a'], 'count' => 5]]],
        'news'
    );

    /** @var Collection<int, EntryObject> $lead */
    $lead = $magazine['lead'];

    // p1 (sticky) leads; the cat-a section drops it and shows the rest.
    expect(magIds($lead))->toBe(['p1'])
        ->and(magIds($magazine['sections'][0]['posts']))->toBe(['p2', 'p3']);
});

it('deduplicates across sections earlier-wins, seeded by the lead', function (): void {
    // p-shared is in both categories; it should surface once, in the earliest
    // list that reaches it (the lead), and never again.
    magPost('p-shared', '2024-05-01', sticky: true, categories: ['cat-a', 'cat-b']);
    magPost('a-only', '2024-04-01', sticky: false, categories: ['cat-a']);
    magPost('b-only', '2024-03-01', sticky: false, categories: ['cat-b']);

    $magazine = magSvc()->resolveMagazine(
        [
            'lead_count' => 1,
            'sections' => [
                ['category' => ['cat-a'], 'count' => 5],
                ['category' => ['cat-b'], 'count' => 5],
            ],
        ],
        'news'
    );

    expect(magIds($magazine['lead']))->toBe(['p-shared'])
        ->and(magIds($magazine['sections'][0]['posts']))->toBe(['a-only'])
        ->and(magIds($magazine['sections'][1]['posts']))->toBe(['b-only']);
});

it('the news_home tag renders the lead and sections for the whole newsroom', function (): void {
    magPost('lead-1', '2024-05-01', sticky: true, categories: ['cat-a']);
    magPost('a-1', '2024-04-01', sticky: false, categories: ['cat-a']);
    magPost('b-1', '2024-03-01', sticky: false, categories: ['cat-b']);

    $context = [
        'lead_count' => 1,
        'sections' => [
            ['section_heading' => 'A News', 'category' => ['cat-a'], 'count' => 5],
            ['section_heading' => 'B News', 'category' => ['cat-b'], 'count' => 5],
        ],
    ];

    $template = '{{ news_home }}{{ if no_results }}NONE{{ else }}lead:{{ lead }}[{{ id }}]{{ /lead }} {{ sections }}{{ heading }}:{{ posts }}[{{ id }}]{{ /posts }};{{ /sections }}{{ /if }}{{ /news_home }}';

    $out = (string) Antlers::parse($template, $context);

    expect($out)->toContain('lead:[lead-1]')
        // The lead post never repeats in its section; the sections carry the rest.
        ->toContain('A News:[a-1];')
        ->toContain('B News:[b-1];')
        ->and($out)->not->toContain('A News:[lead-1]');
});

it('honors the lead_count field from a real augmented news-home entry', function (): void {
    magPost('p1', '2024-05-01', sticky: false, categories: ['cat-a']);
    magPost('p2', '2024-04-01', sticky: false, categories: ['cat-a']);
    magPost('p3', '2024-03-01', sticky: false, categories: ['cat-a']);

    // The shipped template renders `{{ news_home }}` with no lead_count param, so
    // production always takes the CONTEXT branch — where an entry field arrives
    // augmented as a Value, not a scalar. Render against a real entry so dropping
    // the Value unwrap (silently falling back to the default 5) fails here.
    /** @var EntryObject $home */
    $home = Entry::make();
    /** @var EntryObject $home */
    $home = $home->collection('pages');
    /** @var EntryObject $home */
    $home = $home->id('news-home');
    /** @var EntryObject $home */
    $home = $home->slug('news');
    /** @var EntryObject $home */
    $home = $home->locale('news');
    /** @var EntryObject $home */
    $home = $home->published(true);
    /** @var EntryObject $home */
    $home = $home->blueprint('news_home');
    /** @var EntryObject $home */
    $home = $home->data([
        'title' => 'News',
        'lead_count' => 1,
        'sections' => [['section_heading' => 'A News', 'category' => ['cat-a'], 'count' => 5]],
    ]);
    $home->saveQuietly();

    /** @var array<string, mixed> $context */
    $context = $home->toAugmentedArray();

    $out = (string) Antlers::parse(
        '{{ news_home }}lead:{{ lead }}[{{ id }}]{{ /lead }} {{ sections }}sec:{{ posts }}[{{ id }}]{{ /posts }}{{ /sections }}{{ /news_home }}',
        $context
    );

    // The entry's lead_count of 1 must win: a 5-post default would pull all three
    // into the lead and leave the section empty.
    expect($out)->toContain('lead:[p1]')
        ->toContain('sec:[p2][p3]')
        ->and($out)->not->toContain('lead:[p1][p2]');
});

it('honors a lead_count tag parameter, which arrives as a string', function (): void {
    magPost('p1', '2024-05-01', sticky: false, categories: ['cat-a']);
    magPost('p2', '2024-04-01', sticky: false, categories: ['cat-a']);

    $out = (string) Antlers::parse(
        '{{ news_home lead_count="1" }}lead:{{ lead }}[{{ id }}]{{ /lead }} {{ sections }}sec:{{ posts }}[{{ id }}]{{ /posts }}{{ /sections }}{{ /news_home }}',
        ['sections' => [['section_heading' => 'A News', 'category' => ['cat-a'], 'count' => 5]]]
    );

    // A count of 1 leaves the second post to the section; the default of 5 would
    // pull both into the lead.
    expect($out)->toContain('lead:[p1]')
        ->toContain('sec:[p2]');
});

it('the news_home tag reports no_results for an empty newsroom', function (): void {
    $out = (string) Antlers::parse(
        '{{ news_home }}{{ if no_results }}NONE{{ else }}SOME{{ /if }}{{ /news_home }}',
        ['sections' => []]
    );

    expect($out)->toContain('NONE');
});

/**
 * The handle of the site the NewsHome tag resolves as current.
 *
 * @return string The current site's handle.
 */
function magCurrentSiteHandle(): string
{
    return mustBeString(
        mustBe(SiteModel::class, Site::current(), 'the current site')->handle(),
        'the current site handle',
    );
}

/**
 * Build a NewsHome tag over a stubbed magazine service for narrow mutant pins.
 *
 * @param  Collection<int, EntryObject>  $lead
 * @param  array<int, array{heading: ?string, posts: Collection<int, EntryObject>, category_name: ?string, category_url: ?string}>  $sections
 * @param  array<string, mixed>  $params
 * @param  array<string, mixed>  $context
 * @return array{0: NewsHome, 1: MockInterface}
 */
function magNewsHomeWithStub(
    Collection $lead,
    array $sections,
    array $params = [],
    array $context = [],
    ?callable $configAssert = null
): array {
    $svc = Mockery::mock(NewsSpringboardService::class);
    $expectation = $svc->shouldReceive('resolveMagazine')->once();
    if ($configAssert instanceof Closure) {
        $expectation->withArgs(function (array $config, string $handle) use ($configAssert): bool {
            expect($handle)->toBe(magCurrentSiteHandle());
            $configAssert($config);

            return true;
        });
    } else {
        $expectation->withArgs(function (array $config, string $handle): bool {
            expect($handle)->toBe(magCurrentSiteHandle())
                ->and($config)->toHaveKey('lead_sources')
                ->and($config['lead_sources'])->toBe([]);

            return true;
        });
    }
    $expectation->andReturn([
        'lead' => $lead,
        'sections' => $sections,
    ]);
    app()->instance(NewsSpringboardService::class, $svc);

    $tag = new NewsHome;
    $tag->setContext($context);
    $tag->setParameters($params);

    return [$tag, $svc];
}

it('passes empty lead_sources and pins no_results when only sections have posts', function (): void {
    $sectionPost = Mockery::mock(EntryObject::class);
    $sections = [[
        'heading' => 'A',
        'posts' => magEntries($sectionPost),
        'category_name' => null,
        'category_url' => null,
    ]];

    [$tag] = magNewsHomeWithStub(
        magEntries(),
        $sections,
        context: ['sections' => [], 'lead_count' => 2],
    );

    $payload = $tag->index();

    // RemoveArrayItem on lead_sources is asserted in the stub; And→Or on
    // no_results would flip true when the lead is empty but a section has posts.
    expect($payload['no_results'])->toBeFalse()
        ->and($payload['lead'])->toBeEmpty()
        ->and($payload['sections'])->toBe($sections);
});

it('falls back to the default lead count for zero, negative, and non-numeric values', function (): void {
    foreach ([0, -3, '1abc', 'nope', null] as $leadCount) {
        [$tag] = magNewsHomeWithStub(
            magEntries(),
            [],
            context: ['lead_count' => $leadCount],
            configAssert: function (array $config): void {
                expect($config['lead_count'])->toBe(NewsSpringboardService::DEFAULT_LEAD_COUNT);
            },
        );

        $tag->index();
    }
});

it('prefers an explicit lead_count param over context and coerces to int', function (): void {
    [$tag] = magNewsHomeWithStub(
        magEntries(),
        [],
        params: ['lead_count' => '3'],
        context: ['lead_count' => 9],
        configAssert: function (array $config): void {
            // CoalesceRemoveLeft would keep the context 9; RemoveIntegerCast would
            // pass the string through.
            expect($config['lead_count'])->toBe(3);
        },
    );

    $tag->index();
});
