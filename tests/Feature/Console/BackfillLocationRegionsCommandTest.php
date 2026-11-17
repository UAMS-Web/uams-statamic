<?php

declare(strict_types=1);

use App\Jobs\BackfillLocationRegion;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\PendingCommand;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Query\Builder;
use Tests\TestCase;

use function Pest\Laravel\artisan;

/**
 * Coverage for the one-shot `content:backfill-location-regions` command (UAMS-Web/uams-statamic-archive#1112):
 * it queues a backfill for each location missing a region, skips those that
 * already have one (or lack a coordinate), and queues nothing on a dry run. The
 * Entry query is faked so this stays offline.
 *
 * @command  php artisan test --compact tests/Feature/Console/BackfillLocationRegionsCommandTest.php
 */
uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Build a mock clinical_locations entry the command's gate can read.
 *
 * @param  string|null  $id  The value `id()` returns
 * @param  mixed  $regions  The value `get('regions')` returns
 * @param  mixed  $map  The value `get('map')` returns
 */
function queryLocationMock(?string $id, $regions, $map): Entry
{
    $collection = Mockery::mock(Collection::class);
    $collection->shouldReceive('handle')->andReturn('clinical_locations');

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('collection')->andReturn($collection);
    $entry->shouldReceive('get')->with('regions')->andReturn($regions);
    $entry->shouldReceive('get')->with('map')->andReturn($map);
    $entry->shouldReceive('id')->andReturn($id);

    return $entry;
}

/**
 * Fake the `clinical_locations` entry query to return the given entries.
 *
 * @param  array<int, Entry>  $entries
 */
function fakeLocationQuery(array $entries): void
{
    $builder = Mockery::mock(Builder::class);
    $builder->shouldReceive('where')->with('collection', 'clinical_locations')->andReturnSelf();
    $builder->shouldReceive('get')->andReturn(collect($entries));

    EntryFacade::shouldReceive('query')->andReturn($builder);
}

/**
 * Run the backfill command and return its PendingCommand for fluent assertions.
 *
 * The returned value MUST be chained on directly: a PendingCommand executes in
 * its destructor, so storing it would defer the run past the later queue
 * assertions.
 *
 * @param  string  $command  The full Artisan command string (with any options)
 */
function backfillRunCommand(string $command): PendingCommand
{
    // Return it unstored so the caller chains and it destructs in-statement.
    // pendingArtisan() resolves artisan()'s PendingCommand|int union.
    return pendingArtisan($command);
}

it('queues a backfill only for locations missing a region', function (): void {
    Queue::fake();

    fakeLocationQuery([
        queryLocationMock('needs-region', null, ['lat' => 34.7465, 'lng' => -92.2896]),
        queryLocationMock('already-set', 'central-arkansas', ['lat' => 34.7465, 'lng' => -92.2896]),
        queryLocationMock('no-coordinate', null, null),
    ]);

    backfillRunCommand('content:backfill-location-regions')->assertSuccessful();

    Queue::assertPushed(BackfillLocationRegion::class, 1);
    Queue::assertPushed(fn (BackfillLocationRegion $job): bool => $job->entryId === 'needs-region');
});

it('queues nothing on a dry run', function (): void {
    Queue::fake();

    fakeLocationQuery([
        queryLocationMock('needs-region', null, ['lat' => 34.7465, 'lng' => -92.2896]),
    ]);

    backfillRunCommand('content:backfill-location-regions --dry-run')
        ->expectsOutputToContain('Would queue 1 location')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

it('goes on past the locations it skips to queue the ones after them, naming each', function (): void {
    Queue::fake();

    fakeLocationQuery([
        queryLocationMock('already-set', 'central-arkansas', ['lat' => 34.7465, 'lng' => -92.2896]),
        queryLocationMock('no-coordinate', null, null),
        queryLocationMock(null, null, ['lat' => 34.7465, 'lng' => -92.2896]),
        queryLocationMock('needs-region-kirk', null, ['lat' => 34.7465, 'lng' => -92.2896]),
        queryLocationMock('needs-region-spock', null, ['lat' => 36.0626, 'lng' => -94.1574]),
    ]);

    backfillRunCommand('content:backfill-location-regions')
        ->expectsOutput('Queued needs-region-kirk')
        ->expectsOutput('Queued needs-region-spock')
        ->expectsOutput('Queued 2 locations.')
        ->assertSuccessful();

    Queue::assertPushed(BackfillLocationRegion::class, 2);
    Queue::assertPushed(fn (BackfillLocationRegion $job): bool => $job->entryId === 'needs-region-kirk');
    Queue::assertPushed(fn (BackfillLocationRegion $job): bool => $job->entryId === 'needs-region-spock');
});

it('names the location a dry run would queue, in the singular', function (): void {
    Queue::fake();

    fakeLocationQuery([
        queryLocationMock('needs-region', null, ['lat' => 34.7465, 'lng' => -92.2896]),
    ]);

    backfillRunCommand('content:backfill-location-regions --dry-run')
        ->expectsOutput('[dry-run] needs-region')
        ->expectsOutput('Would queue 1 location.')
        ->doesntExpectOutputToContain('Queued')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

it('reports no locations when none needs a region', function (): void {
    Queue::fake();

    fakeLocationQuery([
        queryLocationMock('already-set', 'central-arkansas', ['lat' => 34.7465, 'lng' => -92.2896]),
    ]);

    backfillRunCommand('content:backfill-location-regions')
        ->expectsOutput('Queued 0 locations.')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});
