<?php

declare(strict_types=1);

use App\Services\ComputedFields\DepartmentHub\DepartmentHubPeopleComputedValues;
use App\Support\Multisite\DepartmentHubPeopleRoster;
use App\Support\Multisite\DepartmentHubResolver;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Entries\EntryCollection;
use Tests\TestCase;
use UAMSWeb\Aether\People\Contracts\DepartmentHubRoster;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(DepartmentHubPeopleRoster::class);

/**
 * Coverage for the site's binding of `uams-web/aether-people`'s `DepartmentHubRoster`
 * contract (#281): each method hands its argument to the site class that answers it
 * and returns that answer unchanged.
 *
 * The collaborators are Mockery doubles, so this runs without the `content/` submodule.
 *
 * @command  php artisan test --compact tests/Feature/Support/Multisite/DepartmentHubPeopleRosterTest.php
 */
it('is what the container binds the add-on contract to', function (): void {
    expect(get_class(app()->make(DepartmentHubRoster::class)))->toBe(DepartmentHubPeopleRoster::class);
});

it('answers organization() with the hub resolver\'s entry', function (): void {
    $organization = Mockery::mock(Entry::class);

    $hubs = Mockery::mock(DepartmentHubResolver::class);
    $hubs->shouldReceive('organization')->once()->with('medicine_anesthesiology')->andReturn($organization);
    $hubs->shouldReceive('organization')->once()->with('plain_site')->andReturnNull();

    $roster = new DepartmentHubPeopleRoster($hubs, Mockery::mock(DepartmentHubPeopleComputedValues::class));

    expect($roster->organization('medicine_anesthesiology'))->toBe($organization)
        ->and($roster->organization('plain_site'))->toBeNull();
});

it('answers resolvePeople() with the roster computeds\' collection', function (): void {
    $organization = Mockery::mock(Entry::class);
    $people = new EntryCollection([Mockery::mock(Entry::class)]);

    $computed = Mockery::mock(DepartmentHubPeopleComputedValues::class);
    $computed->shouldReceive('resolvePeople')->once()->with($organization)->andReturn($people);

    $roster = new DepartmentHubPeopleRoster(Mockery::mock(DepartmentHubResolver::class), $computed);

    expect($roster->resolvePeople($organization))->toBe($people);
});
