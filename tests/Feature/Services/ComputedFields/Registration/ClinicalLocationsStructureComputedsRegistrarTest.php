<?php

declare(strict_types=1);

use App\Services\ComputedFields\Registration\ClinicalLocationsStructureComputedsRegistrar;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;
use Statamic\Structures\Page;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``.
 */
beforeEach(function (): void {
    static $padded = false;
    if ($padded) {
        return;
    }

    if (runningUnderMutate()) {
        usleep(20_000_000);
        $padded = true;
    }
});

/**
 * Capture the two structure computeds the registrar binds, keyed by handle.
 *
 * @return array<string, array{0: mixed, 1: callable}>
 */
function locationsStructureComputeds(): array
{
    $captured = [];
    Collection::shouldReceive('computed')->andReturnUsing(
        function (mixed $collection, string $handle, callable $callback) use (&$captured): void {
            $captured[$handle] = [$collection, $callback];
        }
    );

    (new ClinicalLocationsStructureComputedsRegistrar)->register();

    return $captured;
}

/**
 * A location entry mock whose structural parent is $parent.
 */
function locationWithParent(mixed $parent): Entry
{
    $location = Mockery::mock(Entry::class);
    $location->shouldReceive('parent')->andReturn($parent);

    return $location;
}

/**
 * A parent location entry mock with the given field values and URL.
 *
 * @param  array<string, mixed>  $values
 */
function parentLocation(array $values, mixed $url = '/locations/parent'): Entry
{
    $parent = Mockery::mock(Entry::class);
    $parent->shouldReceive('get')->andReturnUsing(fn (string $handle): mixed => $values[$handle] ?? null);
    $parent->shouldReceive('url')->andReturn($url);

    return $parent;
}

/**
 * @covers \App\Services\ComputedFields\Registration\ClinicalLocationsStructureComputedsRegistrar
 *
 * @command php artisan test --compact tests/Feature/Services/ComputedFields/Registration/ClinicalLocationsStructureComputedsRegistrarTest.php
 */
it('registers the parent name and URL computeds on clinical_locations', function (): void {
    $captured = locationsStructureComputeds();

    expect(array_keys($captured))->toBe(['parent_location_name', 'parent_location_url'])
        ->and(array_column($captured, 0))->toBe(['clinical_locations', 'clinical_locations']);
});

it('names the parent by its generic name over its title, and links its URL', function (): void {
    $captured = locationsStructureComputeds();
    $location = locationWithParent(parentLocation(['generic_name' => 'Sickbay', 'title' => 'Sickbay (Deck 5)']));

    expect(($captured['parent_location_name'][1])($location, null))->toBe('Sickbay')
        ->and(($captured['parent_location_url'][1])($location, null))->toBe('/locations/parent');
});

it('falls back to the parent title when the generic name is empty', function (): void {
    $captured = locationsStructureComputeds();
    $location = locationWithParent(parentLocation(['generic_name' => '', 'title' => 'Engineering']));

    expect(($captured['parent_location_name'][1])($location, null))->toBe('Engineering');
});

it('unwraps a structure page parent to its entry', function (): void {
    $captured = locationsStructureComputeds();

    $page = Mockery::mock(Page::class);
    $page->shouldReceive('entry')->andReturn(parentLocation(['title' => 'Transporter Room'], '/locations/transporter'));
    $location = locationWithParent($page);

    expect(($captured['parent_location_name'][1])($location, null))->toBe('Transporter Room')
        ->and(($captured['parent_location_url'][1])($location, null))->toBe('/locations/transporter');
});

it('returns null for both when the location has no parent entry', function (mixed $parent): void {
    $captured = locationsStructureComputeds();
    $location = locationWithParent($parent);

    expect(($captured['parent_location_name'][1])($location, null))->toBeNull()
        ->and(($captured['parent_location_url'][1])($location, null))->toBeNull();
})->with([
    'top level' => [null],
    'a page with no entry' => [fn (): Page => tap(Mockery::mock(Page::class), fn ($page) => $page->shouldReceive('entry')->andReturnNull())],
    // Page::entry() is typed to the entry contract, so a page can hand back an
    // entry that is not the concrete class the computeds read.
    'a page whose entry is only the contract' => [fn (): Page => tap(Mockery::mock(Page::class), fn ($page) => $page->shouldReceive('entry')->andReturn(Mockery::mock(EntryContract::class)))],
]);

it('returns null for a parent name or URL that is not a string', function (): void {
    $captured = locationsStructureComputeds();
    $location = locationWithParent(parentLocation(['generic_name' => ['Ten Forward'], 'title' => 'Ten Forward'], null));

    expect(($captured['parent_location_name'][1])($location, null))->toBeNull()
        ->and(($captured['parent_location_url'][1])($location, null))->toBeNull();
});
