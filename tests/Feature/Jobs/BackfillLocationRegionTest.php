<?php

declare(strict_types=1);

use App\Jobs\BackfillLocationRegion;
use App\Services\Locations\LocationRegionBackfiller;
use App\Services\Locations\LocationRegionResolver;
use Illuminate\Support\Facades\Log;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\LegacyMockInterface;
use Mockery\MockInterface;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Entry;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Tests\TestCase;

covers(BackfillLocationRegion::class);

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``.
 */
beforeEach(function (): void {
    static $padded = false;
    if ($padded) {
        return;
    }

    if (runningUnderMutate()) {
        usleep(12_000_000);
        $padded = true;
    }
});

/**
 * Coverage for the queued region backfill job (UAMS-Web/uams-statamic-archive#1112). The entry is re-loaded by
 * id (never carried across the queue boundary), so the job must no-op cleanly
 * when the entry is gone and must swallow + log a resolver failure rather than
 * fail into a retry stall. The gate/shape logic itself lives in — and is tested
 * via — LocationRegionBackfiller; here the real backfiller is driven with a mock
 * resolver so the whole load → gate → persist path is exercised.
 *
 * Mutation: tracked under App\Jobs\BackfillLocationRegion (--covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Jobs/BackfillLocationRegionTest.php
 */
uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Build a mock clinical_locations entry that qualifies for a backfill (empty
 * region, usable coordinate, single-item regions field).
 *
 * @return EntryModel&MockInterface
 */
function qualifyingLocationMock(): EntryModel
{
    $collection = Mockery::mock(Collection::class);
    $collection->shouldReceive('handle')->andReturn('clinical_locations');

    $field = Mockery::mock(Field::class);
    $field->shouldReceive('get')->with('max_items')->andReturn(1);

    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('field')->with('regions')->andReturn($field);

    // Mock the concrete Statamic\Entries\Entry: the custom App\Entries\EntryRepository
    // facade root pins find()'s return type to the concrete class, not the contract.
    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('collection')->andReturn($collection);
    $entry->shouldReceive('blueprint')->andReturn($blueprint);
    $entry->shouldReceive('get')->with('regions')->andReturn(null);
    $entry->shouldReceive('get')->with('map')->andReturn(['lat' => 34.7465, 'lng' => -92.2896]);

    return $entry;
}

it('re-loads the entry and fills its region through the backfiller', function (): void {
    $entry = qualifyingLocationMock();
    $entry->shouldReceive('set')->once()->with('regions', 'central-arkansas');
    $entry->shouldReceive('saveQuietly')->once();

    Entry::shouldReceive('find')->once()->with('loc-1')->andReturn($entry);

    $resolver = Mockery::mock(LocationRegionResolver::class);
    $resolver->shouldReceive('regionsForCoordinate')->once()->with(34.7465, -92.2896)
        ->andReturn(['central-arkansas']);

    (new BackfillLocationRegion('loc-1'))->handle(new LocationRegionBackfiller($resolver));
});

it('no-ops when the entry no longer exists', function (): void {
    // InstanceOfToTrue on ``! $entry instanceof EntryModel`` would call backfill(null),
    // throw, and log: the clean path must neither resolve nor warn.
    /** @var LegacyMockInterface $log */
    $log = Log::spy();

    Entry::shouldReceive('find')->once()->with('gone')->andReturn(null);

    $resolver = Mockery::mock(LocationRegionResolver::class);
    $resolver->shouldNotReceive('regionsForCoordinate');

    (new BackfillLocationRegion('gone'))->handle(new LocationRegionBackfiller($resolver));

    $log->shouldNotHaveReceived('warning');
});

it('swallows and logs a resolver failure instead of stalling a retry', function (): void {
    /** @var LegacyMockInterface $log */
    $log = Log::spy();

    $entry = qualifyingLocationMock();
    $entry->shouldNotReceive('set');
    $entry->shouldNotReceive('saveQuietly');

    Entry::shouldReceive('find')->once()->with('loc-1')->andReturn($entry);

    $resolver = Mockery::mock(LocationRegionResolver::class);
    $resolver->shouldReceive('regionsForCoordinate')->once()->andThrow(new RuntimeException('census unavailable'));

    // Must not throw out of the job.
    (new BackfillLocationRegion('loc-1'))->handle(new LocationRegionBackfiller($resolver));

    // RemoveArrayItem must not drop either context key.
    $log->shouldHaveReceived('warning')->once()
        ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'BackfillLocationRegion')
            && ($context['entry'] ?? null) === 'loc-1'
            && ($context['message'] ?? null) === 'census unavailable');
});
