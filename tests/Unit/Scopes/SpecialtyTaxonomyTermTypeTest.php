<?php

declare(strict_types=1);

use App\Scopes\SpecialtyTaxonomyTermType;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Stache\Query\TermQueryBuilder;

uses(MockeryPHPUnitIntegration::class);

/**
 * Unit tests for `SpecialtyTaxonomyTermType` query scope aliases and `apply`
 * behavior.
 *
 * Mutation: 100% on App\Scopes\SpecialtyTaxonomyTermType (131 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Unit/Scopes/SpecialtyTaxonomyTermTypeTest.php
 */
it('parses the specialty_acgme encoding from expectationFromScopeAlias', function () {
    expect(SpecialtyTaxonomyTermType::expectationFromScopeAlias('specialty_acgme'))->toBe([
        'type' => SpecialtyTaxonomyTermType::TYPE_VALUE_SPECIALTY,
        'taxonomies' => [
            'specialties_acgme',
        ],
        'medical_query' => null,
    ]);
});

it('parses the research medical specialty alias from expectationFromScopeAlias', function () {
    expect(SpecialtyTaxonomyTermType::expectationFromScopeAlias('medical_research_specialty'))->toBe([
        'type' => SpecialtyTaxonomyTermType::TYPE_VALUE_SPECIALTY,
        'taxonomies' => SpecialtyTaxonomyTermType::RESEARCH_TAXONOMIES,
        'medical_query' => true,
    ]);
});

it('registers the fourteen aliases including base type, type-only, taxonomy-suffixed, and research handles', function () {
    // Load the static alias list for this scope
    $aliases = SpecialtyTaxonomyTermType::aliases();

    expect($aliases)->toContain('specialty');
    expect($aliases)->toContain('subspecialty');
    expect($aliases)->toContain('specialty_type_only');
    expect($aliases)->toContain('subspecialty_type_only');
    expect($aliases)->toContain('specialty_acgme');
    expect($aliases)->toContain('specialty_aoa');
    expect($aliases)->toContain('specialty_non_accredited');
    expect($aliases)->toContain('subspecialty_acgme');
    expect($aliases)->toContain('subspecialty_aoa');
    expect($aliases)->toContain('subspecialty_non_accredited');
    expect($aliases)->toContain('medical_research_specialty');
    expect($aliases)->toContain('non_medical_research_subspecialty');
    expect($aliases)->toHaveCount(14);
});

it('parses the type-only specialty alias to an empty taxonomy set', function () {
    expect(SpecialtyTaxonomyTermType::expectationFromScopeAlias('specialty_type_only'))->toBe([
        'type' => SpecialtyTaxonomyTermType::TYPE_VALUE_SPECIALTY,
        'taxonomies' => [],
        'medical_query' => null,
    ]);
});

it('parses the type-only subspecialty alias to an empty taxonomy set', function () {
    expect(SpecialtyTaxonomyTermType::expectationFromScopeAlias('subspecialty_type_only'))->toBe([
        'type' => SpecialtyTaxonomyTermType::TYPE_VALUE_SUBSPECIALTY,
        'taxonomies' => [],
        'medical_query' => null,
    ]);
});

it('adds only a type where and no taxonomies whereIn for specialty_type_only', function () {
    // The whole point of the type-only alias: constrain by specialty_subspecialty_type
    // WITHOUT a taxonomy clause, since taxonomy constraints union (never narrow).
    $scope = new SpecialtyTaxonomyTermType;
    $query = Mockery::mock(TermQueryBuilder::class);
    $query->shouldReceive('where')
        ->once()
        ->with(
            SpecialtyTaxonomyTermType::TYPE_FIELD,
            SpecialtyTaxonomyTermType::TYPE_VALUE_SPECIALTY
        )
        ->andReturnSelf();
    $query->shouldReceive('whereIn')->never();

    $scope->apply(
        $query,
        [
            'queryScopes' => [
                'specialty_type_only',
            ],
        ]
    );
});

it('adds a type where and a taxonomies whereIn when apply runs for specialty', function () {
    // Create the scope and a query mock
    $scope = new SpecialtyTaxonomyTermType;
    $query = Mockery::mock(TermQueryBuilder::class);
    $query->shouldReceive('where')
        ->once()
        ->with(
            SpecialtyTaxonomyTermType::TYPE_FIELD,
            SpecialtyTaxonomyTermType::TYPE_VALUE_SPECIALTY
        )
        ->andReturnSelf();
    $query->shouldReceive('whereIn')
        ->once()
        ->with(
            'taxonomies',
            array_values(SpecialtyTaxonomyTermType::TAXONOMY_BY_HANDLE_SUFFIX)
        )
        ->andReturnSelf();

    // Run apply with the `specialty` alias
    $scope->apply(
        $query,
        [
            'queryScopes' => [
                'specialty',
            ],
        ]
    );
});

it('adds a type where and a single taxonomy whereIn when apply runs for specialty_acgme', function () {
    // Create the scope and a query mock
    $scope = new SpecialtyTaxonomyTermType;
    $query = Mockery::mock(TermQueryBuilder::class);
    $query->shouldReceive('where')
        ->once()
        ->with(
            SpecialtyTaxonomyTermType::TYPE_FIELD,
            SpecialtyTaxonomyTermType::TYPE_VALUE_SPECIALTY
        )
        ->andReturnSelf();
    $query->shouldReceive('whereIn')
        ->once()
        ->with(
            'taxonomies',
            [
                'specialties_acgme',
            ]
        )
        ->andReturnSelf();

    // Run apply with a taxonomy-specific alias
    $scope->apply(
        $query,
        [
            'queryScopes' => [
                'specialty_acgme',
            ],
        ]
    );
});

it('constrains research taxonomies and medical_query for non_medical_research_subspecialty', function () {
    $scope = new SpecialtyTaxonomyTermType;
    $query = Mockery::mock(TermQueryBuilder::class);
    $query->shouldReceive('where')
        ->once()
        ->with(
            SpecialtyTaxonomyTermType::TYPE_FIELD,
            SpecialtyTaxonomyTermType::TYPE_VALUE_SUBSPECIALTY
        )
        ->andReturnSelf();
    $query->shouldReceive('whereIn')
        ->once()
        ->with(
            'taxonomies',
            SpecialtyTaxonomyTermType::RESEARCH_TAXONOMIES
        )
        ->andReturnSelf();
    $query->shouldReceive('where')
        ->once()
        ->with(
            SpecialtyTaxonomyTermType::MEDICAL_QUERY_FIELD,
            false
        )
        ->andReturnSelf();

    $scope->apply(
        $query,
        [
            'queryScopes' => [
                'non_medical_research_subspecialty',
            ],
        ]
    );
});

it('parses the comma-separated research form from expectationFromRuleArguments', function () {
    expect(SpecialtyTaxonomyTermType::expectationFromRuleArguments('specialty', 'medical'))->toEqual([
        'type' => SpecialtyTaxonomyTermType::TYPE_VALUE_SPECIALTY,
        'taxonomies' => SpecialtyTaxonomyTermType::RESEARCH_TAXONOMIES,
        'medical_query' => true,
    ]);
});

it('does not alter the query when no known scope handle is present', function () {
    // Create the scope and a query mock that should stay untouched
    $scope = new SpecialtyTaxonomyTermType;
    $query = Mockery::mock(TermQueryBuilder::class);
    $query->shouldReceive('where')->never();
    $query->shouldReceive('whereIn')->never();

    // Run apply with a handle that is not a registered alias
    $scope->apply(
        $query,
        [
            'queryScopes' => [
                'unrelated_scope',
            ],
        ]
    );
});

/**
 * The expectation a research handle or shorthand resolves to.
 *
 * @return array{type: string, taxonomies: list<string>, medical_query: bool}
 */
function specialtyResearchExpectation(string $type, bool $medical): array
{
    return ['type' => $type, 'taxonomies' => ['research_specialties_acgme', 'research_specialties_non_accredited'], 'medical_query' => $medical];
}

it('resolves every scope alias to its type, taxonomies and medical constraint', function (string $handle, ?array $expected): void {
    expect(SpecialtyTaxonomyTermType::expectationFromScopeAlias($handle))->toBe($expected);
})->with([
    'medical research specialty' => ['medical_research_specialty', specialtyResearchExpectation('specialty', true)],
    'medical research subspecialty' => ['medical_research_subspecialty', specialtyResearchExpectation('subspecialty', true)],
    'non-medical research specialty' => ['non_medical_research_specialty', specialtyResearchExpectation('specialty', false)],
    'non-medical research subspecialty' => ['non_medical_research_subspecialty', specialtyResearchExpectation('subspecialty', false)],
    'subspecialty' => ['subspecialty', ['type' => 'subspecialty', 'taxonomies' => ['specialties_acgme', 'specialties_aoa', 'specialties_non_accredited'], 'medical_query' => null]],
    'subspecialty aoa' => ['subspecialty_aoa', ['type' => 'subspecialty', 'taxonomies' => ['specialties_aoa'], 'medical_query' => null]],
    'subspecialty non-accredited' => ['subspecialty_non_accredited', ['type' => 'subspecialty', 'taxonomies' => ['specialties_non_accredited'], 'medical_query' => null]],
    'specialty non-accredited' => ['specialty_non_accredited', ['type' => 'specialty', 'taxonomies' => ['specialties_non_accredited'], 'medical_query' => null]],
    'an unknown subspecialty suffix' => ['subspecialty_osteopathic', null],
    'an unknown specialty suffix' => ['specialty_osteopathic', null],
    'an unrelated handle' => ['clinical_specialty', null],
]);

it('lists every alias, built from the taxonomy suffixes', function (): void {
    expect(SpecialtyTaxonomyTermType::aliases())->toBe([
        'specialty',
        'subspecialty',
        'specialty_type_only',
        'subspecialty_type_only',
        'specialty_acgme',
        'subspecialty_acgme',
        'specialty_aoa',
        'subspecialty_aoa',
        'specialty_non_accredited',
        'subspecialty_non_accredited',
        'medical_research_specialty',
        'medical_research_subspecialty',
        'non_medical_research_specialty',
        'non_medical_research_subspecialty',
    ]);
});

it('resolves a one-argument rule from an alias or a research shorthand', function (string $argument, ?array $expected): void {
    expect(SpecialtyTaxonomyTermType::expectationFromRuleArguments($argument))->toBe($expected);
})->with([
    'a padded alias' => ['  specialty_aoa ', ['type' => 'specialty', 'taxonomies' => ['specialties_aoa'], 'medical_query' => null]],
    'blank' => ['   ', null],
    'medical specialty' => ['medical_specialty', specialtyResearchExpectation('specialty', true)],
    'medical subspecialty' => ['medical_subspecialty', specialtyResearchExpectation('subspecialty', true)],
    'non-medical specialty' => ['non_medical_specialty', specialtyResearchExpectation('specialty', false)],
    'non-medical subspecialty' => ['non_medical_subspecialty', specialtyResearchExpectation('subspecialty', false)],
    'unknown' => ['clinical', null],
]);

it('resolves a two-argument rule from a type and a medical dimension in either order', function (string $first, string $second, ?array $expected): void {
    expect(SpecialtyTaxonomyTermType::expectationFromRuleArguments($first, $second))->toBe($expected);
})->with([
    'type then medical, mixed case and padding' => [' Specialty ', 'MEDICAL', specialtyResearchExpectation('specialty', true)],
    'medical dimension first' => ['non_medical', 'subspecialty', specialtyResearchExpectation('subspecialty', false)],
    'a padded second argument' => ['subspecialty', '  medical ', specialtyResearchExpectation('subspecialty', true)],
    'nonmedical spelled solid' => ['subspecialty', 'nonmedical', specialtyResearchExpectation('subspecialty', false)],
    'non-medical hyphenated' => ['specialty', 'Non-Medical', specialtyResearchExpectation('specialty', false)],
    'a blank first argument' => [' ', 'medical', null],
    'a blank second argument' => ['specialty', '', null],
    'two types' => ['specialty', 'subspecialty', null],
    'two dimensions' => ['medical', 'non_medical', null],
]);

it('resolves no rule from no arguments or more than two', function (): void {
    expect(SpecialtyTaxonomyTermType::expectationFromRuleArguments())->toBeNull()
        ->and(SpecialtyTaxonomyTermType::expectationFromRuleArguments('specialty', 'medical', 'extra'))->toBeNull();
});

it('leaves the query alone when it is not a builder or the values are not a list', function (): void {
    $query = Mockery::mock(TermQueryBuilder::class);
    $query->shouldReceive('where')->never();
    $query->shouldReceive('whereIn')->never();

    (new SpecialtyTaxonomyTermType)->apply($query, 'specialty');
    (new SpecialtyTaxonomyTermType)->apply(new stdClass, ['queryScopes' => ['specialty']]);
});

it('applies the first known handle after skipping blank, non-string and unknown ones', function (): void {
    $query = Mockery::mock(TermQueryBuilder::class);
    $query->shouldReceive('where')->once()->with(SpecialtyTaxonomyTermType::TYPE_FIELD, 'subspecialty')->andReturnSelf();
    $query->shouldReceive('whereIn')->once()->with('taxonomies', ['specialties_aoa'])->andReturnSelf();

    (new SpecialtyTaxonomyTermType)->apply($query, ['queryScopes' => [5, '', 'unrelated_scope', 'subspecialty_aoa', 'specialty']]);
});

it('adds the medical constraint for a medical research handle', function (): void {
    $query = Mockery::mock(TermQueryBuilder::class);
    $query->shouldReceive('where')->once()->with(SpecialtyTaxonomyTermType::TYPE_FIELD, 'specialty')->andReturnSelf();
    $query->shouldReceive('whereIn')->once()->with('taxonomies', SpecialtyTaxonomyTermType::RESEARCH_TAXONOMIES)->andReturnSelf();
    $query->shouldReceive('where')->once()->with(SpecialtyTaxonomyTermType::MEDICAL_QUERY_FIELD, true)->andReturnSelf();

    (new SpecialtyTaxonomyTermType)->apply($query, ['queryScopes' => 'medical_research_specialty']);
});
