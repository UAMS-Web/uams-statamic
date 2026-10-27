<?php

declare(strict_types=1);

use App\Services\ComputedFields\Reverse\OrganizationsReverseReferenceComputedValues;
use App\Services\ComputedFields\Reverse\ReverseEntryReferenceResolver;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Entries\EntryCollection;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/*
|--------------------------------------------------------------------------
| OrganizationsReverseReferenceComputedValues (UAMS-Web/uams-statamic-archive#1373)
|--------------------------------------------------------------------------
|
| The `organizations` reverse-reference computeds are thin wiring over the shared
| ReverseEntryReferenceResolver (whose real-Stache behavior — scalar/array/multi
| storage matching — is proved in ReverseEntryReferenceResolverTest). These mock
| the resolver to pin the exact (collections, field, id) each reverse resolves:
| the service-line members query spans locations + people under the shared
| `service_line` handle, while the residents / fellows reverses query only people
| under their distinct academic-department pointers.
|
| @covers \App\Services\ComputedFields\Reverse\OrganizationsReverseReferenceComputedValues
|
| @command php artisan test --compact tests/Feature/Services/ComputedFields/Reverse/OrganizationsReverseReferenceComputedValuesTest.php
*/

function orgReverseEntry(?string $id): Entry
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn($id);

    return $entry;
}

it('resolves service line members from the shared service_line handle across locations and people', function (): void {
    $expected = EntryCollection::make();
    $entry = orgReverseEntry('org-1');

    $resolver = Mockery::mock(ReverseEntryReferenceResolver::class);
    $resolver->shouldReceive('resolve')
        ->once()
        ->with(['clinical_locations', 'people'], 'service_line', 'org-1')
        ->andReturn($expected);

    expect((new OrganizationsReverseReferenceComputedValues($resolver))->resolveServiceLineMembers($entry))
        ->toBe($expected);
});

it('resolves current residents from the people resident_academic_department field', function (): void {
    $expected = EntryCollection::make();
    $entry = orgReverseEntry('org-2');

    $resolver = Mockery::mock(ReverseEntryReferenceResolver::class);
    $resolver->shouldReceive('resolve')
        ->once()
        ->with('people', 'resident_academic_department', 'org-2')
        ->andReturn($expected);

    expect((new OrganizationsReverseReferenceComputedValues($resolver))->resolveAcademicDepartmentResidents($entry))
        ->toBe($expected);
});

it('resolves current fellows from the people fellow_academic_department field', function (): void {
    $expected = EntryCollection::make();
    $entry = orgReverseEntry('org-3');

    $resolver = Mockery::mock(ReverseEntryReferenceResolver::class);
    $resolver->shouldReceive('resolve')
        ->once()
        ->with('people', 'fellow_academic_department', 'org-3')
        ->andReturn($expected);

    expect((new OrganizationsReverseReferenceComputedValues($resolver))->resolveAcademicDepartmentFellows($entry))
        ->toBe($expected);
});

it('keeps the residents and fellows reverses distinct so their fields are not swapped', function (): void {
    // Distinct return instances catch a residents ↔ fellows binding swap.
    $residents = EntryCollection::make();
    $fellows = EntryCollection::make();
    $entry = orgReverseEntry('org-4');

    $resolver = Mockery::mock(ReverseEntryReferenceResolver::class);
    $resolver->shouldReceive('resolve')->once()->with('people', 'resident_academic_department', 'org-4')->andReturn($residents);
    $resolver->shouldReceive('resolve')->once()->with('people', 'fellow_academic_department', 'org-4')->andReturn($fellows);

    $service = new OrganizationsReverseReferenceComputedValues($resolver);

    expect($service->resolveAcademicDepartmentResidents($entry))->toBe($residents)
        ->and($service->resolveAcademicDepartmentFellows($entry))->toBe($fellows);
});

it('passes the entry id straight through, including a null id for an unsaved organization', function (): void {
    // The resolver owns the id-less short-circuit; the service must forward whatever
    // id() returns without pre-filtering, so an unsaved organization still delegates.
    $members = EntryCollection::make();
    $residents = EntryCollection::make();
    $fellows = EntryCollection::make();
    $entry = orgReverseEntry(null);

    $resolver = Mockery::mock(ReverseEntryReferenceResolver::class);
    $resolver->shouldReceive('resolve')->once()->with(['clinical_locations', 'people'], 'service_line', null)->andReturn($members);
    $resolver->shouldReceive('resolve')->once()->with('people', 'resident_academic_department', null)->andReturn($residents);
    $resolver->shouldReceive('resolve')->once()->with('people', 'fellow_academic_department', null)->andReturn($fellows);

    $service = new OrganizationsReverseReferenceComputedValues($resolver);

    expect($service->resolveServiceLineMembers($entry))->toBe($members)
        ->and($service->resolveAcademicDepartmentResidents($entry))->toBe($residents)
        ->and($service->resolveAcademicDepartmentFellows($entry))->toBe($fellows);
});
