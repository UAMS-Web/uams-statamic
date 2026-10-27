<?php

declare(strict_types=1);

use App\Services\Locations\LocationInheritedAddressService;
use Illuminate\Testing\PendingCommand;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Query\Builder;
use Statamic\Structures\Page;
use Tests\TestCase;

/**
 * Coverage for the one-shot `content:split-location-address-override` command (UAMS-Web/uams-statamic-archive#2608): a
 * child with the UAMS-Web/uams-statamic-archive#1269 address override on gets all four group toggles on, so it renders as
 * before; a child already split, or with the override off, is left alone; and a dry run
 * changes nothing. The Entry query is faked, as in BackfillLocationRegionsCommandTest.
 *
 * @command  php artisan test --compact tests/Feature/Console/SplitLocationAddressOverrideCommandTest.php
 */
uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * A mock location storing the given toggles, with every other group toggle absent.
 *
 * @param  array<string, mixed>  $stored
 */
function splitLocationMock(string $id, array $stored, bool $isChild = true): Entry&MockInterface
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn($id);
    $entry->shouldReceive('parent')->andReturn($isChild ? Mockery::mock(Page::class) : null);
    $entry->shouldReceive('get')->with('parent_address_override_query')->andReturn($stored['parent_address_override_query'] ?? null);

    foreach (LocationInheritedAddressService::GROUP_OVERRIDE as $toggle) {
        $entry->shouldReceive('get')->with($toggle)->andReturn($stored[$toggle] ?? null);
    }

    return $entry;
}

/**
 * Fake the `clinical_locations` entry query to return the given entries.
 *
 * @param  list<Entry>  $entries
 */
function fakeSplitLocationQuery(array $entries): void
{
    $builder = Mockery::mock(Builder::class);
    $builder->shouldReceive('where')->with('collection', 'clinical_locations')->andReturnSelf();
    $builder->shouldReceive('get')->andReturn(collect($entries));

    EntryFacade::shouldReceive('query')->andReturn($builder);
}

/**
 * Run the command, returned unstored so it executes in the caller's statement.
 */
function splitRunCommand(string $command): PendingCommand
{
    return pendingArtisan($command);
}

it('turns every group on for a child with the address override on', function (): void {
    $migrated = splitLocationMock('child1', ['parent_address_override_query' => true]);

    foreach (LocationInheritedAddressService::GROUP_OVERRIDE as $toggle) {
        $migrated->shouldReceive('set')->with($toggle, true)->once();
    }

    $migrated->shouldReceive('saveQuietly')->once();

    fakeSplitLocationQuery([$migrated]);

    splitRunCommand('content:split-location-address-override')
        ->expectsOutput('Split child1')
        ->expectsOutput('Split 1 location.')
        ->assertSuccessful();
});

it('leaves alone a child with the override off and a child already split', function (): void {
    $off = splitLocationMock('off1', ['parent_address_override_query' => false]);
    $split = splitLocationMock('split1', [
        'parent_address_override_query' => true,
        'parent_address_override_unit_query' => false,
    ]);

    foreach ([$off, $split] as $entry) {
        $entry->shouldNotReceive('set');
        $entry->shouldNotReceive('saveQuietly');
    }

    fakeSplitLocationQuery([$off, $split]);

    splitRunCommand('content:split-location-address-override')
        ->expectsOutput('Split 0 locations.')
        ->assertSuccessful();
});

it('leaves alone a top-level location carrying a leftover override', function (): void {
    // A location moved up from a child can keep the master; owning every group already,
    // it needs no toggles, and the docblock promises to change children only.
    $topLevel = splitLocationMock('top1', ['parent_address_override_query' => true], isChild: false);
    $topLevel->shouldNotReceive('set');
    $topLevel->shouldNotReceive('saveQuietly');

    fakeSplitLocationQuery([$topLevel]);

    splitRunCommand('content:split-location-address-override')
        ->expectsOutput('Split 0 locations.')
        ->assertSuccessful();
});

it('changes nothing on a dry run', function (): void {
    $child = splitLocationMock('child1', ['parent_address_override_query' => true]);
    $child->shouldNotReceive('set');
    $child->shouldNotReceive('saveQuietly');

    fakeSplitLocationQuery([$child]);

    splitRunCommand('content:split-location-address-override --dry-run')
        ->expectsOutput('[dry-run] child1')
        ->expectsOutput('Would split 1 location.')
        ->assertSuccessful();
});
