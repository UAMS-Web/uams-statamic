<?php

declare(strict_types=1);

use App\Support\ContentMirrorBandLock;
use App\Support\ContentRoot;
use App\Support\StacheSeedStamp;
use Illuminate\Console\Command;
use Illuminate\Console\View\Components\Mutators\EnsureRelativePaths;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

use function Pest\Laravel\artisan;

// Serial: the happy-path test re-runs the ~21s warm and rebuilds the shared
// `storage/framework/stache-seed` snapshot, so it runs in the single-process
// serial tail rather than contending with the parallel workers.
uses(TestCase::class)->group('serial');

// This process holds the shared band lock from boot, as every test process does, and the
// seed refuses while any process holds it (UAMS-Web/uams-statamic-archive#2762). The serial tail runs with no other test
// process alive, so releasing it here is the documented between-bands case, not a band.
beforeEach(function (): void {
    ContentMirrorBandLock::release();
});

afterEach(function (): void {
    ContentMirrorBandLock::hold();
});

/**
 * Run `test:seed-stache` and return its PendingCommand for fluent assertions.
 *
 * The returned value MUST be chained on directly: a PendingCommand executes in
 * its destructor, so storing it would defer the run past the later filesystem
 * assertions.
 */
function seedStacheRunCommand(): PendingCommand
{
    // Return it unstored so the caller chains and it destructs in-statement.
    // pendingArtisan() resolves artisan()'s PendingCommand|int union.
    return pendingArtisan('test:seed-stache');
}

/**
 * Replace `statamic:stache:warm` with a stub that writes the given files.
 *
 * The real warm costs ~21s, and the tests using this bound the snapshot-and-
 * verify step rather than the warm itself. Registering under the same name
 * replaces the command. Writing the files from *inside* the stub is what the
 * command's own ordering requires: it wipes the cache dir before warming, so
 * anything planted ahead of the run is gone before the copy ever reads it.
 *
 * @param  list<string>  $relativePaths  Paths under the cache dir to create.
 */
function seedStacheStubWarmWriting(array $relativePaths): void
{
    App::make(ConsoleKernel::class)->registerCommand(new class($relativePaths) extends Command
    {
        protected $signature = 'statamic:stache:warm';

        /** @param  list<string>  $relativePaths */
        public function __construct(private readonly array $relativePaths)
        {
            parent::__construct();
        }

        public function handle(Filesystem $files): int
        {
            $cacheDir = Config::string('cache.stores.file.path');

            foreach ($this->relativePaths as $relative) {
                $files->ensureDirectoryExists(dirname($cacheDir.'/'.$relative));
                $files->put($cacheDir.'/'.$relative, 'warmed');
            }

            return self::SUCCESS;
        }
    });
}

/**
 * Run $body with the real Stache snapshot parked aside, then restore it.
 *
 * The stubbed-warm tests below leave a one- or two-file snapshot behind, and
 * `CreatesApplication::stacheSnapshotIsUsable()` accepts any non-empty snapshot
 * dir — so without this, every worker in the next band would seed from that stub
 * and run against a starved Stache. A rename costs nothing; rebuilding a real
 * snapshot costs another ~21s warm. Parked under `storage/framework/testing`,
 * which already ignores everything it contains.
 */
function seedStachePreservingSnapshot(Closure $body): void
{
    $seedDir = storage_path('framework/stache-seed');
    $parked = storage_path('framework/testing/stache-seed-parked');

    File::deleteDirectory($parked);
    $hadSnapshot = File::isDirectory($seedDir);

    if ($hadSnapshot) {
        File::move($seedDir, $parked);
    }

    // The root stamp is parked with the snapshot it describes (UAMS-Web/uams-statamic-archive#1916). Every
    // invocation below clears it up front and rewrites it only on success, so the
    // failure-path tests — an empty warm, a truncated copy — legitimately leave
    // none behind. Without this, running this file deleted the band's stamp, and
    // every process booting afterwards refused a snapshot it could no longer
    // identify and paid a full warm in its place.
    $stampedRoot = File::exists(StacheSeedStamp::path()) ? File::get(StacheSeedStamp::path()) : null;

    try {
        $body();
    } finally {
        File::deleteDirectory($seedDir);

        if ($hadSnapshot) {
            File::move($parked, $seedDir);
        }

        if ($stampedRoot === null) {
            StacheSeedStamp::clear(new Filesystem);
        } else {
            File::put(StacheSeedStamp::path(), $stampedRoot);
        }
    }
}

/**
 * Whether the single-flight lock (UAMS-Web/uams-statamic-archive#1978) can currently be taken.
 *
 * Takes and immediately releases the same exclusive lock the command uses, so a
 * `true` means no build is holding it.
 */
function seedStacheLockIsFree(): bool
{
    $handle = fopen(storage_path('framework/stache-seed.lock'), 'c');

    if ($handle === false) {
        return false;
    }

    $free = flock($handle, LOCK_EX | LOCK_NB);

    if ($free) {
        flock($handle, LOCK_UN);
    }

    fclose($handle);

    return $free;
}

/**
 * Feature tests for `test:seed-stache` (UAMS-Web/uams-statamic-archive#1581).
 *
 * The command warms the Stache once and snapshots it into
 * `storage/framework/stache-seed` so the parallel suite's ~12 workers each copy
 * that snapshot into their own per-worker cache instead of re-running the ~168s
 * contended `statamic:stache:warm`. These bound the happy path (a non-empty
 * snapshot is produced), the env guard (it refuses to run — and writes nothing —
 * outside the test env, so it can never clobber a real cache), and the
 * single-flight lock (UAMS-Web/uams-statamic-archive#1978: a second concurrent build would corrupt the first).
 */
it('warms once and writes a fresh, non-empty Stache snapshot', function (): void {
    $seedDir = storage_path('framework/stache-seed');
    $cacheDir = Config::string('cache.stores.file.path');

    // Plant stale markers in both the cache and the seed dir. The command must
    // clear each before it warms/snapshots; without those clears a prior run's
    // files would leak into the seed, since copyDirectory copies over the target
    // rather than pruning it.
    File::ensureDirectoryExists($seedDir);
    File::ensureDirectoryExists($cacheDir);
    File::put($seedDir.'/stale-seed-marker.txt', 'stale');
    File::put($cacheDir.'/stale-cache-marker.txt', 'stale');

    seedStacheRunCommand()->assertSuccessful();

    expect(File::isDirectory($seedDir))->toBeTrue()
        ->and(File::allFiles($seedDir))->not->toBeEmpty()
        ->and(File::exists($seedDir.'/stale-seed-marker.txt'))->toBeFalse()
        ->and(File::exists($seedDir.'/stale-cache-marker.txt'))->toBeFalse()
        ->and(File::exists($cacheDir.'/stale-cache-marker.txt'))->toBeFalse();

    // The single-flight lock is released once the build finishes, so the next
    // seed is not locked out by the last one. Asserted here rather than in its
    // own test because proving the release costs a second ~21s warm otherwise.
    expect(seedStacheLockIsFree())->toBeTrue();
});

it('refuses to build while another seed build holds the lock, leaving the snapshot intact', function (): void {
    // Two concurrent builds are a destructive conflict, not a mkdir race: both
    // directories are shared, and each run deletes and rebuilds both. Observed
    // before the lock — one run's delete tore the tree the other was mid-copy
    // into, and the loser died in Filesystem::deleteDirectory() with a raw
    // FilesystemIterator trace. The second run must now decline before it
    // touches anything, so the first run's snapshot survives untouched.
    $seedDir = storage_path('framework/stache-seed');
    File::ensureDirectoryExists($seedDir);
    File::put($seedDir.'/in-flight-snapshot.txt', 'first run');

    // Stand in for the concurrent run by holding the same lock. flock() is
    // per-handle even within one process (verified on Windows), so this
    // contends exactly as a second process would.
    $held = fopen(storage_path('framework/stache-seed.lock'), 'c');

    if ($held === false) {
        throw new RuntimeException('Could not open the seed lock file to stand in for a concurrent build.');
    }

    expect(flock($held, LOCK_EX | LOCK_NB))->toBeTrue();

    try {
        seedStacheRunCommand()
            ->expectsOutputToContain('Another test:seed-stache is already building the snapshot')
            ->assertFailed();

        // Wrote nothing: it declined ahead of the wipe, so the concurrent
        // build's snapshot is still whole. A run that deleted first and
        // refused second would pass an exit-code-only assertion.
        expect(File::exists($seedDir.'/in-flight-snapshot.txt'))->toBeTrue();
    } finally {
        flock($held, LOCK_UN);
        fclose($held);
        File::delete($seedDir.'/in-flight-snapshot.txt');
    }
});

it('refuses to build while a test process is reading the content mirror, leaving everything intact', function (): void {
    // A seed alongside a running band deletes and re-copies the mirror its workers are
    // reading, and the torn file surfaced as a YAML parse error in unrelated suites. The
    // seed must refuse before its wipe, so the band's snapshot and mirror survive.
    $seedDir = storage_path('framework/stache-seed');
    File::ensureDirectoryExists($seedDir);
    File::put($seedDir.'/in-flight-snapshot.txt', 'band in progress');

    // Stand in for a live band by taking the shared lock on a second handle, as a
    // worker's boot does. flock() contends per handle, even within one process.
    $band = fopen(ContentMirrorBandLock::path(), 'c');

    if ($band === false) {
        throw new RuntimeException('Could not open the band lock file to stand in for a running band.');
    }

    expect(flock($band, LOCK_SH | LOCK_NB))->toBeTrue();

    try {
        $exitCode = Artisan::call('test:seed-stache');

        // The error box wraps long lines, path included, so compare with whitespace collapsed
        $output = (string) preg_replace('/\s+/', ' ', Artisan::output());

        expect($exitCode)->not->toBe(0)
            // Laravel's console components shorten a path through this mutator, which strips
            // `base_path()/` and so leaves a Windows path, joined with `\`, absolute
            ->and($output)->toContain('A test process is reading the content mirror at '.(new EnsureRelativePaths)(ContentRoot::path('testing')))
            ->and($output)->toContain('Wait for the running tests to finish, then re-run test:seed-stache.');

        expect(File::exists($seedDir.'/in-flight-snapshot.txt'))->toBeTrue();
    } finally {
        flock($band, LOCK_UN);
        fclose($band);
        File::delete($seedDir.'/in-flight-snapshot.txt');
    }
});

it('verifies the copy against the files it copied, not a live recount of the shared cache', function (): void {
    // The cache dir is shared and un-tokenized, so any other app boot in the
    // checkout writes into it mid-build — a bare `php artisan inspire` alongside
    // a seed build adds `entries_by_schema_type/<hash>`. Comparing the two
    // directories' totals after the copy read that as a short copy and deleted a
    // complete snapshot (UAMS-Web/uams-statamic-archive#1978). Verified end-to-end before the fix: a staggered
    // pair left `seed=0` with the cache one file over the baseline.
    $cacheDir = Config::string('cache.stores.file.path');
    $seedDir = storage_path('framework/stache-seed');

    seedStacheStubWarmWriting(['stache/items/planted-entry']);

    // Stand in for the concurrent boot: land a cache entry in the source *after*
    // copyDirectory() has read it. The command must judge the copy by the files
    // it was asked to copy, so this late arrival is not a missing file.
    App::instance('files', new class($cacheDir) extends Filesystem
    {
        public function __construct(private readonly string $cacheDir) {}

        public function copyDirectory($directory, $destination, $options = null): bool
        {
            $copied = parent::copyDirectory($directory, $destination, $options);

            // Only on the outermost call — copyDirectory() recurses through
            // $this, so every nested directory re-enters this override.
            if ($directory === $this->cacheDir) {
                $this->ensureDirectoryExists($this->cacheDir.'/entries_by_schema_type');
                $this->put($this->cacheDir.'/entries_by_schema_type/late-boot-entry', 'x');
            }

            return $copied;
        }
    });

    seedStachePreservingSnapshot(function () use ($seedDir): void {
        seedStacheRunCommand()->assertSuccessful();

        expect(File::exists($seedDir.'/stache/items/planted-entry'))->toBeTrue()
            // The late arrival belongs to no copy, so it is neither snapshotted
            // nor treated as a miss.
            ->and(File::exists($seedDir.'/entries_by_schema_type/late-boot-entry'))->toBeFalse();
    });
});

it('fails loudly and writes no snapshot when the copy is genuinely truncated', function (): void {
    // The UAMS-Web/uams-statamic-archive#1740 guard, bounded on the other side: a real short copy must still
    // abort. Without this, ignoring the late writer above could be "fixed" by
    // dropping the verification altogether and nothing would notice.
    $seedDir = storage_path('framework/stache-seed');

    seedStacheStubWarmWriting(['stache/items/kept-entry', 'stache/items/dropped-entry']);

    // Drop one file *silently* — report the copy as successful without writing
    // it. That is the case copyDirectory()'s own return value cannot catch, so
    // only the per-file check stands between a torn snapshot and a green build.
    App::instance('files', new class extends Filesystem
    {
        public function copy($path, $target): bool
        {
            return str_contains($path, 'dropped-entry') ? true : parent::copy($path, $target);
        }
    });

    seedStachePreservingSnapshot(function () use ($seedDir): void {
        seedStacheRunCommand()
            ->expectsOutputToContain('Stache seed copy was incomplete')
            ->assertFailed();

        // A partial snapshot is worse than none: every worker would inherit it
        // and each would look correctly seeded.
        expect(File::isDirectory($seedDir))->toBeFalse();
    });
});

it('fails when the warm produced nothing rather than snapshotting an empty cache', function (): void {
    // Nothing to copy means nothing missing, so a verification that compares
    // totals passes an empty snapshot. `stacheSnapshotIsUsable()` then reads it
    // as no snapshot at all and every worker falls back to a ~168s warm — the
    // UAMS-Web/uams-statamic-archive#1581 cost back in full, off a command that said it succeeded.
    $seedDir = storage_path('framework/stache-seed');

    seedStacheStubWarmWriting([]);

    seedStachePreservingSnapshot(function () use ($seedDir): void {
        seedStacheRunCommand()
            ->expectsOutputToContain('produced no cache files')
            ->assertFailed();

        expect(File::isDirectory($seedDir))->toBeFalse();
    });
});

it('refuses to run outside the testing environment and writes nothing', function (): void {
    // The guard exists so the command can never warm-and-snapshot against a real
    // environment's cache. Force a non-testing env and assert it aborts untouched.
    App::detectEnvironment(fn () => 'production');
    $seedDir = storage_path('framework/stache-seed');

    // Parked rather than deleted outright, so this test leaves the checkout's
    // snapshot as it found it. Absence is a safe end state — a worker falls back
    // to a warm — but it costs the next band ~168s per worker for no reason.
    seedStachePreservingSnapshot(function () use ($seedDir): void {
        seedStacheRunCommand()->assertFailed();

        expect(File::isDirectory($seedDir))->toBeFalse();
    });
});
