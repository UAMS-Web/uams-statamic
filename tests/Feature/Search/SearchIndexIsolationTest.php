<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Tests\Support\StacheIsolatedTestCase;
use Tests\Support\ThrowawayRoot;

/**
 * A test run never writes the shared `storage/statamic/search` indexes (UAMS-Web/uams-statamic-archive#2536).
 *
 * Every entry save runs Statamic's `UpdateItemIndexes` subscriber, which reads a
 * Comb index file, merges the entry in and writes it back. Under the driver's
 * default path every paratest worker and every `--mutate` child did that to the
 * same files, and a `--mutate --parallel` run left `cp.json` as invalid JSON.
 * The test bootstrap now points the `local` driver at a per-process directory.
 *
 * The save below goes through the real subscriber, so the test fails if the
 * bootstrap stops redirecting the driver and the save lands in the shared path.
 * It also requires the save to have written an index where the bootstrap put it,
 * so a save that wrote nothing at all cannot pass.
 *
 * @command  php artisan test --compact tests/Feature/Search/SearchIndexIsolationTest.php
 */
uses(StacheIsolatedTestCase::class);

/**
 * Every file under the shared index directory, with its size and modification time.
 *
 * @return array<string, string>
 */
function sharedSearchIndexState(): array
{
    $directory = storage_path('statamic/search');

    if (! File::isDirectory($directory)) {
        return [];
    }

    $state = [];

    foreach (File::allFiles($directory) as $file) {
        $state[$file->getRelativePathname()] = $file->getSize().'@'.$file->getMTime();
    }

    ksort($state);

    return $state;
}

it('points the local search driver at a directory only this process uses', function (): void {
    expect(config('statamic.search.drivers.local.path'))
        ->toBe(storage_path('framework/testing/search/'.ThrowawayRoot::segment()));
});

it('writes an entry save to this process\'s own search indexes, not the shared ones', function (): void {
    $shared = sharedSearchIndexState();

    /** @var Statamic\Entries\Collection $collection */
    $collection = Collection::make('search_isolation_probe');
    $collection->save();

    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('search_isolation_probe');
    $entry->slug('probe');
    $entry->data(['title' => 'Search isolation probe']);
    $entry->save();

    /** @var string $ownDirectory */
    $ownDirectory = config('statamic.search.drivers.local.path');

    expect(File::exists($ownDirectory.'/cp.json'))->toBeTrue()
        ->and(sharedSearchIndexState())->toBe($shared);
});
