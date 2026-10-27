<?php

declare(strict_types=1);

use App\Enums\SchemaType;
use App\Scopes\EntriesBySchemaType;
use Illuminate\Support\Str;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Query\Builder;

uses(MockeryPHPUnitIntegration::class);

/**
 * @covers \App\Scopes\EntriesBySchemaType
 *
 * Mutation: 78 of 80 on App\Scopes\EntriesBySchemaType, measured on macOS on 2026-10-08, serial, --covered-only, with every test file that names the class. The two
 * survivors are explained beside the code they mutate: aliases() runs its fallbacks only
 * on a process's first call. Eleven equivalent mutants are annotated in the class.
 *
 * @command  php artisan test --compact tests/Unit/Scopes/EntriesBySchemaTypeScopeTest.php
 */
it('skips the filter when the thing scope is encoded', function () {
    $scope = new EntriesBySchemaType;
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('where')->never();

    $scope->apply(
        $query,
        [
            'queryScopes' => [
                'entries_by_schema_type_thing',
            ],
        ]
    );
});

it('skips when no matching scope handles are present', function () {
    $scope = new EntriesBySchemaType;
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('where')->never();

    $scope->apply(
        $query,
        [
            'queryScopes' => [
                'other_scope',
            ],
        ]
    );
});

it('adds a where clause for a non-root schema type', function () {
    $scope = new EntriesBySchemaType;
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('where')
        ->once()
        ->withArgs(
            fn ($arg): bool => $arg instanceof Closure
        )
        ->andReturnSelf();

    $scope->apply(
        $query,
        [
            'queryScopes' => [
                'entries_by_schema_type_person',
            ],
        ]
    );
});

it('does nothing when not bound to a Statamic query builder', function () {
    $scope = new EntriesBySchemaType;

    // Off a Statamic builder the scope must be inert: returning without touching
    // the value is the whole contract, so "nothing was thrown" is the assertion.
    // throwableFrom() rather than `->not->toThrow(…)` because dropping the guard
    // fails as an Error (a query method called on null/stdClass), and that idiom
    // passes on any throwable outside the class it names (UAMS-Web/uams-statamic-archive#1759).
    expect(throwableFrom(fn () => $scope->apply(null, ['queryScopes' => ['entries_by_schema_type_person']])))->toBeNull()
        ->and(throwableFrom(fn () => $scope->apply(new stdClass, ['queryScopes' => ['entries_by_schema_type_person']])))->toBeNull();
});

it('registers a resolving Str::snake alias for every in-use schema-type handle', function () {
    // Every handle a shipped blueprint references must resolve to the unified
    // scope — no silent no-op like the former slug `administrativearea`. Covers
    // both the schema_objects pickers and the clinical pickers migrated from the
    // deleted `resolved_schema_type_*` family (UAMS-Web/uams-statamic-archive#1408).
    EntriesBySchemaType::clearAliasesCache();

    $aliases = EntriesBySchemaType::aliases();

    $inUse = [
        // schema_objects pickers
        'entries_by_schema_type_administrative_area',
        'entries_by_schema_type_country',
        'entries_by_schema_type_organization',
        'entries_by_schema_type_person',
        'entries_by_schema_type_place',
        'entries_by_schema_type_web_site',
        // clinical pickers (migrated from resolved_schema_type_*)
        'entries_by_schema_type_medical_condition',
        'entries_by_schema_type_medical_sign',
        'entries_by_schema_type_medical_sign_or_symptom',
        'entries_by_schema_type_medical_test',
        'entries_by_schema_type_medical_therapy',
        'entries_by_schema_type_physical_exam',
    ];

    foreach ($inUse as $handle) {
        expect($aliases)->toContain($handle);
    }
});

it('reads past scope handles that are not text to find its own', function () {
    $scope = new EntriesBySchemaType;
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('where')
        ->once()
        ->withArgs(
            fn ($arg): bool => $arg instanceof Closure
        )
        ->andReturnSelf();

    $scope->apply(
        $query,
        [
            'queryScopes' => [
                42,
                ['nested'],
                'entries_by_schema_type_person',
            ],
        ]
    );
});

/**
 * Apply the scope and return the constraints its nested closure adds, as [method, ...args].
 *
 * @param  list<mixed>  $queryScopes
 * @return list<array<array-key, mixed>>
 */
function schemaTypeScopeConstraints(array $queryScopes): array
{
    $recorded = [];

    $nested = Mockery::mock(Builder::class);
    foreach (['whereIn', 'orWhereJsonContains'] as $method) {
        $nested->shouldReceive($method)->andReturnUsing(function (mixed ...$args) use (&$recorded, $nested, $method) {
            $recorded[] = [$method, ...$args];

            return $nested;
        });
    }

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('where')->andReturnUsing(function (Closure $constraint) use ($query, $nested) {
        $constraint($nested);

        return $query;
    });

    (new EntriesBySchemaType)->apply($query, ['queryScopes' => $queryScopes]);

    return $recorded;
}

it('matches the flat leaf with each type and its descendants once, and the chained path with each leaf', function () {
    // Country is itself a descendant of AdministrativeArea, so it must appear once.
    expect(schemaTypeScopeConstraints(['entries_by_schema_type_administrative_area', 'entries_by_schema_type_country']))->toBe([
        ['whereIn', 'schema->type', ['AdministrativeArea', 'City', 'Country', 'SchoolDistrict', 'State']],
        ['orWhereJsonContains', 'schema->type', 'AdministrativeArea'],
        ['orWhereJsonContains', 'schema->type', 'City'],
        ['orWhereJsonContains', 'schema->type', 'Country'],
        ['orWhereJsonContains', 'schema->type', 'SchoolDistrict'],
        ['orWhereJsonContains', 'schema->type', 'State'],
        ['orWhereJsonContains', 'schema_type->selections', 'AdministrativeArea'],
        ['orWhereJsonContains', 'schema_type->selections', 'Country'],
    ]);
});

it('reads a handle suffix in any case', function () {
    expect(schemaTypeScopeConstraints(['entries_by_schema_type_COUNTRY']))->toBe([
        ['whereIn', 'schema->type', ['Country']],
        ['orWhereJsonContains', 'schema->type', 'Country'],
        ['orWhereJsonContains', 'schema_type->selections', 'Country'],
    ]);
});

it('reads past another scope\'s handle to find its own', function () {
    expect(schemaTypeScopeConstraints(['other_scope', 'entries_by_schema_type_country']))->toBe([
        ['whereIn', 'schema->type', ['Country']],
        ['orWhereJsonContains', 'schema->type', 'Country'],
        ['orWhereJsonContains', 'schema_type->selections', 'Country'],
    ]);
});

it('builds one prefixed, snake-cased alias per schema type, as a list', function () {
    $build = new ReflectionMethod(EntriesBySchemaType::class, 'buildAliases');

    /** @var array<int, string> $aliases */
    $aliases = $build->invoke(null);

    $expected = array_values(array_unique(array_map(
        fn (SchemaType $case): string => 'entries_by_schema_type_'.Str::snake($case->value),
        SchemaType::cases(),
    )));

    expect($aliases)->toBe($expected)
        ->and($aliases)->toContain('entries_by_schema_type_administrative_area')
        ->and(array_is_list($aliases))->toBeTrue();
});

it('keeps the expanded types a list when a repeat is dropped from the middle', function () {
    // Country first, then its ancestor: the second Country falls mid-list, not at the end.
    expect(schemaTypeScopeConstraints(['entries_by_schema_type_country', 'entries_by_schema_type_administrative_area']))->toBe([
        ['whereIn', 'schema->type', ['Country', 'AdministrativeArea', 'City', 'SchoolDistrict', 'State']],
        ['orWhereJsonContains', 'schema->type', 'Country'],
        ['orWhereJsonContains', 'schema->type', 'AdministrativeArea'],
        ['orWhereJsonContains', 'schema->type', 'City'],
        ['orWhereJsonContains', 'schema->type', 'SchoolDistrict'],
        ['orWhereJsonContains', 'schema->type', 'State'],
        ['orWhereJsonContains', 'schema_type->selections', 'Country'],
        ['orWhereJsonContains', 'schema_type->selections', 'AdministrativeArea'],
    ]);
});
