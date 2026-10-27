<?php

declare(strict_types=1);

use App\Scopes\ExcludeNonSearchableCollections;
use App\Scopes\ExcludeUnsearchableEntries;
use Illuminate\Support\Collection;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Coverage for App\Scopes\ExcludeUnsearchableEntries (UAMS-Web/uams-statamic-archive#1060/UAMS-Web/uams-statamic-archive#1438): the site-search
 * index query scope that drops entries an editor has flagged out of interactive
 * search (`searchable: false`) while leaving published / routable / sitemap status
 * untouched. The unified `default` index and the per-type `clinical_expertise` index
 * both apply this gate.
 *
 * An isolated Stache with real Area-of-Expertise entries (searchable unset / true /
 * false) drives the filter against the real query layer.
 *
 * @command php artisan test --compact tests/Feature/Scopes/ExcludeUnsearchableEntriesScopeTest.php
 */
uses(StacheIsolatedTestCase::class);

beforeEach(function (): void {
    $expertise = CollectionFacade::make('clinical_expertise');
    $expertise->title('Areas of Expertise');
    $expertise->routes('{slug}');
    $expertise->save();

    // No `searchable` value — the state of every entry predating the UAMS-Web/uams-statamic-archive#1060 toggle;
    // it must stay searchable (no silent flip).
    /** @var Statamic\Entries\Entry $aoeDefault */
    $aoeDefault = Entry::make();
    $aoeDefault->collection('clinical_expertise');
    $aoeDefault->slug('cardiology');
    $aoeDefault->published(true);
    $aoeDefault->data(['title' => 'Cardiology']);
    $aoeDefault->saveQuietly();
    $this->aoeDefault = $aoeDefault;

    // Explicitly opted in.
    /** @var Statamic\Entries\Entry $aoeOn */
    $aoeOn = Entry::make();
    $aoeOn->collection('clinical_expertise');
    $aoeOn->slug('oncology');
    $aoeOn->published(true);
    $aoeOn->data(['title' => 'Oncology', 'searchable' => true]);
    $aoeOn->saveQuietly();
    $this->aoeOn = $aoeOn;

    // Explicitly opted out — the broad parent that stays indexed for SEO but never
    // surfaces as its own search result.
    /** @var Statamic\Entries\Entry $aoeOff */
    $aoeOff = Entry::make();
    $aoeOff->collection('clinical_expertise');
    $aoeOff->slug('institute');
    $aoeOff->published(true);
    $aoeOff->data(['title' => 'Institute', 'searchable' => false]);
    $aoeOff->saveQuietly();
    $this->aoeOff = $aoeOff;
});

/**
 * Apply the gate to the Areas-of-Expertise query and return the surviving slugs.
 *
 * @return list<string>
 */
function unsearchableGatedSlugs(): array
{
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'clinical_expertise');
    ExcludeUnsearchableEntries::excludeUnsearchable($query);

    /** @var Collection<int, Statamic\Entries\Entry> $entries */
    $entries = $query->get();

    /** @var list<string> $slugs */
    $slugs = $entries
        ->map(function (Statamic\Entries\Entry $entry): string {
            /** @var string|null $slug */
            $slug = $entry->slug();

            return (string) $slug;
        })
        ->sort()
        ->values()
        ->all();

    return $slugs;
}

it('keeps entries with no searchable value and entries explicitly searchable', function (): void {
    expect(unsearchableGatedSlugs())->toEqualCanonicalizing(['cardiology', 'oncology']);
});

it('drops entries explicitly flagged searchable:false', function (): void {
    expect(unsearchableGatedSlugs())->not->toContain('institute');
});

it('applies the same gate through the registered scope handle', function (): void {
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'clinical_expertise');
    (new ExcludeUnsearchableEntries)->apply($query, []);

    /** @var Collection<int, Statamic\Entries\Entry> $entries */
    $entries = $query->get();
    $slugs = $entries->map(function (Statamic\Entries\Entry $entry): string {
        /** @var string|null $slug */
        $slug = $entry->slug();

        return (string) $slug;
    })->sort()->values()->all();
    expect($slugs)->toEqualCanonicalizing(['cardiology', 'oncology']);
});

it('excludes the flagged entry from search yet keeps it published and routable (#1438 AC3)', function (): void {
    // The gate drops the flagged Area of Expertise from the search query…
    expect(unsearchableGatedSlugs())->not->toContain('institute');

    // …while it stays a published, findable, routable entry (so it stays in the
    // sitemap and resolves at its URL — neither of which reads `searchable`). Both
    // halves are asserted on the same `institute` entry.
    /** @var Statamic\Entries\Entry $aoeOff */
    $aoeOff = $this->aoeOff;

    /** @var Statamic\Entries\Entry $found */
    $found = Entry::find($aoeOff->id());

    /** @var bool $isPublished */
    $isPublished = $found->published();

    /** @var string|null $foundUrl */
    $foundUrl = $found->url();

    expect($found)->not->toBeNull()
        ->and($isPublished)->toBeTrue()
        ->and((string) $foundUrl)->toBe('/institute');
});

it('keeps every reader-facing collection in the wildcard default index while dropping a searchable:false AoE (#1438 regression)', function (): void {
    // Regression guard for the wildcard `default` ("All results") index collapse: a
    // `whereNotIn('id', …)` on a collection:* query whose builder `$collections` is
    // empty makes whereStatus() misread the exclusion as a positive id filter and
    // drop every non-AoE collection. A non-AoE reader-facing entry must survive.
    $people = CollectionFacade::make('people');
    $people->title('People');
    $people->routes('{slug}');
    $people->save();

    /** @var Statamic\Entries\Entry $kirk */
    $kirk = Entry::make();
    $kirk->collection('people');
    $kirk->slug('kirk');
    $kirk->published(true);
    $kirk->data(['title' => 'James Kirk']);
    $kirk->saveQuietly();

    // Mirror the default index's provide(): the exclude_non_searchable_collections
    // scope on a wildcard query, THEN whereStatus — the exact path that collapsed.
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    (new ExcludeNonSearchableCollections)->apply($query, []);
    $query->whereStatus('published');

    /** @var Collection<int, Statamic\Entries\Entry> $entries */
    $entries = $query->get();
    $slugs = $entries->map(function (Statamic\Entries\Entry $entry): string {
        /** @var string|null $slug */
        $slug = $entry->slug();

        return (string) $slug;
    })->sort()->values()->all();

    expect($slugs)->toContain('kirk')          // the non-AoE collection survives (the collapse guard)
        ->and($slugs)->toContain('cardiology') // a searchable (default) AoE stays
        ->and($slugs)->not->toContain('institute'); // the flagged AoE is dropped
});

it('applies after whereStatus without tripping the collection-before-status guard (the contains() path, #1526)', function (): void {
    // Regression guard for the per-save membership path: Searchables\Entries::contains()
    // builds the query whereStatus()-FIRST, THEN applies this scope. A positive
    // whereIn('collection', …) added after the status clause throws "The collection
    // clause must come before the status clause" (EntryQueryBuilder), which reddened
    // every test that saved a published entry (news syndication, navigation, posts,
    // forms, widgets). The scope must fall back to a guard-free whereNotIn there.
    $people = CollectionFacade::make('people');
    $people->title('People');
    $people->routes('{slug}');
    $people->save();

    $postCategories = CollectionFacade::make('post_categories');
    $postCategories->title('Post Categories'); // an EXCLUDED_COLLECTIONS handle
    $postCategories->save();

    /** @var Statamic\Entries\Entry $readerFacing */
    $readerFacing = Entry::make();
    $readerFacing->collection('people');
    $readerFacing->slug('spock');
    $readerFacing->published(true);
    $readerFacing->data(['title' => 'Spock']);
    $readerFacing->saveQuietly();

    /** @var Statamic\Entries\Entry $excluded */
    $excluded = Entry::make();
    $excluded->collection('post_categories');
    $excluded->slug('news');
    $excluded->published(true);
    $excluded->data(['title' => 'News']);
    $excluded->saveQuietly();

    // Mirror contains(): whereStatus first, then the scope, then exists().
    $isMember = function (string $id): bool {
        /** @var EntryQueryBuilder $query */
        $query = Entry::query();
        $query->whereStatus('published');
        $query->where('id', $id);
        (new ExcludeNonSearchableCollections)->apply($query, []); // must NOT throw

        /** @var bool $exists */
        $exists = $query->exists();

        return $exists;
    };

    // A reader-facing published entry is a member; one in an excluded collection is not.
    /** @var string|null $readerFacingId */
    $readerFacingId = $readerFacing->id();

    /** @var string|null $excludedId */
    $excludedId = $excluded->id();

    expect($isMember((string) $readerFacingId))->toBeTrue()
        ->and($isMember((string) $excludedId))->toBeFalse();
});

it('keeps an entry outside the gated collections even when it stores searchable:false', function (): void {
    $people = CollectionFacade::make('people');
    $people->title('People');
    $people->routes('{slug}');
    $people->save();

    /** @var Statamic\Entries\Entry $ungatedOff */
    $ungatedOff = Entry::make();
    $ungatedOff->collection('people');
    $ungatedOff->slug('mccoy');
    $ungatedOff->published(true);
    $ungatedOff->data(['title' => 'Leonard McCoy', 'searchable' => false]);
    $ungatedOff->saveQuietly();

    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->whereIn('collection', ['clinical_expertise', 'people']);
    ExcludeUnsearchableEntries::excludeUnsearchable($query);

    /** @var Collection<int, Statamic\Entries\Entry> $entries */
    $entries = $query->get();
    $slugs = $entries->map(function (Statamic\Entries\Entry $entry): string {
        /** @var string|null $slug */
        $slug = $entry->slug();

        return (string) $slug;
    })->sort()->values()->all();

    // Only the gated collection's toggle is honored; the ungated `false` is not read
    expect($slugs)->toEqualCanonicalizing(['cardiology', 'mccoy', 'oncology']);
});

it('indexes a missing searchable toggle as false, which is why the gate scans stored data (#2703)', function (): void {
    /**
     * Pins the Stache behavior the docblock's reasoning rests on. If an upgrade starts
     * indexing a missing toggle as null, `where('searchable', '!=', false)` may become
     * a correct replacement for the id scan, and this test is the signal to revisit it.
     */
    $slugsFor = function (EntryQueryBuilder $query): array {
        /** @var Collection<int, Statamic\Entries\Entry> $entries */
        $entries = $query->get();

        return $entries->map(function (Statamic\Entries\Entry $entry): string {
            /** @var string|null $slug */
            $slug = $entry->slug();

            return (string) $slug;
        })->sort()->values()->all();
    };

    /** @var EntryQueryBuilder $absentKey */
    $absentKey = Entry::query();
    $absentKey->where('collection', 'clinical_expertise');
    $absentKey->whereNull('a_key_no_blueprint_declares');

    /** @var EntryQueryBuilder $isNull */
    $isNull = Entry::query();
    $isNull->where('collection', 'clinical_expertise');
    $isNull->whereNull('searchable');

    /** @var EntryQueryBuilder $notFalse */
    $notFalse = Entry::query();
    $notFalse->where('collection', 'clinical_expertise');
    $notFalse->where('searchable', '!=', false);

    // Control: whereNull does match an absent key the blueprint does not declare
    expect($slugsFor($absentKey))->toEqualCanonicalizing(['cardiology', 'institute', 'oncology'])
        // The toggle's missing value is indexed as false, so neither form keeps it
        ->and($slugsFor($isNull))->toBe([])
        ->and($slugsFor($notFalse))->toBe(['oncology']);
});
