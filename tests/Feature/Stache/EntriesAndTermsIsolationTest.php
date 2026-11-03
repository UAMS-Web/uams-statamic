<?php

declare(strict_types=1);

use App\Support\ContentRoot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Entry;
use Statamic\Facades\Stache;
use Statamic\Facades\Taxonomy as TaxonomyFacade;
use Statamic\Facades\Term;
use Statamic\Stache\Stores\Store;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Taxonomy;
use Statamic\Taxonomies\Term as TaxonomyTerm;
use Tests\Support\ContentSubmodule;
use Tests\Support\EntriesAndTermsIsolatedTestCase;
use Tests\Support\ThrowawayRoot;

uses(EntriesAndTermsIsolatedTestCase::class);

/**
 * The contract {@see EntriesAndTermsIsolatedTestCase} holds (UAMS-Web/uams-statamic#556):
 * an entry or a term saved through it lands in this process's copy of the content
 * mirror, the shared mirror every worker reads is left byte-for-byte as it was, and
 * the real content still resolves.
 *
 * The second case is the one the base exists for. Re-saving an entry or a term that
 * was cached before the stores moved deletes the committed file from the shared
 * mirror unless the base also forgot the cached item, because
 * `ExistsAsFile::writeFile()` deletes an item's `initialPath` when it differs from
 * the path recomputed from the store directory.
 *
 * Saves are quiet, so no listener writes outside the two stores the base moves.
 *
 * The third case checks what the first two left behind, so it tests something only
 * when it runs after them in the same process, which is the file's declared order.
 * Run alone with `--filter`, it passes having nothing to find.
 *
 * @command  php artisan test --compact tests/Feature/Stache/EntriesAndTermsIsolationTest.php
 */

/**
 * The collection and taxonomy the cases save into: real, unstructured, small.
 */
const ISOLATION_PROBE_COLLECTION = 'calculators';
const ISOLATION_PROBE_TAXONOMY = 'audiences';
const ISOLATION_PROBE_SLUG = 'entries-terms-isolation-probe-556';

/**
 * Each file under the shared mirror's `collections/` and `taxonomies/`, mapped to
 * its size, modification time and content hash.
 *
 * @return array<string, string>
 */
function sharedEntriesAndTermsFingerprint(): array
{
    $root = ContentRoot::path(Config::string('app.env'));
    $fingerprint = [];

    // Hash every file, so a rewrite with the same size and time still differs
    foreach (['collections', 'taxonomies'] as $directory) {
        foreach (File::allFiles($root.'/'.$directory, true) as $file) {
            $relative = $directory.'/'.str_replace('\\', '/', $file->getRelativePathname());
            $fingerprint[$relative] = $file->getSize().':'.$file->getMTime().':'.sha1_file($file->getPathname());
        }
    }

    ksort($fingerprint);

    return $fingerprint;
}

/**
 * The first committed entry in the probe collection.
 */
function isolationProbeEntry(): StatamicEntry
{
    $entry = Entry::whereCollection(ISOLATION_PROBE_COLLECTION)->first();

    if ($entry === null) {
        ContentSubmodule::reportMissing('a committed '.ISOLATION_PROBE_COLLECTION.' entry');
    }

    return mustBe(StatamicEntry::class, $entry, 'the probe entry');
}

/**
 * The first committed term in the probe taxonomy.
 */
function isolationProbeTerm(): LocalizedTerm
{
    $term = Term::whereTaxonomy(ISOLATION_PROBE_TAXONOMY)->first();

    if ($term === null) {
        ContentSubmodule::reportMissing('a committed '.ISOLATION_PROBE_TAXONOMY.' term');
    }

    return mustBe(LocalizedTerm::class, $term, 'the probe term');
}

/**
 * An item's file path with forward slashes.
 *
 * @param  StatamicEntry|LocalizedTerm|TaxonomyTerm  $item  A saved entry or term.
 */
function isolationProbePath(StatamicEntry|LocalizedTerm|TaxonomyTerm $item): string
{
    return str_replace('\\', '/', mustBeString($item->path(), 'the probe item path'));
}

/**
 * A Stache store's root directory with forward slashes.
 *
 * @param  string  $key  The store key.
 */
function isolationStoreDirectory(string $key): string
{
    return str_replace('\\', '/', mustBeString(mustBe(Store::class, Stache::store($key), "the {$key} store")->directory(), "the {$key} store directory"));
}

it('saves a new entry and term into the copy and leaves the shared mirror unchanged', function (): void {
    $site = mustBeString(isolationProbeEntry()->locale(), 'the probe entry locale');
    $before = sharedEntriesAndTermsFingerprint();

    $entry = mustBe(StatamicEntry::class, Entry::make(), 'a new entry');
    $entry->collection(ISOLATION_PROBE_COLLECTION);
    $entry->locale($site);
    $entry->slug(ISOLATION_PROBE_SLUG);
    $entry->data(['title' => 'Entries and terms isolation probe']);
    $entry->saveQuietly();

    $taxonomy = mustBe(Taxonomy::class, TaxonomyFacade::findByHandle(ISOLATION_PROBE_TAXONOMY), 'the probe taxonomy');
    $term = mustBe(TaxonomyTerm::class, Term::make(ISOLATION_PROBE_SLUG), 'a new term');
    $term->taxonomy(ISOLATION_PROBE_TAXONOMY);
    $term->dataForLocale(mustBeString(mustBe(Collection::class, $taxonomy->sites(), 'the probe taxonomy sites')->first(), 'the probe taxonomy site'), ['title' => 'Entries and terms isolation probe']);
    $term->saveQuietly();

    /** @var EntriesAndTermsIsolatedTestCase $this */
    $copy = str_replace('\\', '/', $this->isolatedContentRoot);
    $found = Entry::find(mustBeString($entry->id(), 'the new entry id'));

    expect(isolationProbePath($entry))->toStartWith($copy.'/collections/')
        ->and(isolationProbePath($entry))->toBeFile()
        ->and(isolationProbePath($term))->toStartWith($copy.'/taxonomies/')
        ->and(isolationProbePath($term))->toBeFile()
        ->and($found === null ? null : mustBe(StatamicEntry::class, $found, 'the found entry')->slug())->toBe(ISOLATION_PROBE_SLUG)
        ->and(sharedEntriesAndTermsFingerprint())->toBe($before);
});

it('re-saves a committed entry and term without moving them out of the shared mirror', function (): void {
    $entry = isolationProbeEntry();
    $term = isolationProbeTerm();
    $before = sharedEntriesAndTermsFingerprint();

    $entry->set('title', mustBeString($entry->get('title'), 'the probe entry title').' (re-saved)');
    $entry->saveQuietly();
    $term->set('title', mustBeString($term->get('title'), 'the probe term title').' (re-saved)');
    $term->saveQuietly();

    /** @var EntriesAndTermsIsolatedTestCase $this */
    $copy = str_replace('\\', '/', $this->isolatedContentRoot);

    expect(isolationProbePath($entry))->toStartWith($copy.'/collections/')
        ->and(isolationProbePath($term))->toStartWith($copy.'/taxonomies/')
        ->and(sharedEntriesAndTermsFingerprint())->toBe($before);
});

it('leaves no trace of an earlier test in the copy or the Stache', function (): void {
    $entries = Entry::whereCollection(ISOLATION_PROBE_COLLECTION);

    /** @var EntriesAndTermsIsolatedTestCase $this */
    $copy = str_replace('\\', '/', $this->isolatedContentRoot);
    $leftover = array_filter(
        File::allFiles($copy, true),
        fn (SplFileInfo $file): bool => str_contains($file->getFilename(), ISOLATION_PROBE_SLUG),
    );
    $slugs = $entries->map(fn (mixed $entry): mixed => $entry instanceof StatamicEntry ? $entry->slug() : null)->all();
    $reSavedEntries = $entries->filter(fn (mixed $entry): bool => $entry instanceof StatamicEntry && str_ends_with(mustBeString($entry->get('title') ?? '', 'an entry title'), '(re-saved)'))->all();
    $terms = Term::whereTaxonomy(ISOLATION_PROBE_TAXONOMY);
    $reSavedTerms = $terms->filter(fn (mixed $term): bool => $term instanceof LocalizedTerm && str_ends_with(mustBeString($term->get('title') ?? '', 'a term title'), '(re-saved)'))->all();

    expect($entries->all())->not->toBe([])
        ->and($terms->all())->not->toBe([])
        ->and($leftover)->toBe([])
        ->and($slugs)->not->toContain(ISOLATION_PROBE_SLUG)
        ->and(Term::find(ISOLATION_PROBE_TAXONOMY.'::'.ISOLATION_PROBE_SLUG))->toBeNull()
        ->and($reSavedEntries)->toBe([])
        ->and($reSavedTerms)->toBe([]);
});

it('roots the copy per process, outside the shared mirror', function (): void {
    /** @var EntriesAndTermsIsolatedTestCase $this */
    $copy = str_replace('\\', '/', $this->isolatedContentRoot);
    $shared = str_replace('\\', '/', ContentRoot::path(Config::string('app.env')));

    expect($copy)->toEndWith('/'.ThrowawayRoot::segment())
        ->and(str_starts_with($copy.'/', $shared.'/'))->toBeFalse()
        ->and(isolationStoreDirectory('entries'))->toStartWith($copy.'/collections')
        ->and(isolationStoreDirectory('terms'))->toStartWith($copy.'/taxonomies');
});
