<?php

declare(strict_types=1);

use App\Services\Posts\NewsSpringboardService;
use App\Services\Posts\NewsSyndicationQueryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert;
use Statamic\Entries\Entry as EntryObject;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Coverage for the Departmental News Hub magazine (UAMS-Web/uams-statamic-archive#1602): the org-filtered,
 * sticky-first lead + per-blueprint type sections with **offset** dedup
 * ({@see NewsSpringboardService::resolveDepartmentMagazine()} +
 * {@see NewsSyndicationQueryService::queryTypeSection()}).
 *
 * Offset dedup is deliberately distinct from the category magazine's cross-section
 * accumulator dedup (UAMS-Web/uams-statamic-archive#1609): a type section skips that type's lead count, so a
 * lead post never repeats in its own type section.
 *
 * Helpers are prefixed `deptMag*` to avoid colliding with other suites.
 *
 * @covers \App\Services\Posts\NewsSpringboardService::resolveDepartmentMagazine
 * @covers \App\Services\Posts\NewsSyndicationQueryService::queryTypeSection
 *
 * @command  php artisan test --compact tests/Feature/Services/Posts/DepartmentMagazineTest.php
 */
uses(StacheIsolatedTestCase::class);

/** Resolve the magazine service. */
function deptMagSvc(): NewsSpringboardService
{
    return app(NewsSpringboardService::class);
}

/**
 * Create a published news post of the given blueprint type, date, sticky flag,
 * org associations, and site.
 *
 * @param  array<int, string>  $orgs
 */
function deptMagPost(
    string $id,
    string $blueprint,
    string $date,
    bool $sticky,
    array $orgs,
    string $site = 'news'
): void {
    $data = ['title' => 'Post '.$id, 'sticky_query' => $sticky];
    if ($orgs !== []) {
        $data['related_organizations'] = $orgs;
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
    $entry = $entry->locale($site);
    /** @var EntryObject $entry */
    $entry = $entry->blueprint($blueprint);
    /** @var EntryObject $entry */
    $entry = $entry->published(true);
    /** @var EntryObject $entry */
    $entry = $entry->date(Carbon::parse($date));
    /** @var EntryObject $entry */
    $entry = $entry->data($data);
    $entry->saveQuietly();
}

/**
 * Map an entry collection to ids (order-sensitive).
 *
 * @return array<int, string>
 */
function deptMagIds(mixed $entries): array
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

/**
 * Find a resolved type section by its blueprint type.
 *
 * @param  array<int, array{type: string, heading: ?string, posts: Collection<int, EntryObject>}>  $sections
 * @return array{type: string, heading: ?string, posts: Collection<int, EntryObject>}|null
 */
function deptMagSection(array $sections, string $type): ?array
{
    foreach ($sections as $section) {
        if ($section['type'] === $type) {
            return $section;
        }
    }

    return null;
}

beforeEach(function (): void {
    /** @var Statamic\Entries\Collection $organizations */
    $organizations = CollectionFacade::make('organizations');
    /** @var Statamic\Entries\Collection $organizations */
    $organizations = $organizations->title('Organizations');
    /** @var Statamic\Entries\Collection $organizations */
    $organizations = $organizations->sites(['default']);
    $organizations->save();

    /** @var Statamic\Entries\Collection $posts */
    $posts = CollectionFacade::make('posts');
    /** @var Statamic\Entries\Collection $posts */
    $posts = $posts->title('Posts');
    /** @var Statamic\Entries\Collection $posts */
    $posts = $posts->sites(['default', 'news']);
    /** @var Statamic\Entries\Collection $posts */
    $posts = $posts->dated(true);
    $posts->save();

    foreach (['post', 'video', 'audio', 'news_release'] as $handle) {
        /** @var Statamic\Fields\Blueprint $blueprint */
        $blueprint = Blueprint::makeFromFields([
            'title' => ['type' => 'text'],
            'related_organizations' => ['type' => 'entries', 'collections' => ['organizations']],
            'sticky_query' => ['type' => 'toggle'],
        ]);
        /** @var Statamic\Fields\Blueprint $blueprint */
        $blueprint = $blueprint->setHandle($handle);
        /** @var Statamic\Fields\Blueprint $blueprint */
        $blueprint = $blueprint->setNamespace('collections.posts');
        $blueprint->save();
    }
});

it('offsets each type section past that type\'s lead posts (offset dedup)', function (): void {
    // Two sticky posts lead: vv1 (video, Jun) then pp1 (post, May). The post
    // section must start at pp2 (skipping pp1, the one post in the lead), and the
    // video section is empty after vv1 (its only video), so it is dropped.
    deptMagPost('vv1', 'video', '2024-06-01', sticky: true, orgs: ['org-anesthesiology']);
    deptMagPost('pp1', 'post', '2024-05-01', sticky: true, orgs: ['org-anesthesiology']);
    deptMagPost('pp2', 'post', '2024-04-01', sticky: false, orgs: ['org-anesthesiology']);
    deptMagPost('pp3', 'post', '2024-03-01', sticky: false, orgs: ['org-anesthesiology']);

    $magazine = deptMagSvc()->resolveDepartmentMagazine(['lead_count' => 2], ['org-anesthesiology'], 'default');

    expect(deptMagIds($magazine['lead']))->toBe(['vv1', 'pp1']);

    $postSection = deptMagSection($magazine['sections'], 'post');
    Assert::assertNotNull($postSection);
    // pp1 is in the lead, so the post section offsets past it.
    expect(deptMagIds($postSection['posts']))->toBe(['pp2', 'pp3']);

    // The lone video is in the lead; its section deduplicates to empty and is dropped.
    expect(deptMagSection($magazine['sections'], 'video'))->toBeNull();
});

it('filters the whole hub to the department organization', function (): void {
    deptMagPost('mine', 'post', '2024-05-01', sticky: false, orgs: ['org-anesthesiology']);
    deptMagPost('theirs', 'post', '2024-06-01', sticky: false, orgs: ['org-cardio']);
    deptMagPost('none', 'post', '2024-07-01', sticky: false, orgs: []);

    $magazine = deptMagSvc()->resolveDepartmentMagazine(['lead_count' => 5], ['org-anesthesiology'], 'default');

    // Only the org-anesthesiology post surfaces, in the lead; the section then has nothing left.
    expect(deptMagIds($magazine['lead']))->toBe(['mine'])
        ->and(deptMagSection($magazine['sections'], 'post'))->toBeNull();
});

it('includes the department\'s local posts alongside the newsroom', function (): void {
    // A hub pulls dept-filtered central news + the site's own org-tagged local posts.
    deptMagPost('newsroom', 'post', '2024-05-01', sticky: false, orgs: ['org-anesthesiology'], site: 'news');
    deptMagPost('local', 'post', '2024-06-01', sticky: false, orgs: ['org-anesthesiology'], site: 'default');

    $magazine = deptMagSvc()->resolveDepartmentMagazine(['lead_count' => 5], ['org-anesthesiology'], 'default');

    // Both sites' org posts merge newest-first.
    expect(deptMagIds($magazine['lead']))->toBe(['local', 'newsroom']);
});

it('queryTypeSection skips the offset and keeps only the requested type', function (): void {
    deptMagPost('v1', 'video', '2024-05-01', sticky: false, orgs: ['org-anesthesiology']);
    deptMagPost('v2', 'video', '2024-04-01', sticky: false, orgs: ['org-anesthesiology']);
    deptMagPost('v3', 'video', '2024-03-01', sticky: false, orgs: ['org-anesthesiology']);
    deptMagPost('p1', 'post', '2024-06-01', sticky: false, orgs: ['org-anesthesiology']);

    $svc = app(NewsSyndicationQueryService::class);

    // Videos only, offset 1 (skip v1), take 5 → v2, v3. The post is excluded by type.
    expect(deptMagIds($svc->queryTypeSection(['news'], ['org-anesthesiology'], 'video', 1, 5)))->toBe(['v2', 'v3']);
});

it('honors the section hide threshold', function (): void {
    // lead_count 1 leaves the post section with p2 + p3 (2 posts): threshold 3
    // hides it, threshold 1 keeps it.
    deptMagPost('p1', 'post', '2024-05-01', sticky: false, orgs: ['org-anesthesiology']);
    deptMagPost('p2', 'post', '2024-04-01', sticky: false, orgs: ['org-anesthesiology']);
    deptMagPost('p3', 'post', '2024-03-01', sticky: false, orgs: ['org-anesthesiology']);

    $svc = deptMagSvc();

    $hidden = $svc->resolveDepartmentMagazine(
        ['lead_count' => 1, 'section_threshold' => 3, 'section_types' => [['type' => 'post', 'count' => 5]]],
        ['org-anesthesiology'],
        'default'
    );
    expect(deptMagSection($hidden['sections'], 'post'))->toBeNull();

    $shown = $svc->resolveDepartmentMagazine(
        ['lead_count' => 1, 'section_threshold' => 1, 'section_types' => [['type' => 'post', 'count' => 5]]],
        ['org-anesthesiology'],
        'default'
    );
    $shownSection = deptMagSection($shown['sections'], 'post');
    Assert::assertNotNull($shownSection);
    expect(deptMagIds($shownSection['posts']))->toBe(['p2', 'p3']);
});
