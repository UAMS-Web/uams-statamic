<?php

declare(strict_types=1);

use App\Services\Posts\NewsSyndicationQueryService;
use Illuminate\Support\Collection;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\Support\StacheIsolatedTestCase;

/**
 * UAMS-Web/uams-statamic-archive#1598 — proves the runtime join a Departmental News Hub (UAMS-Web/uams-statamic-archive#1602) and its news
 * archive (UAMS-Web/uams-statamic-archive#1599) depend on: `posts` filtered by an associated `organizations`
 * entry. `related_organizations` stores an array of org entry ids, so the hub's
 * query is a `whereJsonContains('related_organizations', <orgId>)` — the same
 * JSON-membership shape {@see NewsSyndicationQueryService} already uses for
 * `post_categories`.
 *
 * Builds real collections/entries in an isolated Stache (throwaway disk), so the
 * query exercises the actual Stache query layer, not a mock.
 *
 * @command  php artisan test --compact tests/Feature/Services/Posts/PostOrganizationQueryTest.php
 */
uses(StacheIsolatedTestCase::class);

/** Create an `organizations` entry (default site) with the given id. */
function postOrgEntry(string $id, string $title): void
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
 * Create a `posts` entry associated with the given `organizations` entry ids.
 *
 * @param  array<int, string>  $organizations
 */
function postWithOrgs(string $id, array $organizations): void
{
    $data = ['title' => 'Post '.$id];
    if ($organizations !== []) {
        $data['related_organizations'] = $organizations;
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
    $entry = $entry->locale('news');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->published(true);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->data($data);
    $entry->saveQuietly();
}

beforeEach(function (): void {
    // Organizations live only on the default site (no per-site localization).
    /** @var Statamic\Entries\Collection $organizations */
    $organizations = CollectionFacade::make('organizations');
    /** @var Statamic\Entries\Collection $organizations */
    $organizations = $organizations->title('Organizations');
    /** @var Statamic\Entries\Collection $organizations */
    $organizations = $organizations->sites(['default']);
    $organizations->save();

    // Posts are dated and span the default + news sites.
    /** @var Statamic\Entries\Collection $posts */
    $posts = CollectionFacade::make('posts');
    /** @var Statamic\Entries\Collection $posts */
    $posts = $posts->title('Posts');
    /** @var Statamic\Entries\Collection $posts */
    $posts = $posts->sites(['default', 'news']);
    /** @var Statamic\Entries\Collection $posts */
    $posts = $posts->dated(true);
    $posts->save();

    // A minimal posts blueprint carrying the association field under test.
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::makeFromFields([
        'title' => ['type' => 'text'],
        'related_organizations' => [
            'type' => 'entries',
            'collections' => ['organizations'],
        ],
    ]);
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = $blueprint->setHandle('post');
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = $blueprint->setNamespace('collections.posts');
    $blueprint->save();
});

it('finds only the posts associated with a given organization', function () {
    postOrgEntry('org-anesthesiology', 'Department of Anesthesiology');
    postOrgEntry('org-cardiology', 'Department of Cardiology');

    postWithOrgs('p1', ['org-anesthesiology']);
    postWithOrgs('p2', ['org-cardiology']);
    postWithOrgs('p3', ['org-anesthesiology', 'org-cardiology']);
    postWithOrgs('p4', []);

    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'posts');
    $query->whereJsonContains('related_organizations', 'org-anesthesiology');

    /** @var Collection<int, Statamic\Entries\Entry> $results */
    $results = $query->get();

    $ids = $results
        ->map(function (Statamic\Entries\Entry $entry): string {
            /** @var string $id */
            $id = $entry->id();

            return (string) $id;
        })
        ->all();

    sort($ids);

    // p1 (only anesthesiology) and p3 (both) match; p2 (cardiology only) and p4
    // (no association) do not.
    expect($ids)->toBe(['p1', 'p3']);
});
