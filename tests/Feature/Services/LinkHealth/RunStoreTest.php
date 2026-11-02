<?php

declare(strict_types=1);

use App\Services\LinkHealth\ProbeOutcome;
use App\Services\LinkHealth\RunStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Coverage for {@see RunStore} — persisting runs so consecutive ones can be diffed (UAMS-Web/uams-statamic-archive#1766).
 *
 * @command  php artisan test --compact tests/Feature/Services/LinkHealth/RunStoreTest.php
 */
uses(TestCase::class, RefreshDatabase::class);

it('stamps a run with its creation timestamps', function (): void {
    $store = new RunStore;

    $runId = $store->record(['entry:a:body.0' => ProbeOutcome::Healthy]);

    // Read column-wise rather than through `first()`, whose `stdClass|null` return makes every
    // property access a larastan level-10 error.
    $run = DB::table('link_health_runs')->where('id', $runId);

    expect($run->value('created_at'))->not->toBeNull()
        ->and($run->value('updated_at'))->not->toBeNull();
});

it('stores an empty value for an occurrence that carries none', function (): void {
    $store = new RunStore;

    // The outcome has no matching entry in the values map, so the default is what lands.
    $runId = $store->record(['entry:a:body.0' => ProbeOutcome::Broken], []);

    $value = DB::table('link_health_occurrences')
        ->where('run_id', $runId)
        ->where('occurrence_key', 'entry:a:body.0')
        ->value('value');

    expect($value)->toBe('');
});

it('reads back the outcomes of the previous run', function (): void {
    $store = new RunStore;

    $first = $store->record(['entry:a:body.0' => ProbeOutcome::Healthy]);
    $second = $store->record(['entry:a:body.0' => ProbeOutcome::Broken]);

    expect($store->previousOutcomes($second))->toBe(['entry:a:body.0' => 'healthy'])
        ->and($first)->toBeLessThan($second);
});

it('returns nothing when there is no earlier run', function (): void {
    $store = new RunStore;
    $only = $store->record(['entry:a:body.0' => ProbeOutcome::Healthy]);

    // Not an error state: the first scan has nothing to diff against, and the diff treats an
    // absent record as newly SEEN rather than newly broken.
    expect($store->previousOutcomes($only))->toBe([]);
});

// This is a correctness requirement rather than a refinement. A `--site` run diffed against a
// full one would show every occurrence outside that site as absent; the next full run would then
// read hundreds of links as newly broken. That number looks like news and is an artifact.
it('never diffs a scoped run against a differently scoped one', function (): void {
    $store = new RunStore;

    $store->record(['entry:a:body.0' => ProbeOutcome::Healthy], [], null, null);
    $scoped = $store->record(['entry:b:body.0' => ProbeOutcome::Broken], [], 'health', null);

    // The full run is not this scoped run's predecessor.
    expect($store->previousOutcomes($scoped, 'health'))->toBe([]);
});

it('separates two runs that differ only by collection scope', function (): void {
    $store = new RunStore;

    // Same site, different collections: neither is the other's predecessor.
    $store->record(['entry:a:body.0' => ProbeOutcome::Healthy], [], 'health', 'pages');
    $posts = $store->record(['entry:b:body.0' => ProbeOutcome::Broken], [], 'health', 'posts');

    expect($store->previousOutcomes($posts, 'health', 'posts'))->toBe([]);

    // And a matching collection scope does find its own predecessor.
    $pages = $store->record(['entry:a:body.0' => ProbeOutcome::Broken], [], 'health', 'pages');

    expect($store->previousOutcomes($pages, 'health', 'pages'))->toBe(['entry:a:body.0' => 'healthy']);
});

it('finds the previous run with a matching scope', function (): void {
    $store = new RunStore;

    $store->record(['entry:b:body.0' => ProbeOutcome::Healthy], [], 'health', null);
    $store->record(['entry:a:body.0' => ProbeOutcome::Healthy], [], null, null);
    $second = $store->record(['entry:b:body.0' => ProbeOutcome::Broken], [], 'health', null);

    // Skips the intervening full run rather than taking the most recent by id.
    expect($store->previousOutcomes($second, 'health'))->toBe(['entry:b:body.0' => 'healthy']);
});

it('stores the value alongside the outcome', function (): void {
    $store = new RunStore;
    $run = $store->record(
        ['entry:a:body.0' => ProbeOutcome::Broken],
        ['entry:a:body.0' => 'https://example.com/gone'],
    );

    expect(DB::table('link_health_occurrences')->where('run_id', $run)->value('value'))
        ->toBe('https://example.com/gone');
});

it('retains a bounded number of runs', function (): void {
    $store = new RunStore;

    for ($i = 0; $i < RunStore::RETAINED_RUNS + 4; $i++) {
        $store->record(['entry:a:body.0' => ProbeOutcome::Healthy]);
    }

    expect($store->runCount())->toBe(RunStore::RETAINED_RUNS);
});

// A bounded table that leaves orphaned children is not bounded. The cascade is what makes the
// retention real rather than nominal.
it('drops a pruned run occurrences with it', function (): void {
    $store = new RunStore;

    $first = $store->record(['entry:a:body.0' => ProbeOutcome::Healthy]);

    for ($i = 0; $i < RunStore::RETAINED_RUNS + 2; $i++) {
        $store->record(['entry:a:body.0' => ProbeOutcome::Healthy]);
    }

    expect(DB::table('link_health_occurrences')->where('run_id', $first)->count())->toBe(0);
});

it('stores the reason a target was not reached, and null where there is none', function (): void {
    // UAMS-Web/uams-statamic-archive#1768. An `unverifiable` outcome does not say whether the guard refused the host or it never
    // answered; the stored reason does, for a report that reads the run later.
    $store = new RunStore;

    $runId = $store->record(
        [
            'entry:a:body.0' => ProbeOutcome::Unverifiable,
            'entry:a:body.1' => ProbeOutcome::Healthy,
        ],
        [],
        null,
        null,
        ['entry:a:body.0' => 'cURL error 6: Could not resolve host: inside.uams.edu'],
    );

    $reasons = DB::table('link_health_occurrences')
        ->where('run_id', $runId)
        ->pluck('reason', 'occurrence_key')
        ->all();

    expect($reasons)->toBe([
        'entry:a:body.0' => 'cURL error 6: Could not resolve host: inside.uams.edu',
        'entry:a:body.1' => null,
    ]);
});
