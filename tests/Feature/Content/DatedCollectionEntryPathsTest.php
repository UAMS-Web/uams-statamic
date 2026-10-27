<?php

declare(strict_types=1);

use Statamic\Entries\Collection as EntryCollection;
use Statamic\Entries\Entry as EntryItem;
use Statamic\Entries\GetDateFromPath;
use Statamic\Entries\RemoveSuffixFromPath;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

/**
 * Every stored entry of a dated collection carries the date prefix a save writes (UAMS-Web/uams-statamic-archive#2552).
 *
 * A dated collection's entry is written to `<date>.<slug>.md`. A file with no date
 * prefix still loads, with `date()` falling back to the file's modification time, and
 * the first save that sets an explicit date writes the prefixed name and deletes the old
 * file. `people` did exactly that: `App\Stache\CollectionEntriesStore` copies `date()`
 * onto every re-wrapped person, so 31 files were renamed in one
 * `role-title:link-scope-units --write` run while `content/collections/people.yaml` still
 * said `date: true` and its files carried no prefix.
 *
 * Note what this does NOT compare: `Entry::path()` returns the path the entry was read
 * from whenever one is set, so `path() !== initialPath()` is never true for a stored
 * entry and cannot detect this. The prefix is what Statamic reads the date from
 * (`GetDateFromPath`), so it is the observable half of the contract.
 *
 * Reads the real content, so it catches the next collection whose files and config
 * disagree, not only `people`. `pages` was in that state too until UAMS-Web/uams-statamic-archive#2564 made it undated as
 * well.
 *
 * @command  php artisan test --compact tests/Feature/Content/DatedCollectionEntryPathsTest.php
 */
uses(TestCase::class);

/**
 * The entries of one collection whose stored file name carries no date prefix.
 *
 * @param  string  $handle  The collection handle.
 * @return list<string> The file names without a prefix.
 */
function undatedEntryFiles(string $handle): array
{
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', $handle);

    /** @var Illuminate\Support\Collection<int, EntryItem> $entries */
    $entries = $query->get();

    /** @var list<string> $undated */
    $undated = $entries
        ->map(fn (EntryItem $entry): mixed => $entry->initialPath())
        ->filter(fn (mixed $path): bool => \is_string($path) && (new GetDateFromPath)($path) === null)
        ->map(fn (mixed $path): string => basename((string) $path))
        ->values()
        ->all();

    return $undated;
}

/**
 * A collection's handle as a string.
 *
 * @param  EntryCollection  $collection  The collection.
 */
function collectionHandle(EntryCollection $collection): string
{
    $handle = $collection->handle();

    return \is_string($handle) ? $handle : '';
}

/**
 * The path an entry was read from, empty when it has none.
 *
 * @param  EntryItem  $entry  The entry.
 */
function storedPath(EntryItem $entry): string
{
    $path = $entry->initialPath();

    return \is_string($path) ? $path : '';
}

/**
 * The path a save would write an entry to.
 *
 * @param  EntryItem  $entry  The entry.
 */
function builtPath(EntryItem $entry): string
{
    $path = $entry->buildPath();

    return \is_string($path) ? $path : '';
}

it('stores every entry of a dated collection under a date-prefixed file name', function (): void {
    /** @var Illuminate\Support\Collection<int, EntryCollection> $all */
    $all = Collection::all();

    $dated = $all->filter(fn (EntryCollection $collection): bool => (bool) $collection->dated());

    if ($dated->isEmpty()) {
        ContentSubmodule::reportMissing('a dated collection');
    }

    $misplaced = $dated
        ->mapWithKeys(fn (EntryCollection $collection): array => [collectionHandle($collection) => undatedEntryFiles(collectionHandle($collection))])
        ->filter(fn (array $paths): bool => $paths !== [])
        ->map(fn (array $paths): array => array_slice($paths, 0, 5))
        ->all();

    expect($misplaced)->toBe([]);
});

it('keeps :dataset undated, so saving an entry leaves its file where it is', function (string $handle): void {
    $collection = Collection::findByHandle($handle);

    if (! $collection instanceof EntryCollection) {
        ContentSubmodule::reportMissing("{$handle} collection");
    }

    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', $handle);

    /** @var Illuminate\Support\Collection<int, EntryItem> $entries */
    $entries = $query->get();

    // `buildPath()` is the path a save writes; `path()` would echo the stored one back. A
    // stored duplicate-slug suffix (`fast-facts.1.md`) is compared without it, because
    // the entries store keeps a suffix whenever the rest of the path is unchanged.
    $moved = $entries
        ->filter(fn (EntryItem $entry): bool => storedPath($entry) !== '' && (new RemoveSuffixFromPath)(storedPath($entry)) !== builtPath($entry))
        ->map(fn (EntryItem $entry): string => basename(storedPath($entry)).' -> '.basename(builtPath($entry)))
        ->values()
        ->all();

    /** @var EntryCollection $collection */
    expect($collection->dated())->toBeFalse()
        ->and($entries)->not->toBeEmpty()
        ->and($moved)->toBe([]);
})->with([
    // Undated since UAMS-Web/uams-statamic-archive#2552, scheduled by `publish_at` / `unpublish_at`
    'people' => ['people'],
    // Undated since UAMS-Web/uams-statamic-archive#2564, the same way
    'pages' => ['pages'],
]);
