<?php

declare(strict_types=1);

use App\Models\Asset as AssetMetadataModel;
use App\Support\ContentRoot;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Entry;
use Statamic\Facades\Stache;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(TestCase::class)->group('serial');

/**
 * The test env roots every content-backed Stache store at a disposable mirror of
 * `content/`, so no test can write into the live submodule (UAMS-Web/uams-statamic-archive#1916).
 *
 * The regression these guard is not "a stray file appears" — it is the *deletion*
 * documented in `config/statamic/stache.php`. Re-rooting the store directories
 * while the Stache cache still resolves items read from `content/` makes
 * `ExistsAsFile::writeFile()` remove the committed file, and it does so on a run
 * whose tests all pass: saving an entry in a **structured** collection implicitly
 * saves its tree, so the damage lands in a store the test never names.
 */
it('roots every Stache store outside the content submodule', function (): void {
    // ---
    // Enumerated rather than listed. `config/statamic/stache.php` names the ten
    // stores that were rooted in `content/`; a hardcoded list here would assert
    // that those ten moved and say nothing about an eleventh — a store Statamic
    // adds in an upgrade, or one an addon registers. Asking every store the
    // question makes the guarantee "no store writes into tracked content" rather
    // than "these ten do not".
    // ---
    $offenders = [];

    foreach (Stache::stores() as $key => $store) {
        $directory = (string) $store->directory();

        if (contentMirrorIsInsideSubmodule($directory)) {
            $offenders[(string) $key] = $directory;
        }
    }

    expect($offenders)->toBe([]);
});

it('roots the asset metadata YAML directory outside the content submodule', function (): void {
    // ---
    // The one surface a store re-root cannot reach. `App\Models\Asset` dual-writes
    // every save to `{id}.yml` off Eloquent's `saved` event, never through the
    // Stache — and under test the `:memory:` autoincrement restarts at `1` in
    // every process, so the first asset any test creates lands on a real image's
    // committed alt text, caption and photographer credit (UAMS-Web/uams-statamic-archive#1919). Missing it
    // was how a mid-run kill left `M models/assets/1.yml` behind.
    // ---
    expect(contentMirrorIsInsideSubmodule(Config::string('asset_metadata.yaml_directory')))->toBeFalse();
});

it('recognizes a path inside the submodule, so the guards above can fail', function (): void {
    // The negative control for both. Every assertion above is an absence, and an
    // absence passes just as well when the check is broken — a `str_starts_with`
    // against a separator-mismatched prefix would report a clean bill of health
    // on a directory rooted squarely in tracked content.
    expect(contentMirrorIsInsideSubmodule(base_path('content/collections')))->toBeTrue()
        ->and(contentMirrorIsInsideSubmodule(base_path('content')))->toBeTrue();
});

it('still reads real content through the mirror', function (): void {
    // The mirror exists so the tests that read real content keep working without
    // fixtures. An empty throwaway root would pass the guard above and break
    // every one of them, so assert the content is actually there.
    //
    // Counted on disk, not through `Entry::query()`. What this ticket guarantees
    // is that the mirror is a faithful copy of `content/`; whether *this
    // process's* Stache index is warm at this moment is a separate invariant
    // owned by whatever ran before it.
    //
    // That distinction is not academic here. In the serial arm of a full band
    // this position resolves ONE page against a mirror holding all 82 — and a
    // control run on `origin/main`, with none of this ticket's code present,
    // resolves one against the same 82 in `content/` itself. So the reduction
    // predates the mirror and is tracked in UAMS-Web/uams-statamic-archive#1994; asserting through the Stache
    // would make this guard fail for a reason it does not own.
    $mirror = ContentRoot::path(Config::string('app.env'));

    $pageFiles = contentMirrorFileCount($mirror.'/collections/pages');
    $peopleFiles = contentMirrorFileCount($mirror.'/collections/people');

    // ---
    // Route a zero through the UAMS-Web/uams-statamic-archive#1738 discriminator rather than asserting
    // outright. A bare checkout with no `content/` submodule is a supported
    // state and skips; with the submodule present, an empty mirror is a defect,
    // and one that would otherwise surface as a `toBeGreaterThan` failure naming
    // a number instead of a cause.
    // ---
    if ($pageFiles === 0) {
        ContentSubmodule::reportMissing('the committed entries this test counts through the mirror');
    }

    expect($pageFiles)->toBeGreaterThan(50)
        ->and($peopleFiles)->toBeGreaterThan(20);

    // ---
    // The Stache's own count is *reported* rather than asserted, and the reason
    // is now precise rather than provisional.
    //
    // UAMS-Web/uams-statamic-archive#1994 removed one trigger (a Stache-isolated test's own `Cache::flush()`
    // reaching the worker's shared store). It did not remove this one:
    // `SeedTestStacheCommandTest` sorts immediately before this file and runs the
    // real `test:seed-stache`, whose wipe of `storage/framework/testing/cache/data` is
    // deliberately un-tokenized so it heals a hard-killed band (UAMS-Web/uams-statamic-archive#1876). Emptying
    // that cache is legitimate; what follows it is not. The FIRST Stache rebuild
    // after the cache is emptied returns ONE entry per collection and caches it as
    // authoritative — measured 1 against a directory of 82, with a second rebuild
    // returning all 82. That defect is UAMS-Web/uams-statamic-archive#2037, and until it closes this position
    // legitimately sees a starved index through no fault of the mirror.
    //
    // So this line goes back to an assertion when **UAMS-Web/uams-statamic-archive#2037** closes, not UAMS-Web/uams-statamic-archive#1994.
    // Reported at all because the two failures are otherwise indistinguishable
    // from a bare `toBeGreaterThan(50)`: "the mirror is empty" is this ticket's
    // problem, "the first rebuild after a flush is starved" is UAMS-Web/uams-statamic-archive#2037's.
    // ---
    $indexed = contentMirrorIndexedEntryCount('pages');

    if ($indexed < $pageFiles) {
        fwrite(STDERR, sprintf(
            "\n[#2037] The mirror at %s holds %d page files; the Stache resolved %d.\n",
            $mirror,
            $pageFiles,
            $indexed
        ));
    }
});

it('leaves the content submodule untouched when a structured collection is written', function (): void {
    // `pages` is structured, so this save writes its collection tree too — the
    // implicit second write that deleted content/trees/collections/default/pages.yaml
    // (166 lines) when the directories were re-rooted but the cache was not.
    $before = contentMirrorSubmoduleStatus();

    /** @var StatamicEntry $entry */
    $entry = Entry::make();
    $entry->collection('pages');
    $entry->slug('zz-content-mirror-probe');
    $entry->data(['title' => 'Probe']);
    $entry->save();
    $entry->delete();

    expect(contentMirrorSubmoduleStatus())->toBe($before);
});

it('leaves the content submodule untouched when the asset metadata dual-write runs', function (): void {
    // ---
    // The path guard above says where the write is *aimed*; this fires it. The
    // suppression `Tests\TestCase::setUp()` arms is lifted on purpose — with it
    // armed, this test would pass against a directory still rooted in tracked
    // content and prove nothing about the re-root.
    // ---
    $before = contentMirrorSubmoduleStatus();
    $directory = Config::string('asset_metadata.yaml_directory');

    $probe = new AssetMetadataModel;
    $probe->setAttribute('container', 'content_mirror_probe');
    $probe->setAttribute('folder', 'probe');
    $probe->setAttribute('basename', 'mirror.jpg');
    $probe->setAttribute('filename', 'mirror');
    $probe->setAttribute('extension', 'jpg');
    $probe->setAttribute('path', 'probe/mirror.jpg');
    $probe->setAttribute('meta', ['data' => ['alt' => 'probe']]);

    $written = null;

    try {
        AssetMetadataModel::$skipYamlSync = false;
        $probe->save();

        // Narrowed by control flow rather than annotation: `getKey()` is typed
        // `mixed`, and a probe that reached here without an integer key means the
        // save silently did not happen — which would leave every assertion below
        // pointed at a path built from an empty id.
        $key = $probe->getKey();

        if (! \is_int($key)) {
            throw new RuntimeException('the probe asset was saved without an integer primary key');
        }

        $written = $directory.'/'.$key.'.yml';

        expect(File::exists($written))->toBeTrue()
            ->and(contentMirrorSubmoduleStatus())->toBe($before);
    } finally {
        AssetMetadataModel::$skipYamlSync = true;

        // The `:memory:` autoincrement starts at 1, so the probe lands on the id
        // a committed file already uses — in the mirror, which is the point.
        // Put the mirror's copy back rather than leaving a hole that makes the
        // next `test:seed-stache` re-copy 3,233 files.
        if (\is_string($written) && File::exists($written)) {
            $committed = base_path('content/models/assets/'.basename($written));

            if (File::exists($committed)) {
                File::copy($committed, $written);
            } else {
                File::delete($written);
            }
        }
    }
});

/**
 * How many files a mirror subdirectory holds, hidden files included.
 *
 * @return int Zero when the directory is absent, which the caller routes through
 *             the UAMS-Web/uams-statamic-archive#1738 discriminator rather than asserting on
 */
function contentMirrorFileCount(string $directory): int
{
    return File::isDirectory($directory) ? \count(File::allFiles($directory, true)) : 0;
}

/**
 * How many entries of a collection the Stache resolves right now.
 *
 * `Entry::query()` is typed as the `QueryBuilder` *contract*, which declares no
 * `where()`, so the concrete builder is named for the analyser rather than
 * chained through.
 */
function contentMirrorIndexedEntryCount(string $collection): int
{
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', $collection);

    // `count()` comes off the builder as `mixed`; narrowed by control flow so a
    // future signature change surfaces here rather than as a silent cast to 0.
    $count = $query->count();

    if (! \is_int($count)) {
        throw new RuntimeException('the entry query returned a non-integer count');
    }

    return $count;
}

/**
 * Whether a configured path resolves inside the live `content/` submodule.
 *
 * Separators are normalized on both sides because Windows mixes them freely —
 * `base_path()` yields backslashes while a config value built by string
 * concatenation keeps forward ones, and a naive prefix test would call every
 * path safe. The submodule root itself counts as inside it.
 */
function contentMirrorIsInsideSubmodule(string $directory): bool
{
    $submodule = rtrim(str_replace('\\', '/', base_path('content')), '/');
    $candidate = rtrim(str_replace('\\', '/', $directory), '/');

    return $candidate === $submodule || str_starts_with($candidate, $submodule.'/');
}

/**
 * `git status --porcelain` for the content submodule, as a comparable string.
 *
 * Compared against a before-state rather than asserted empty, so the test reports
 * *its own* damage on a checkout that was already dirty for unrelated reasons
 * instead of failing for somebody else's stray file.
 */
function contentMirrorSubmoduleStatus(): string
{
    $output = [];
    exec('git -C '.escapeshellarg(base_path('content')).' status --porcelain 2>&1', $output);
    sort($output);

    return implode("\n", $output);
}
