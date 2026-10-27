<?php

declare(strict_types=1);

use App\Jobs\WarmPubMedSummaries;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\PendingCommand;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Tests\Support\StacheIsolatedTestCase;

use function Pest\Laravel\artisan;

uses(StacheIsolatedTestCase::class);

/**
 * UAMS-Web/uams-statamic-archive#865: the `pubmed:warm` command gathers PMIDs from the providers/people
 * collections and dispatches the warming job out of band.
 */
beforeEach(function () {
    foreach (['clinical_providers', 'people'] as $handle) {
        $collection = CollectionFacade::make($handle);
        $collection->title($handle);
        $collection->save();
    }
});

/**
 * @param  array<int, array<string, mixed>>  $sets
 */
function warmCmdMakeEntry(string $collection, string $slug, array $sets): void
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection($collection);
    $entry->slug($slug);
    $entry->data(['research_publication_select' => $sets]);
    $entry->saveQuietly();
}

/**
 * Run `pubmed:warm` and return its PendingCommand for fluent assertions.
 *
 * The returned value MUST be chained on directly: a PendingCommand executes in
 * its destructor, so storing it would defer the run past the later queue
 * assertions.
 */
function warmPubMedRunCommand(): PendingCommand
{
    // Return it unstored so the caller chains and it destructs in-statement.
    // pendingArtisan() resolves artisan()'s PendingCommand|int union.
    return pendingArtisan('pubmed:warm');
}

it('dispatches a warming job with the distinct pmids across collections (#865)', function () {
    Queue::fake();

    warmCmdMakeEntry('clinical_providers', 'a', [['pmid' => '111'], ['pmid' => '222']]);
    warmCmdMakeEntry('people', 'b', [['pmid' => '222'], ['pmid' => '333']]);

    warmPubMedRunCommand()
        ->expectsOutputToContain('Dispatched warming for 3 PMID(s).')
        ->assertSuccessful();

    Queue::assertPushed(WarmPubMedSummaries::class, function (WarmPubMedSummaries $job): bool {
        // Dedup across collections must leave a proper list (no gapped keys from
        // array_unique), before the order-insensitive value check.
        /** @var array<int, string> $pmids */
        $pmids = $job->pmids;
        expect(array_is_list($pmids))->toBeTrue();

        sort($job->pmids);

        return $job->pmids === ['111', '222', '333'];
    });
});

it('normalizes int pmids to strings and drops empty/non-scalar/missing values (#865 review)', function () {
    Queue::fake();

    warmCmdMakeEntry('clinical_providers', 'mixed', [
        ['pmid' => 12345],     // int from YAML -> must become the string '12345'
        ['pmid' => ''],        // empty string -> dropped
        ['pmid' => ['x']],     // non-scalar -> dropped
        ['pmid' => false],     // bool -> (string) false is '' -> dropped
        ['notpmid' => 'y'],    // missing pmid key -> dropped
        ['pmid' => '999'],     // valid
    ]);

    warmPubMedRunCommand()
        ->expectsOutputToContain('Dispatched warming for 2 PMID(s).')
        ->assertSuccessful();

    Queue::assertPushed(WarmPubMedSummaries::class, function (WarmPubMedSummaries $job): bool {
        return $job->pmids === ['12345', '999'];
    });
});

it('dispatches nothing when no pmids are present (#865)', function () {
    Queue::fake();

    warmCmdMakeEntry('people', 'no-pubs', []);

    warmPubMedRunCommand()
        ->expectsOutputToContain('No PMIDs found to warm.')
        ->doesntExpectOutputToContain('Dispatched warming for')
        ->assertSuccessful();

    Queue::assertNotPushed(WarmPubMedSummaries::class);
});

it('skips a publication field that is not a list, and a set that is not a map', function () {
    Queue::fake();

    foreach ([
        ['clinical_providers', 'scalar-field', 'not a list'],
        ['people', 'mixed-sets', ['not a set', ['pmid' => '444']]],
    ] as [$collection, $slug, $value]) {
        $entry = mustBe(Statamic\Entries\Entry::class, Entry::make(), 'the entry');
        $entry->collection($collection);
        $entry->slug($slug);
        $entry->data(['research_publication_select' => $value]);
        $entry->saveQuietly();
    }

    warmPubMedRunCommand()
        ->expectsOutputToContain('Dispatched warming for 1 PMID(s).')
        ->assertSuccessful();

    Queue::assertPushed(WarmPubMedSummaries::class, fn (WarmPubMedSummaries $job): bool => $job->pmids === ['444']);
});

it('dispatches one job per hundred PMIDs', function () {
    Queue::fake();

    $sets = array_map(fn (int $n): array => ['pmid' => (string) (1000 + $n)], range(1, 201));
    warmCmdMakeEntry('clinical_providers', 'many', $sets);

    warmPubMedRunCommand()
        ->expectsOutputToContain('Dispatched warming for 201 PMID(s).')
        ->assertSuccessful();

    $sizes = [];
    Queue::assertPushed(WarmPubMedSummaries::class, function (WarmPubMedSummaries $job) use (&$sizes): bool {
        $sizes[] = count($job->pmids);

        return true;
    });

    expect($sizes)->toBe([100, 100, 1]);
});

it('goes on to the next entry after one whose publication field is not a list', function () {
    Queue::fake();

    // The unreadable entry sorts first, so stopping at it would skip the rest
    foreach (['a-unreadable' => 'not a list', 'b-readable' => [['pmid' => '555']], 'c-readable' => [['pmid' => '666']]] as $id => $value) {
        $entry = mustBe(Statamic\Entries\Entry::class, Entry::make(), 'the entry');
        $entry->collection('clinical_providers');
        $entry->id($id);
        $entry->slug($id);
        $entry->data(['research_publication_select' => $value]);
        $entry->saveQuietly();
    }

    warmPubMedRunCommand()
        ->expectsOutputToContain('Dispatched warming for 2 PMID(s).')
        ->assertSuccessful();

    Queue::assertPushed(WarmPubMedSummaries::class, function (WarmPubMedSummaries $job): bool {
        $pmids = $job->pmids;
        sort($pmids);

        return $pmids === ['555', '666'];
    });
});
