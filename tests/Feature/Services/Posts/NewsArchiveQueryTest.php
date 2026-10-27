<?php

declare(strict_types=1);

use App\Services\Posts\NewsSyndicationQueryService;
use App\Support\Multisite\DepartmentHubResolver;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Statamic\Entries\Collection;
use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Behavioral coverage for {@see NewsSyndicationQueryService::paginateArchive()},
 * the org × type × source filterable, sticky-first, paginated Departmental News
 * Hub archive (UAMS-Web/uams-statamic-archive#1599).
 *
 * The content snapshot ships zero `posts`, so every case builds its own fixtures
 * in an isolated Stache (real collections/entries, throwaway disk) and exercises
 * the actual cross-site `whereIn('site')`, `whereIn('blueprint')`,
 * `whereJsonOverlaps('related_organizations')` and status filters — not a mock.
 *
 * Helpers are prefixed `newsArchive*` so they never collide with the `newsSvc*`
 * helpers in {@see NewsSyndicationQueryServiceTest} (a duplicate top-level
 * function would fatal the whole suite).
 *
 * @covers \App\Services\Posts\NewsSyndicationQueryService::paginateArchive
 *
 * @command  php artisan test --compact tests/Feature/Services/Posts/NewsArchiveQueryTest.php
 */
uses(StacheIsolatedTestCase::class);

/** Resolve the service under test. */
function newsArchiveSvc(): NewsSyndicationQueryService
{
    return app(NewsSyndicationQueryService::class);
}

/** Create an `organizations` entry (default site) with the given id/title. */
function newsArchiveOrg(string $id, string $title): void
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->collection('organizations');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->id($id);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->slug($id);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->locale('default');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->published(true);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->data(['title' => $title]);
    $entry->saveQuietly();
}

/**
 * Persist a fixture post carrying the five dimensions the archive filters/orders
 * on: site, date, org association, blueprint type, and the sticky flag.
 *
 * @param  array<int, string>  $orgs  `organizations` entry ids for `related_organizations`.
 */
function newsArchivePost(
    string $id,
    string $site,
    string $date,
    bool $published,
    array $orgs = [],
    string $blueprint = 'post',
    bool $sticky = false
): void {
    $data = ['title' => 'Post '.$id, 'sticky_query' => $sticky];
    if ($orgs !== []) {
        $data['related_organizations'] = $orgs;
    }

    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->collection('posts');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->id($id);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->slug($id);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->locale($site);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->blueprint($blueprint);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->published($published);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->date(Carbon::parse($date));
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->data($data);
    $entry->saveQuietly();
}

/**
 * Map a paginator's current page of items to entry ids for order-sensitive
 * assertions.
 *
 * @param  LengthAwarePaginator<int, Statamic\Entries\Entry>  $paginator
 * @return array<int, string>
 */
function newsArchiveIds(LengthAwarePaginator $paginator): array
{
    return collect($paginator->items())
        ->map(function (Statamic\Entries\Entry $entry): string {
            /** @var string $id */
            $id = $entry->id();

            return (string) $id;
        })
        ->all();
}

beforeEach(function (): void {
    // Organizations live only on the default site (no per-site localization).
    /** @var Collection $organizations */
    $organizations = CollectionFacade::make('organizations');
    /** @var Collection $organizations */
    $organizations = $organizations->title('Organizations');
    /** @var Collection $organizations */
    $organizations = $organizations->sites(['default']);
    $organizations->save();

    // Posts span the default + news sites, dated.
    /** @var Collection $posts */
    $posts = CollectionFacade::make('posts');
    /** @var Collection $posts */
    $posts = $posts->title('Posts');
    /** @var Collection $posts */
    $posts = $posts->sites(['default', 'news']);
    /** @var Collection $posts */
    $posts = $posts->dated(true);
    $posts->save();

    // Two blueprints on the collection so the `type` axis has something to
    // narrow by; both carry the association + behavior fields under test.
    foreach (['post', 'video'] as $handle) {
        /** @var Statamic\Fields\Blueprint $blueprint */
        $blueprint = Blueprint::makeFromFields([
            'title' => ['type' => 'text'],
            'related_organizations' => [
                'type' => 'entries',
                'collections' => ['organizations'],
            ],
            'sticky_query' => ['type' => 'toggle'],
        ]);
        /** @var Statamic\Fields\Blueprint $blueprint */
        $blueprint = $blueprint->setHandle($handle);
        /** @var Statamic\Fields\Blueprint $blueprint */
        $blueprint = $blueprint->setNamespace('collections.posts');
        $blueprint->save();
    }

    newsArchiveOrg('org-anesthesiology', 'Department of Anesthesiology');
    newsArchiveOrg('org-cardio', 'Department of Cardiology');
});

it('filters to only the posts associated with the given organization', function (): void {
    newsArchivePost('p-anesthesiology', 'news', '2024-01-01', true, ['org-anesthesiology']);
    newsArchivePost('p-cardio', 'news', '2024-02-01', true, ['org-cardio']);
    newsArchivePost('p-both', 'news', '2024-03-01', true, ['org-anesthesiology', 'org-cardio']);
    newsArchivePost('p-none', 'news', '2024-04-01', true, []);

    $page = newsArchiveSvc()->paginateArchive(['news'], ['org-anesthesiology'], [], [], 10, 1);

    // Newest-first: p-both (Mar) then p-anesthesiology (Jan). p-cardio and p-none excluded.
    expect(newsArchiveIds($page))->toBe(['p-both', 'p-anesthesiology']);
});

it('applies no organization filter for an empty org list (news-only degrade)', function (): void {
    newsArchivePost('p-anesthesiology', 'news', '2024-01-01', true, ['org-anesthesiology']);
    newsArchivePost('p-none', 'news', '2024-02-01', true, []);

    $page = newsArchiveSvc()->paginateArchive(['news'], [], [], [], 10, 1);

    // With no org constraint every published news post surfaces, newest first.
    expect(newsArchiveIds($page))->toBe(['p-none', 'p-anesthesiology']);
});

it('filters by blueprint type', function (): void {
    newsArchivePost('a-post', 'news', '2024-01-01', true, ['org-anesthesiology'], 'post');
    newsArchivePost('a-video', 'news', '2024-02-01', true, ['org-anesthesiology'], 'video');

    $page = newsArchiveSvc()->paginateArchive(['news'], ['org-anesthesiology'], ['video'], [], 10, 1);

    expect(newsArchiveIds($page))->toBe(['a-video']);
});

it('includes several types when several are requested', function (): void {
    newsArchivePost('a-post', 'news', '2024-01-01', true, ['org-anesthesiology'], 'post');
    newsArchivePost('a-video', 'news', '2024-02-01', true, ['org-anesthesiology'], 'video');

    $page = newsArchiveSvc()->paginateArchive(['news'], ['org-anesthesiology'], ['post', 'video'], [], 10, 1);

    expect(newsArchiveIds($page))->toBe(['a-video', 'a-post']);
});

it('filters by source site', function (): void {
    newsArchivePost('news-post', 'news', '2024-01-01', true, ['org-anesthesiology']);
    newsArchivePost('local-post', 'default', '2024-02-01', true, ['org-anesthesiology']);

    // Newsroom only.
    $newsroom = newsArchiveSvc()->paginateArchive(['news'], ['org-anesthesiology'], [], [], 10, 1);
    expect(newsArchiveIds($newsroom))->toBe(['news-post']);

    // Local only.
    $local = newsArchiveSvc()->paginateArchive(['default'], ['org-anesthesiology'], [], [], 10, 1);
    expect(newsArchiveIds($local))->toBe(['local-post']);

    // Both, merged newest-first.
    $both = newsArchiveSvc()->paginateArchive(['news', 'default'], ['org-anesthesiology'], [], [], 10, 1);
    expect(newsArchiveIds($both))->toBe(['local-post', 'news-post']);
});

it('orders sticky posts first, then by date descending', function (): void {
    // An older sticky post must outrank a newer non-sticky one; within each group
    // date-desc holds.
    newsArchivePost('new-plain', 'news', '2024-05-01', true, ['org-anesthesiology'], 'post', sticky: false);
    newsArchivePost('old-sticky', 'news', '2024-01-01', true, ['org-anesthesiology'], 'post', sticky: true);
    newsArchivePost('mid-sticky', 'news', '2024-03-01', true, ['org-anesthesiology'], 'post', sticky: true);
    newsArchivePost('old-plain', 'news', '2024-02-01', true, ['org-anesthesiology'], 'post', sticky: false);

    $page = newsArchiveSvc()->paginateArchive(['news'], ['org-anesthesiology'], [], [], 10, 1);

    // Sticky (mid then old) first, then plain (new then old).
    expect(newsArchiveIds($page))->toBe(['mid-sticky', 'old-sticky', 'new-plain', 'old-plain']);
});

it('excludes drafts', function (): void {
    newsArchivePost('published', 'news', '2024-02-01', true, ['org-anesthesiology']);
    newsArchivePost('draft', 'news', '2024-03-01', false, ['org-anesthesiology']);

    $page = newsArchiveSvc()->paginateArchive(['news'], ['org-anesthesiology'], [], [], 10, 1);

    expect(newsArchiveIds($page))->toBe(['published']);
});

it('paginates: the page size, page slice, total and last-page are correct', function (): void {
    // Five matching posts, two per page → three pages.
    foreach (range(1, 5) as $n) {
        $day = str_pad((string) (10 - $n), 2, '0', STR_PAD_LEFT);
        newsArchivePost('p'.$n, 'news', '2024-01-'.$day, true, ['org-anesthesiology']);
    }

    $svc = newsArchiveSvc();

    $pageOne = $svc->paginateArchive(['news'], ['org-anesthesiology'], [], [], 2, 1);
    expect($pageOne->total())->toBe(5)
        ->and($pageOne->lastPage())->toBe(3)
        ->and($pageOne->currentPage())->toBe(1)
        ->and(newsArchiveIds($pageOne))->toBe(['p1', 'p2']);

    $pageThree = $svc->paginateArchive(['news'], ['org-anesthesiology'], [], [], 2, 3);
    expect(newsArchiveIds($pageThree))->toBe(['p5'])
        ->and($pageThree->currentPage())->toBe(3);
});

it('combines org, type and source filters', function (): void {
    // Only a video, on the news site, associated with org-anesthesiology should survive all three.
    newsArchivePost('match', 'news', '2024-04-01', true, ['org-anesthesiology'], 'video');
    newsArchivePost('wrong-type', 'news', '2024-03-01', true, ['org-anesthesiology'], 'post');
    newsArchivePost('wrong-org', 'news', '2024-02-01', true, ['org-cardio'], 'video');
    newsArchivePost('wrong-site', 'default', '2024-01-01', true, ['org-anesthesiology'], 'video');

    $page = newsArchiveSvc()->paginateArchive(['news'], ['org-anesthesiology'], ['video'], [], 10, 1);

    expect(newsArchiveIds($page))->toBe(['match']);
});

it('returns an empty page for an empty site list rather than an unscoped query', function (): void {
    newsArchivePost('p-anesthesiology', 'news', '2024-01-01', true, ['org-anesthesiology']);

    $page = newsArchiveSvc()->paginateArchive([], ['org-anesthesiology'], [], [], 10, 1);

    expect($page->total())->toBe(0)
        ->and(newsArchiveIds($page))->toBe([]);
});

// ---------------------------------------------------------------------------
// {{ news_archive }} tag (App\Tags\NewsArchive) — the delivery path
// ---------------------------------------------------------------------------

it('the tag filters by organization, source and type', function (): void {
    newsArchivePost('match', 'news', '2024-04-01', true, ['org-anesthesiology'], 'video');
    newsArchivePost('wrong-type', 'news', '2024-03-01', true, ['org-anesthesiology'], 'post');
    newsArchivePost('wrong-org', 'news', '2024-02-01', true, ['org-cardio'], 'video');

    $out = (string) Antlers::parse(
        '{{ news_archive organization="org-anesthesiology" source="newsroom" type="video" as="items" }}{{ if no_results }}NONE{{ else }}{{ items }}[{{ id }}]{{ /items }}{{ /if }}{{ /news_archive }}',
        []
    );

    expect($out)->toContain('[match]')
        ->and($out)->not->toContain('[wrong-type]')
        ->and($out)->not->toContain('[wrong-org]');
});

it('the tag resolves the organization from the current site when no org param is given', function (): void {
    newsArchivePost('anesthesiology-post', 'news', '2024-02-01', true, ['org-anesthesiology']);
    newsArchivePost('cardio-post', 'news', '2024-01-01', true, ['org-cardio']);

    // Stub the site→org bridge (UAMS-Web/uams-statamic-archive#1556) so the tag's site-org fallback resolves
    // org-anesthesiology for a real hub site (and null for the missing-link empty handle).
    app()->instance(DepartmentHubResolver::class, new class extends DepartmentHubResolver
    {
        public function organizationId(string $siteHandle): ?string
        {
            return $siteHandle === '' ? null : 'org-anesthesiology';
        }
    });

    $out = (string) Antlers::parse(
        '{{ news_archive source="newsroom" as="items" }}{{ items }}[{{ id }}]{{ /items }}{{ /news_archive }}',
        []
    );

    expect($out)->toContain('[anesthesiology-post]')
        ->and($out)->not->toContain('[cardio-post]');
});

it('the tag reads the page number from the query string, preserving the filter across pages', function (): void {
    // Three org posts, two per page → q3 alone on page 2.
    foreach (range(1, 3) as $n) {
        $day = str_pad((string) (10 - $n), 2, '0', STR_PAD_LEFT);
        newsArchivePost('q'.$n, 'news', '2024-01-'.$day, true, ['org-anesthesiology']);
    }

    app()->instance('request', Request::create('/news-archive?page=2'));

    $out = (string) Antlers::parse(
        '{{ news_archive organization="org-anesthesiology" source="newsroom" per_page="2" as="items" }}{{ items }}[{{ id }}]{{ /items }} page {{ paginate:current_page }}/{{ paginate:total_pages }}{{ /news_archive }}',
        []
    );

    expect($out)->toContain('[q3]')
        ->and($out)->not->toContain('[q1]')
        ->and($out)->not->toContain('[q2]')
        ->and($out)->toContain('page 2/2');
});

it('carries the active filter into the pagination URLs', function (): void {
    // Three videos, two per page → a next-page link exists. The active ?type=
    // filter must survive into that URL, or paging silently drops the filter.
    foreach (range(1, 3) as $n) {
        $day = str_pad((string) (10 - $n), 2, '0', STR_PAD_LEFT);
        newsArchivePost('v'.$n, 'news', '2024-01-'.$day, true, ['org-anesthesiology'], 'video');
    }

    app()->instance('request', Request::create('/news-archive?type=video'));

    $out = (string) Antlers::parse(
        '{{ news_archive organization="org-anesthesiology" source="newsroom" per_page="2" }}next={{ paginate:next_page }}{{ /news_archive }}',
        []
    );

    expect($out)->toContain('page=2')
        ->toContain('type=video');
});

it('the tag reports no_results when nothing matches', function (): void {
    newsArchivePost('anesthesiology', 'news', '2024-01-01', true, ['org-anesthesiology']);

    $out = (string) Antlers::parse(
        '{{ news_archive organization="org-cardio" source="newsroom" as="items" }}{{ if no_results }}NONE{{ else }}SOME{{ /if }}{{ /news_archive }}',
        []
    );

    expect($out)->toContain('NONE');
});

it('the tag exposes the active type and source for the filter notice', function (): void {
    newsArchivePost('anesthesiology', 'news', '2024-01-01', true, ['org-anesthesiology'], 'video');

    $out = (string) Antlers::parse(
        '{{ news_archive organization="org-anesthesiology" source="newsroom" type="video" }}t={{ active_type }} s={{ active_source }}{{ /news_archive }}',
        []
    );

    expect($out)->toContain('t=video')
        ->and($out)->toContain('s=newsroom');
});

it('does not leak an unknown or hostile type/source into the active-filter state', function (): void {
    newsArchivePost('anesthesiology', 'news', '2024-01-01', true, ['org-anesthesiology'], 'post');

    // `?type=` / `?source=` are attacker-controllable and the view renders the
    // active state raw, so an unrecognized type must not be exposed and an unknown
    // source must fall back to the safe `both` literal — the markup never reaches
    // the template.
    $out = (string) Antlers::parse(
        '{{ news_archive organization="org-anesthesiology" type="<script>alert(1)</script>" source="javascript:evil" }}t=[{{ active_type }}] s=[{{ active_source }}]{{ /news_archive }}',
        []
    );

    expect($out)->toContain('t=[]')
        ->and($out)->toContain('s=[both]')
        ->and($out)->not->toContain('<script>');
});
