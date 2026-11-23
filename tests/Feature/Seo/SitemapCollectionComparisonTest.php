<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use MityDigital\Sitemapamic\Data\Source;
use MityDigital\Sitemapamic\Models\SitemapamicUrl;
use MityDigital\Sitemapamic\Support\Content\UrlIterator;
use Statamic\Facades\Blink;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\URL;
use Statamic\Fields\Blueprint;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Structures\CollectionStructure;
use Statamic\Structures\Tree;
use Tests\Support\ContentSubmodule;
use Tests\Support\EntriesTermsAndTreesIsolatedTestCase;

// Entries, terms and trees isolated (#557): each case writes a collection's structure
// tree, and a tree write needs a root no sibling worker shares.
uses(EntriesTermsAndTreesIsolatedTestCase::class);

/*
|--------------------------------------------------------------------------
| The four collections #969 could not compare (#1232)
|--------------------------------------------------------------------------
|
| #969 compared the URL sets of Sitemapamic 3.5.1 and 4.0.0 on four sites and
| found them identical, but no `post_categories`, `post_tags`,
| `clinical_expertise` or `knowledge_bases` entry exists locally, so those
| collections were never compared. These cases provision a published entry in
| each, placed in its tree on the collection's site, and compare what 4.0.0
| emits for that collection and site with the URL set 3.5.1's loader produced:
| every entry on the site that is published, is not an external link, is not
| hidden by its date behavior and is not excluded by the
| `meta_include_in_xml_sitemap` mapping, each at
| `URL::makeAbsolute($entry->url())`. That rule is 3.5.1's
| `Sitemapamic::loadEntries()` at `0b7f7eb5b6a97aebd8d2c66d2569312e1705c815`,
| restated here because the package no longer carries it. The fixtures carry
| no `redirect`: 4.0.0 drops every redirecting entry where 3.5.1 dropped only an
| off-site link, a difference recorded in docs/DECISIONS.md rather than compared.
| Each fixture uses its collection's default blueprint (`knowledge_base` for
| `knowledge_bases`). The `clinical_expertise` `basic_page` and `landing_page`
| blueprints store a template whose view does not exist, so 4.0.0 leaves those
| entries out where 3.5.1 emitted them; that divergence is #1235's and is not
| compared here. The comparison covers every entry in the collection on the site,
| so it assumes the content carries no other entries there; one would make the
| case fail rather than pass.
|
| @command php artisan test --compact tests/Feature/Seo/SitemapCollectionComparisonTest.php
*/

/** Slug prefix for self-provisioned entries, so teardown is bulletproof. */
const SITEMAP_COMPARISON_TEST_PREFIX = 'zz-sitemap-comparison-test-';

/**
 * The four collections, each with the site its entries are provisioned on.
 *
 * @var array<string, string>
 */
const SITEMAP_COMPARISON_COLLECTIONS = [
    'post_categories' => 'default',
    'post_tags' => 'default',
    'clinical_expertise' => 'health',
    'knowledge_bases' => 'default',
];

/**
 * Remove every self-provisioned test entry from the four collections.
 *
 * Quiet delete, for the reason `SitemapTaxonomyTermsTest` records: the deleted
 * event runs every search index inline, and nothing here reads an index.
 */
function tearDownSitemapComparisonFixtures(): void
{
    foreach (array_keys(SITEMAP_COMPARISON_COLLECTIONS) as $handle) {
        // Content-less checkouts have none of the four collections.
        if (! CollectionFacade::find($handle)) {
            continue;
        }

        /** @var EntryQueryBuilder $query */
        $query = Entry::query();
        $query->where('collection', $handle);

        /** @var Collection<int, Statamic\Entries\Entry> $entries */
        $entries = $query->get();

        $entries
            ->filter(fn (Statamic\Entries\Entry $entry): bool => is_string($entry->slug()) && str_starts_with($entry->slug(), SITEMAP_COMPARISON_TEST_PREFIX))
            ->each(fn (Statamic\Entries\Entry $entry) => $entry->deleteQuietly());
    }
}

/**
 * The URLs Sitemapamic 3.5.1 emitted for one collection on one site.
 *
 * @param  string  $handle  The collection handle.
 * @param  string  $site  The site handle.
 * @return list<string>
 */
function sitemapamic351Locs(string $handle, string $site): array
{
    /** @var Statamic\Entries\Collection $collection */
    $collection = CollectionFacade::findByHandle($handle);
    $includeKey = 'meta_include_in_xml_sitemap';

    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', $handle);

    /** @var Collection<int, Statamic\Entries\Entry> $entries */
    $entries = $query->get();

    $locs = $entries
        ->filter(function (Statamic\Entries\Entry $entry) use ($site, $collection, $includeKey): bool {
            if ($entry->locale() !== $site || ! $entry->published()) {
                return false;
            }

            $redirect = $entry->get('redirect');
            $blueprint = $entry->blueprint();
            if ($blueprint instanceof Blueprint && $blueprint->handle() === 'link' && is_string($redirect) && URL::isExternal($redirect)) {
                return false;
            }

            $hidden = ['private', 'unlisted'];
            if (in_array($collection->futureDateBehavior(), $hidden, true) && $entry->date() > now()) {
                return false;
            }
            if (in_array($collection->pastDateBehavior(), $hidden, true) && $entry->date() < now()) {
                return false;
            }

            $include = $entry->get($includeKey) ?? $entry->getComputed($includeKey);

            return $include !== false && $include !== 'false';
        })
        ->map(fn (Statamic\Entries\Entry $entry): string => sitemapComparisonLoc($entry))
        ->sort()
        ->values()
        ->all();

    /** @var list<string> $locs */
    return $locs;
}

/**
 * The absolute URL 3.5.1 emitted for an entry: `URL::makeAbsolute($entry->url())`.
 *
 * @param  Statamic\Entries\Entry  $entry  The entry.
 */
function sitemapComparisonLoc(Statamic\Entries\Entry $entry): string
{
    $url = $entry->url();

    return URL::makeAbsolute(is_string($url) ? $url : '');
}

beforeEach(fn () => tearDownSitemapComparisonFixtures());
afterEach(fn () => tearDownSitemapComparisonFixtures());

it('emits the URL set 3.5.1 emitted for a collection #969 could not compare', function (string $handle, string $site): void {
    if (! CollectionFacade::find($handle)) {
        ContentSubmodule::reportMissing("{$handle} collection config");
    }

    // A published entry placed at the root of the collection's tree on the site, as
    // an import places it.
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection($handle);
    $entry->locale($site);

    // A root-level knowledge-base entry must be a knowledge base, or the tree
    // validator (App\Listeners\ValidateKnowledgeBaseTree) refuses the save.
    if ($handle === 'knowledge_bases') {
        $entry->blueprint('knowledge_base');
    }

    $entry->slug(SITEMAP_COMPARISON_TEST_PREFIX.$handle);
    $entry->data(['title' => 'ZZ Comparison '.$handle]);
    $entry->saveQuietly();

    /** @var Statamic\Entries\Collection $collection */
    $collection = CollectionFacade::findByHandle($handle);
    /** @var CollectionStructure $structure */
    $structure = $collection->structure();
    /** @var Tree $tree */
    $tree = $structure->in($site);
    /** @var list<array<string, mixed>> $branches */
    $branches = $tree->tree();

    // Saving may already have appended the entry; a second branch for it would throw.
    if (! collect($branches)->contains(fn (array $branch): bool => ($branch['entry'] ?? null) === $entry->id())) {
        $tree->tree([...$branches, ['entry' => $entry->id()]]);
    }

    $tree->save();

    Blink::flush();

    /** @var Statamic\Sites\Site $statamicSite */
    $statamicSite = Site::get($site);
    Site::setCurrent($statamicSite->handle());

    $emitted = collect(app(UrlIterator::class)->forSource(new Source($site, collection: $handle)))
        ->map(fn (SitemapamicUrl $url): string => $url->loc)
        ->sort()
        ->values()
        ->all();

    $expected = sitemapamic351Locs($handle, $site);

    // The provisioned entry is emitted, so the comparison is over a non-empty set...
    expect($emitted)->toContain(sitemapComparisonLoc($entry))
        // ...and 4.0.0 emits exactly what 3.5.1 did for this collection and site.
        ->and($emitted)->toBe($expected);
})->with(array_map(
    fn (string $handle, string $site): array => [$handle, $site],
    array_keys(SITEMAP_COMPARISON_COLLECTIONS),
    SITEMAP_COMPARISON_COLLECTIONS,
));
