<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaOutputService;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Entries\Entry;
use Statamic\Fields\Value;
use Tests\TestCase;

/**
 * Content-independent coverage for the location subtype → `@type` override
 * integration on App\Services\SchemaTemplates\SchemaOutputService (UAMS-Web/uams-statamic-archive#967).
 *
 * The pure multi-type rule is covered by
 * MedicalOrganizationSubtypeAtTypeResolverTest; here we exercise the engine
 * seam: reading `schema_medicalorganization_subtype` off the context entity,
 * unwrapping a Value, and — crucially — the refinement gate that scopes the
 * override to the location's own MedicalOrganization node and keeps it from
 * bleeding onto nested nodes (ContactPoint, PostalAddress) that share the same
 * context entity. The entry is mocked, so no content is required.
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaOutputServiceSubtypeAtTypeTest.php
 */
uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * @param  list<string>  $entryTypeNames
 * @return list<string>|null
 */
function subtypeAtType(EntryContract|Term|null $contextEntity, array $entryTypeNames): ?array
{
    return app(SchemaOutputService::class)
        ->resolveContextEntitySubtypeAtType($contextEntity, $entryTypeNames);
}

/**
 * Mock a location entry whose `schema_medicalorganization_subtype` returns the
 * given stored value.
 */
function mockLocationWithSubtype(mixed $subtype): Entry
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')
        ->with('schema_medicalorganization_subtype')
        ->andReturn($subtype);

    return $entry;
}

it('returns null when the context entity is not an entry', function (): void {
    expect(subtypeAtType(null, ['MedicalOrganization']))->toBeNull();
    expect(subtypeAtType(Mockery::mock(Term::class), ['MedicalOrganization']))->toBeNull();
});

it('drives the location node to its selected LocalBusiness-descendant subtype', function (string $subtype): void {
    expect(subtypeAtType(mockLocationWithSubtype($subtype), ['MedicalOrganization']))->toBe([$subtype]);
})->with(['Hospital', 'MedicalClinic', 'Pharmacy', 'Dentist']);

it('emits the DiagnosticLab multi-type for a DiagnosticLab location', function (): void {
    expect(subtypeAtType(mockLocationWithSubtype('DiagnosticLab'), ['MedicalOrganization']))
        ->toBe(['DiagnosticLab', 'LocalBusiness']);
});

it('unwraps a Value before resolving', function (): void {
    $value = Mockery::mock(Value::class);
    $value->shouldReceive('value')->andReturn('DiagnosticLab');

    expect(subtypeAtType(mockLocationWithSubtype($value), ['MedicalOrganization']))
        ->toBe(['DiagnosticLab', 'LocalBusiness']);
});

it('does not bleed the subtype onto nested nodes the subtype cannot refine', function (string $nestedType): void {
    // A ContactPoint / PostalAddress node shares the location context entity but
    // is not refined by a MedicalOrganization subtype, so the override is skipped.
    expect(subtypeAtType(mockLocationWithSubtype('Hospital'), [$nestedType]))->toBeNull();
})->with(['ContactPoint', 'PostalAddress', 'Person', 'GeoCoordinates']);

it('returns null for an empty, whitespace, unknown, or non-string subtype', function (mixed $subtype): void {
    expect(subtypeAtType(mockLocationWithSubtype($subtype), ['MedicalOrganization']))->toBeNull();
})->with([
    'empty' => [''],
    'whitespace' => ['   '],
    'unknown type' => ['NotARealType'],
    'array' => [['Hospital']],
    'null' => [null],
]);

it('returns null when there are no declared entry types', function (): void {
    expect(subtypeAtType(mockLocationWithSubtype('Hospital'), []))->toBeNull();
});
