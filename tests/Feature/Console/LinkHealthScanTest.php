<?php

declare(strict_types=1);

use App\Services\LinkHealth\HostThrottle;
use App\Services\LinkHealth\ProbeOutcome;
use App\Support\Http\OutboundUrlGuard;
use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Statamic\Entries\Collection;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Fields\Blueprint;
use Tests\Support\StacheIsolatedTestCase;
use Tests\Support\ThrowawayRoot;

/**
 * Coverage for `link-health:scan` — the console entry point (UAMS-Web/uams-statamic-archive#1766).
 *
 * Runs with `--skip-external` unless a case is specifically about probing, so the command's own
 * behavior is tested without the network being a variable in it.
 *
 * @command  php artisan test --compact tests/Feature/Console/LinkHealthScanTest.php
 */
uses(StacheIsolatedTestCase::class, RefreshDatabase::class);

const LHS_COLLECTION = 'lhs_pages';

/**
 * @param  array<string, mixed>  $data
 */
function lhsMakeEntry(string $id, array $data): void
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->collection(LHS_COLLECTION);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->id($id);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->slug($id);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->data($data);
    $entry->saveQuietly();
}

beforeEach(function (): void {
    /** @var Collection $collection */
    $collection = CollectionFacade::make(LHS_COLLECTION);
    /** @var Collection $collection */
    $collection = $collection->title('LHS Pages');
    /** @var Collection $collection */
    $collection = $collection->routes('/lhs/{slug}');
    $collection->save();

    /** @var Blueprint $blueprint */
    $blueprint = Statamic\Facades\Blueprint::make('lhs_page');
    /** @var Blueprint $blueprint */
    $blueprint = $blueprint->setNamespace('collections.'.LHS_COLLECTION);
    /** @var Blueprint $blueprint */
    $blueprint = $blueprint->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
        ['handle' => 'cta_url', 'field' => ['type' => 'text']],
    ]]]]]]);
    $blueprint->save();
});

it('runs, reports a summary, and persists the run', function (): void {
    lhsMakeEntry('lhs-a', ['cta_url' => '/lhs/nowhere']);

    pendingArtisan('link-health:scan', ['--skip-external' => true])
        ->expectsOutputToContain('Occurrences:')
        ->expectsOutputToContain('Newly broken:')
        ->assertExitCode(0);

    expect(DB::table('link_health_runs')->count())->toBe(1);
});

// A scan that finds broken links has done its job. Exiting non-zero would make every scheduled
// run look like a tooling failure and train whoever reads it to ignore the signal.
it('exits zero even when it finds broken links', function (): void {
    lhsMakeEntry('lhs-a', ['cta_url' => '/lhs/definitely-missing']);

    pendingArtisan('link-health:scan', ['--skip-external' => true])->assertExitCode(0);
});

it('reports nothing as newly broken on the first run', function (): void {
    lhsMakeEntry('lhs-a', ['cta_url' => '/lhs/missing']);

    // Nothing to diff against, and an occurrence with no previous record is newly SEEN.
    pendingArtisan('link-health:scan', ['--skip-external' => true])
        ->expectsOutputToContain('Newly broken:  0')
        ->assertExitCode(0);
});

it('reports a link that broke between two runs', function (): void {
    lhsMakeEntry('lhs-a', ['cta_url' => '/lhs/lhs-target']);
    lhsMakeEntry('lhs-target', ['cta_url' => '/lhs/lhs-a']);

    // First run: the target exists, so the link is healthy.
    pendingArtisan('link-health:scan', ['--skip-external' => true])->assertExitCode(0);

    // Remove the target, then scan again.
    /** @var Statamic\Entries\Entry|null $target */
    $target = Entry::find('lhs-target');
    $target?->delete();

    pendingArtisan('link-health:scan', ['--skip-external' => true])
        ->expectsOutputToContain('Newly broken:  1')
        ->assertExitCode(0);
});

it('scopes the WORK by collection, not just the report', function (): void {
    /** @var Collection $other */
    $other = CollectionFacade::make('lhs_other');
    /** @var Collection $other */
    $other = $other->title('LHS Other');
    $other->save();

    lhsMakeEntry('lhs-a', ['cta_url' => '/lhs/x']);

    /** @var Statamic\Entries\Entry $outside */
    $outside = Entry::make();
    /** @var Statamic\Entries\Entry $outside */
    $outside = $outside->collection('lhs_other');
    /** @var Statamic\Entries\Entry $outside */
    $outside = $outside->id('lhs-out');
    /** @var Statamic\Entries\Entry $outside */
    $outside = $outside->slug('out');
    $outside->saveQuietly();

    // The entry count is printed, so the scope is visible as work rather than inferred.
    pendingArtisan('link-health:scan', ['--collection' => LHS_COLLECTION, '--skip-external' => true])
        ->expectsOutputToContain('across 1 entry')
        ->assertExitCode(0);
});

it('issues no outbound request under --skip-external', function (): void {
    OutboundUrlGuard::setResolver(fn (string $host): array => ['93.184.216.34']);
    Http::fake();

    lhsMakeEntry('lhs-a', ['cta_url' => 'https://example.com/external']);

    pendingArtisan('link-health:scan', ['--skip-external' => true])->assertExitCode(0);

    Http::assertNothingSent();

    OutboundUrlGuard::setResolver(null);
});

// --- UAMS-Web/uams-statamic-archive#1768: configured intranet hosts ---------------------------------------------------------

it('reports an unreachable intranet link as unverifiable with its reason, never as broken', function (): void {
    config(['link_health.intranet_hosts' => ['inside.uams.edu']]);
    OutboundUrlGuard::setResolver(fn (string $host): array => []);
    Http::fake(['*' => Http::failedConnection('cURL error 6: Could not resolve host: inside.uams.edu')]);

    lhsMakeEntry('lhs-a', ['cta_url' => 'https://inside.uams.edu/policies']);

    pendingArtisan('link-health:scan')
        ->expectsOutputToContain('Broken:        0')
        ->expectsOutputToContain('Refused:       0')
        ->expectsOutputToContain('Unverifiable:  1')
        ->expectsOutputToContain('Newly broken:  0')
        ->expectsOutputToContain('could not be reached from where the link checker ran')
        ->expectsOutputToContain('https://inside.uams.edu/policies — cURL error 6: Could not resolve host')
        ->assertExitCode(0);

    // The reason is stored with the run, because the printed report is discarded in production.
    $row = DB::table('link_health_occurrences')->first();

    expect($row->outcome ?? null)->toBe('unverifiable')
        ->and($row->reason ?? null)->toBeString()->toStartWith('cURL error 6: Could not resolve host');

    OutboundUrlGuard::setResolver(null);
});

it('checks a reachable intranet link and reports its real failure as broken', function (): void {
    config(['link_health.intranet_hosts' => ['inside.uams.edu']]);
    OutboundUrlGuard::setResolver(fn (string $host): array => ['10.1.2.3']);
    Http::fake(['*' => Http::response('', 404)]);

    lhsMakeEntry('lhs-a', ['cta_url' => 'https://inside.uams.edu/gone']);

    pendingArtisan('link-health:scan')
        ->expectsOutputToContain('Broken:        1')
        ->expectsOutputToContain('Unverifiable:  0')
        ->assertExitCode(0);

    expect(DB::table('link_health_occurrences')->pluck('outcome')->all())->toBe(['broken'])
        ->and(DB::table('link_health_occurrences')->value('reason'))->toBeNull();

    OutboundUrlGuard::setResolver(null);
});

it('counts a guard refusal apart from broken and unverifiable', function (): void {
    config(['link_health.intranet_hosts' => ['inside.uams.edu']]);
    OutboundUrlGuard::setResolver(fn (string $host): array => ['10.0.0.9']);
    Http::fake(['*' => Http::response('', 200)]);

    lhsMakeEntry('lhs-a', ['cta_url' => 'https://payroll.internal/admin']);

    pendingArtisan('link-health:scan')
        ->expectsOutputToContain('Broken:        0')
        ->expectsOutputToContain('Refused:       1')
        ->expectsOutputToContain('Unverifiable:  0')
        ->assertExitCode(0);

    Http::assertNothingSent();

    // A refusal is told apart from a connection failure by its stored reason, not only by the
    // outcome.
    expect(DB::table('link_health_occurrences')->value('reason'))
        ->toBeString()
        ->toStartWith('Refusing host that resolves to a non-public address: payroll.internal');

    OutboundUrlGuard::setResolver(null);
});

// --- #964: the report's exact shape ----------------------------------------------------------------
//
// The cases above assert counts. These pin what an operator reads line by line: the progress
// preamble, the key each newly broken link is listed by, the twenty-line caps, and which outcomes
// count as broken. Each one asserts the printed text through Artisan::output(), which is read once
// because it drains its buffer.

/**
 * Run `link-health:scan` and return everything it printed.
 *
 * @param  array<string, mixed>  $options
 */
function lhsScanOutput(array $options = []): string
{
    Artisan::call('link-health:scan', $options);

    return Artisan::output();
}

/**
 * The lines the report lists under "Newly broken", each an occurrence key.
 *
 * @return list<string>
 */
function lhsListedKeys(string $output): array
{
    preg_match_all('/^  (entry:[^\n]+)$/m', $output, $matches);

    return $matches[1];
}

it('announces the collection step and leaves one blank line between the progress bar and the summary', function (): void {
    lhsMakeEntry('lhs-a', ['cta_url' => '/lhs/nowhere']);

    $output = lhsScanOutput(['--skip-external' => true]);

    expect($output)->toStartWith("Collecting link occurrences...\n")
        ->and($output)->toContain(' 1/1 [')
        ->and($output)->toContain("] 100%\n\nOccurrences:   1\n");
});

it('lists a newly broken link by its full occurrence key', function (): void {
    lhsMakeEntry('lhs-a', ['cta_url' => '/lhs/lhs-target']);
    lhsMakeEntry('lhs-target', ['cta_url' => '/lhs/lhs-a']);

    lhsScanOutput(['--skip-external' => true]);

    /** @var Statamic\Entries\Entry|null $target */
    $target = Entry::find('lhs-target');
    $target?->delete();

    // Item type, collection, id, site and field: two occurrences differing in any one of them are
    // separate histories, so the listed key names all five.
    expect(lhsScanOutput(['--skip-external' => true]))
        ->toContain("Newly broken:  1\n  entry:".LHS_COLLECTION.":lhs-a:default:cta_url\n");
});

it('lists at most the first twenty newly broken links', function (): void {
    lhsMakeEntry('lhs-target', ['cta_url' => '/lhs/lhs-target']);

    foreach (range(1, 21) as $n) {
        lhsMakeEntry(sprintf('lhs-%02d', $n), ['cta_url' => '/lhs/lhs-target']);
    }

    lhsScanOutput(['--skip-external' => true]);

    /** @var Statamic\Entries\Entry|null $target */
    $target = Entry::find('lhs-target');
    $target?->delete();

    $output = lhsScanOutput(['--skip-external' => true]);

    expect($output)->toContain('Newly broken:  21')
        ->and(lhsListedKeys($output))->toHaveCount(20);
});

it('counts a link as broken when its target resolves as missing, unpublished or a missing file', function (string $value): void {
    // An unpublished target: the reference resolves, the entry is not live.
    /** @var Statamic\Entries\Entry $draft */
    $draft = Entry::make();
    /** @var Statamic\Entries\Entry $draft */
    $draft = $draft->collection(LHS_COLLECTION);
    /** @var Statamic\Entries\Entry $draft */
    $draft = $draft->id('lhs-draft');
    /** @var Statamic\Entries\Entry $draft */
    $draft = $draft->slug('lhs-draft');
    /** @var Statamic\Entries\Entry $draft */
    $draft = $draft->published(false);
    $draft->saveQuietly();

    // A missing file: the asset record survives, the file under it does not.
    $root = storage_path('framework/testing/lhs_assets/'.ThrowawayRoot::segment());
    config(['filesystems.disks.lhs_assets' => ['driver' => 'local', 'root' => $root, 'url' => '/lhs-assets', 'visibility' => 'public']]);
    File::ensureDirectoryExists($root);
    File::put($root.'/gone.pdf', '%PDF-1.4 probe');

    /** @var Statamic\Assets\AssetContainer $container */
    $container = AssetContainer::make('lhs_files');
    $container->disk('lhs_assets');
    $container->title('LHS Files');
    $container->save();
    $container->makeAsset('gone.pdf')->save();
    File::delete($root.'/gone.pdf');

    lhsMakeEntry('lhs-a', ['cta_url' => $value]);

    expect(lhsScanOutput(['--skip-external' => true]))->toContain('Broken:        1');
})->with([
    'a missing entry' => 'entry::lhs-no-such-entry',
    'an unpublished entry' => 'entry::lhs-draft',
    'a missing file' => 'asset::lhs_files::gone.pdf',
]);

it('reserves a throttle slot for each external link it probes', function (): void {
    OutboundUrlGuard::setResolver(fn (string $host): array => ['93.184.216.34']);
    Http::fake(['*' => Http::response('', 200)]);

    lhsMakeEntry('lhs-a', ['cta_url' => 'https://example.com/reserved']);

    /** @var RateLimiter $limiter */
    $limiter = app(RateLimiter::class);
    $key = HostThrottle::keyFor('https://example.com/reserved');

    try {
        $output = lhsScanOutput();

        expect($limiter->attempts($key))->toBe(1)
            ->and($output)->toContain('Probed:        1 unique external URL(s)');
    } finally {
        $limiter->clear($key);
        OutboundUrlGuard::setResolver(null);
    }
});

it('reports an external link that broke between two runs', function (): void {
    // The first run stores `ok` as the previous outcome. Reading it back must recognize it as a
    // probe outcome, or the second run has no baseline and the break is reported as new-seen.
    OutboundUrlGuard::setResolver(fn (string $host): array => ['93.184.216.34']);
    $status = 200;
    Http::fake(function () use (&$status) {
        return Http::response('', $status);
    });

    lhsMakeEntry('lhs-a', ['cta_url' => 'https://example.com/flaky']);

    try {
        expect(lhsScanOutput())->toContain('Newly broken:  0');

        $status = 404;

        expect(lhsScanOutput())->toContain('Newly broken:  1');
    } finally {
        OutboundUrlGuard::setResolver(null);
    }
});

/**
 * Treat every given intranet host as unreachable, so each link to one is unverifiable.
 *
 * @param  list<string>  $hosts
 */
function lhsUnreachableIntranet(array $hosts): void
{
    config(['link_health.intranet_hosts' => $hosts]);
    OutboundUrlGuard::setResolver(fn (string $host): array => []);
    Http::fake(['*' => Http::failedConnection('cURL error 6: Could not resolve host')]);
}

/**
 * The URLs the report lists under the unverifiable explanation.
 *
 * @return list<string>
 */
function lhsListedUnverifiable(string $output): array
{
    preg_match_all('/^  (https:\/\/\S+) /m', $output, $matches);

    return $matches[1];
}

it('lists an unverifiable URL once however many links share it, after a blank line', function (): void {
    lhsUnreachableIntranet(['inside.uams.edu']);

    // The same URL from two entries is two occurrences and one listed URL.
    lhsMakeEntry('lhs-dup-1', ['cta_url' => 'https://inside.uams.edu/same']);
    lhsMakeEntry('lhs-dup-2', ['cta_url' => 'https://inside.uams.edu/same']);

    try {
        $output = lhsScanOutput();

        expect($output)->toContain('Unverifiable:  2')
            ->and($output)->toContain("Newly broken:  0\n\n".ProbeOutcome::Unverifiable->explanation()."\n")
            ->and(lhsListedUnverifiable($output))->toBe(['https://inside.uams.edu/same']);
    } finally {
        OutboundUrlGuard::setResolver(null);
    }
});

it('lists at most the first twenty unverifiable URLs', function (): void {
    $hosts = array_map(static fn (int $n): string => sprintf('inside%02d.uams.edu', $n), range(1, 21));
    lhsUnreachableIntranet($hosts);

    foreach ($hosts as $n => $host) {
        lhsMakeEntry(sprintf('lhs-u%02d', $n), ['cta_url' => 'https://'.$host.'/page']);
    }

    try {
        $output = lhsScanOutput();

        expect($output)->toContain('Unverifiable:  21')
            ->and(lhsListedUnverifiable($output))->toHaveCount(20);
    } finally {
        OutboundUrlGuard::setResolver(null);
    }
});

it('treats an empty --collection as no scope at all', function (): void {
    lhsMakeEntry('lhs-a', ['cta_url' => '/lhs/x']);
    lhsMakeEntry('lhs-b', ['cta_url' => '/lhs/y']);

    expect(lhsScanOutput(['--collection' => '', '--skip-external' => true]))
        ->toContain('across 2 entries');
});
