<?php

declare(strict_types=1);

use App\Services\TitleDisambiguator;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Service-level coverage for TitleDisambiguator after UAMS-Web/uams-statamic-archive#862.
 *
 * The candidate lookups now scope the *exact-title* query to the Stache `title`
 * index (`where('title', …)`), while the *base-title* lookup deliberately keeps a
 * full collection scan (a `like` prefix under-matches a base ending in `\` —
 * Stache reads a trailing `\%` as an escaped literal). The case-sensitive PHP
 * filters remain the authoritative match in both, so the index scoping must never
 * change a suffix.
 *
 * Fixtures persist real entries into an isolated Stache (StacheIsolatedTestCase)
 * and are written with `saveQuietly()` so the global DisambiguateEntryTitle
 * EntrySaved listener cannot rename them — each test asserts against EXACTLY the
 * titles it set.
 */
uses(StacheIsolatedTestCase::class);

const TITLE_DISAMBIG_COLLECTION = 'title_disambig_test';

/**
 * Persist a published entry with the given slug + title, bypassing the
 * DisambiguateEntryTitle listener so the stored title is exactly $title.
 *
 * @param  mixed  $title  Stored verbatim; pass a non-string to exercise the
 *                        is_string guards, or null to omit the title entirely.
 */
function titleDisambigMakeEntry(string $slug, mixed $title, ?string $site = null): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection(TITLE_DISAMBIG_COLLECTION);
    $entry->slug($slug);
    $entry->published(true);
    $entry->data($title === null ? [] : ['title' => $title]);

    if ($site !== null) {
        $entry->locale($site);
    }

    $entry->saveQuietly();

    return $entry;
}

/**
 * Two distinct configured site handles for cross-site coverage. The app is
 * multi-site (resources/sites.yaml), so this is always satisfiable.
 *
 * @return array{0: string, 1: string}
 */
function titleDisambigTwoSiteHandles(): array
{
    /** @var Collection<string, Statamic\Sites\Site> $sites */
    $sites = Site::all();

    $handles = $sites->map(function (Statamic\Sites\Site $site): string {
        /** @var string $handle */
        $handle = $site->handle();

        return $handle;
    })->values()->all();

    Assert::assertGreaterThanOrEqual(2, \count($handles), 'Cross-site tests require a multi-site config.');

    return [$handles[0], $handles[1]];
}

beforeEach(function (): void {
    $collection = CollectionFacade::make(TITLE_DISAMBIG_COLLECTION);
    $collection->title('Title Disambig Test');
    $collection->save();

    Assert::assertNotNull(
        CollectionFacade::find(TITLE_DISAMBIG_COLLECTION),
        'Test collection should persist into the isolated Stache.'
    );
});

it('returns no suffix when the title is unique (#862)', function (): void {
    $entry = titleDisambigMakeEntry('unique', 'Unique Title');

    expect((new TitleDisambiguator)->suffixForEntry($entry, 'Unique Title'))->toBe('');
});

it('returns " (2)" for the first duplicate (#862)', function (): void {
    titleDisambigMakeEntry('a', 'Shared');
    $b = titleDisambigMakeEntry('b', 'Shared');

    expect((new TitleDisambiguator)->suffixForEntry($b, 'Shared'))->toBe(' (2)');
});

it('returns the next number when "base (n)" already exists (#862)', function (): void {
    titleDisambigMakeEntry('a', 'Item');
    titleDisambigMakeEntry('b', 'Item (2)');
    $c = titleDisambigMakeEntry('c', 'Item');

    expect((new TitleDisambiguator)->suffixForEntry($c, 'Item'))->toBe(' (3)');
});

it('uses the highest existing number across many numbered duplicates (#862)', function (): void {
    // Under-match guard: if the base-title lookup ever stops finding the real
    // "Base (n)" siblings, this reissues a used suffix (" (2)") instead of " (4)".
    titleDisambigMakeEntry('a', 'Base');
    titleDisambigMakeEntry('b', 'Base (2)');
    titleDisambigMakeEntry('c', 'Base (3)');
    $d = titleDisambigMakeEntry('d', 'Base');

    expect((new TitleDisambiguator)->suffixForEntry($d, 'Base'))->toBe(' (4)');
});

it('handles a base title ending in a backslash (#862)', function (): void {
    // Regression guard for the dropped `like` query: 'C:\' + '%' became the
    // pattern 'C:\%', whose trailing `\%` Stache reads as an escaped literal `%`,
    // so it matched neither sibling and under-counted to " (2)". The full scan +
    // case-sensitive preg_quote filter must count 'C:\ (2)' and return " (3)".
    titleDisambigMakeEntry('a', 'C:\\');
    titleDisambigMakeEntry('b', 'C:\\ (2)');
    $c = titleDisambigMakeEntry('c', 'C:\\');

    expect((new TitleDisambiguator)->suffixForEntry($c, 'C:\\'))->toBe(' (3)');
});

it('does not treat a longer prefix-sharing title as the same base (#862)', function (): void {
    // "Caterpillar" shares the "Cat" prefix but is neither "Cat" nor "Cat (n)",
    // so the authoritative filter must exclude it.
    titleDisambigMakeEntry('a', 'Cat');
    titleDisambigMakeEntry('b', 'Caterpillar');
    $c = titleDisambigMakeEntry('c', 'Cat');

    expect((new TitleDisambiguator)->suffixForEntry($c, 'Cat'))->toBe(' (2)');
});

it('treats a regex/SQL metacharacter in the base title literally (#862)', function (): void {
    // "_" is a SQL-LIKE single-char wildcard and a regex quantifier target; the
    // preg_quote()'d filter must count only the literal "A_B", never "AXB".
    titleDisambigMakeEntry('a', 'A_B');
    titleDisambigMakeEntry('b', 'AXB');
    $c = titleDisambigMakeEntry('c', 'A_B');

    expect((new TitleDisambiguator)->suffixForEntry($c, 'A_B'))->toBe(' (2)');
});

it('matches titles case-sensitively despite the case-insensitive Stache index (#862)', function (): void {
    // The Stache `where('title', …)` index is case-INSENSITIVE, so 'Cat' is a
    // candidate for base 'cat'. The authoritative PHP filter is case-SENSITIVE,
    // so a different-cased title is NOT a collision and no suffix is issued.
    titleDisambigMakeEntry('a', 'Cat');
    $b = titleDisambigMakeEntry('b', 'cat');

    expect((new TitleDisambiguator)->suffixForEntry($b, 'cat'))->toBe('');
});

it('ignores entries with a non-string title without error (#862)', function (): void {
    // A non-string title lands in the base-title full scan; the is_string guards
    // must skip it rather than feeding an int to preg_match()/string compare.
    titleDisambigMakeEntry('a', 'Solo');
    titleDisambigMakeEntry('numeric', 42);
    $c = titleDisambigMakeEntry('c', 'Solo');

    expect((new TitleDisambiguator)->suffixForEntry($c, 'Solo'))->toBe(' (2)');
});

it('disambiguates correctly amid many non-colliding entries (#862)', function (): void {
    // The cheap path the exact-title index scoping exists to preserve: most of the
    // collection does not collide, only the real "Popular" duplicate counts.
    foreach (range(1, 12) as $n) {
        titleDisambigMakeEntry('noise-'.$n, 'Noise '.$n);
    }
    titleDisambigMakeEntry('dup-a', 'Popular');
    $b = titleDisambigMakeEntry('dup-b', 'Popular');

    expect((new TitleDisambiguator)->suffixForEntry($b, 'Popular'))->toBe(' (2)');
});

it('returns no suffix for an unsaved entry when the title is unique (#862)', function (): void {
    titleDisambigMakeEntry('a', 'Fresh');

    expect((new TitleDisambiguator)->suffixForCollectionAndSite(TITLE_DISAMBIG_COLLECTION, null, 'Distinct'))
        ->toBe('');
});

it('computes the next number for an unsaved entry from existing duplicates (#862)', function (): void {
    // suffixForCollectionAndSite excludes no current entry (the entry is not saved
    // yet), so both "Draft" and "Draft (2)" count toward the next suffix.
    titleDisambigMakeEntry('a', 'Draft');
    titleDisambigMakeEntry('b', 'Draft (2)');

    expect((new TitleDisambiguator)->suffixForCollectionAndSite(TITLE_DISAMBIG_COLLECTION, null, 'Draft'))
        ->toBe(' (3)');
});

it('does not treat the same title in a different site as a collision (#862)', function (): void {
    // Cross-site guard, exact-title path (suffixForEntry): two sites legitimately
    // share "Clinic", so the entry in site B must not be disambiguated.
    [$siteA, $siteB] = titleDisambigTwoSiteHandles();
    titleDisambigMakeEntry('a', 'Clinic', $siteA);
    $b = titleDisambigMakeEntry('b', 'Clinic', $siteB);

    expect((new TitleDisambiguator)->suffixForEntry($b, 'Clinic'))->toBe('');
});

it('counts only same-site entries when numbering duplicates (#862)', function (): void {
    // Cross-site guard, base-title path (suffixForEntry): a same-site collision
    // opens the gate, but "Ward (2)"/"Ward (3)" in another site must not be
    // counted — the next number comes from site A alone.
    [$siteA, $siteB] = titleDisambigTwoSiteHandles();
    titleDisambigMakeEntry('a', 'Ward', $siteA);
    titleDisambigMakeEntry('b2', 'Ward (2)', $siteB);
    titleDisambigMakeEntry('b3', 'Ward (3)', $siteB);
    $c = titleDisambigMakeEntry('c', 'Ward', $siteA);

    expect((new TitleDisambiguator)->suffixForEntry($c, 'Ward'))->toBe(' (2)');
});

it('scopes the unsaved-entry exact-title gate to the given site (#862)', function (): void {
    // Cross-site guard, exact-title path (suffixForCollectionAndSite): "Suite"
    // exists only in site B, so site A has no collision and gets no suffix.
    [$siteA, $siteB] = titleDisambigTwoSiteHandles();
    titleDisambigMakeEntry('b', 'Suite', $siteB);

    expect((new TitleDisambiguator)->suffixForCollectionAndSite(TITLE_DISAMBIG_COLLECTION, $siteA, 'Suite'))
        ->toBe('');
});

it('scopes the unsaved-entry numbering to the given site (#862)', function (): void {
    // Cross-site guard, base-title path (suffixForCollectionAndSite): site A has a
    // "Hall" (the new entry would be its 2nd), while "Hall (2)" in site B must not
    // bump the number.
    [$siteA, $siteB] = titleDisambigTwoSiteHandles();
    titleDisambigMakeEntry('a', 'Hall', $siteA);
    titleDisambigMakeEntry('b2', 'Hall (2)', $siteB);

    expect((new TitleDisambiguator)->suffixForCollectionAndSite(TITLE_DISAMBIG_COLLECTION, $siteA, 'Hall'))
        ->toBe(' (2)');
});

it('ignores an out-of-range parenthesised number without overflowing (#862)', function (): void {
    // A ~19-digit sibling number saturates (int) to PHP_INT_MAX; honouring it would
    // overflow the next-number `+ 1` to a float and throw a TypeError on the `: int`
    // return. The guard must skip it and issue the next small number instead.
    titleDisambigMakeEntry('a', 'Base');
    titleDisambigMakeEntry('huge', 'Base (9999999999999999999)');
    $c = titleDisambigMakeEntry('c', 'Base');

    expect((new TitleDisambiguator)->suffixForEntry($c, 'Base'))->toBe(' (2)');
});

it('ignores an out-of-range number for an unsaved entry without overflowing (#862)', function (): void {
    titleDisambigMakeEntry('a', 'Zone');
    titleDisambigMakeEntry('huge', 'Zone (9999999999999999999)');

    expect((new TitleDisambiguator)->suffixForCollectionAndSite(TITLE_DISAMBIG_COLLECTION, null, 'Zone'))
        ->toBe(' (2)');
});
