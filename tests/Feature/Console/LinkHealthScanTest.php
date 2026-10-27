<?php

declare(strict_types=1);

use App\Support\Http\OutboundUrlGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Statamic\Entries\Collection;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Fields\Blueprint;
use Tests\Support\StacheIsolatedTestCase;

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
