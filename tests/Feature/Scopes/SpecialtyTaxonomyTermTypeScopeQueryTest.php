<?php

declare(strict_types=1);

use App\Scopes\SpecialtyTaxonomyTermType;
use Illuminate\Support\Collection;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Taxonomies\LocalizedTerm;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Real term-query behavior of `SpecialtyTaxonomyTermType` against an isolated
 * Stache (not a mocked builder), covering the `other_specialty` leak (UAMS-Web/uams-statamic-archive#1365):
 *
 * The `other_specialty` / `other_subspecialty` pickers set their own
 * `taxonomies: [other_specialties]`, then previously layered the `specialty` /
 * `subspecialty` scope aliases — which hard-inject the three MEDICAL specialty
 * taxonomies. Because Statamic's `TermQueryBuilder::whereIn('taxonomies', …)`
 * UNIONS (array_merge) rather than intersects, the effective set became all four
 * and medical-specialty terms leaked into the non-medical pickers.
 *
 * The new `specialty_type_only` / `subspecialty_type_only` aliases restrict by
 * `specialty_subspecialty_type` ALONE (no taxonomy clause), so the field's own
 * `other_specialties` taxonomy is the sole taxonomy constraint.
 *
 * All six specialty taxonomies of the `specialties_all` search index
 * (config/statamic/search.php) are created: a term save updates that index,
 * which resolves every sibling taxonomy.
 *
 * @covers \App\Scopes\SpecialtyTaxonomyTermType
 *
 * @command  php artisan test --compact tests/Feature/Scopes/SpecialtyTaxonomyTermTypeScopeQueryTest.php
 */
uses(StacheIsolatedTestCase::class);

/**
 * Apply the scope to a term query pre-constrained to the given field taxonomies
 * (mimicking `TermsSearchIndex::getIndexQuery`) and return the matching slugs.
 *
 * @param  array<int, string>  $fieldTaxonomies
 * @param  array<int, string>  $queryScopes
 * @return array<int, string>
 */
function specialtyScopeSlugs(array $fieldTaxonomies, array $queryScopes): array
{
    $query = Term::query();
    $query->whereIn('taxonomies', $fieldTaxonomies);

    (new SpecialtyTaxonomyTermType)->apply($query, ['queryScopes' => $queryScopes]);

    /** @var Collection<int, LocalizedTerm> $terms */
    $terms = $query->get();

    return $terms
        ->map(function ($term): string {
            /** @var string $slug */
            $slug = $term->slug();

            return $slug;
        })
        ->sort()
        ->values()
        ->all();
}

beforeEach(function (): void {
    // Every specialty taxonomy the `specialties_all` search index spans — a term
    // save updates that index across all of them, so each must resolve.
    foreach ([
        'other_specialties',
        'specialties_acgme',
        'specialties_aoa',
        'specialties_non_accredited',
        'research_specialties_acgme',
        'research_specialties_non_accredited',
    ] as $taxonomyHandle) {
        /** @var Statamic\Taxonomies\Taxonomy $taxonomy */
        $taxonomy = Taxonomy::make($taxonomyHandle);
        $taxonomy->title($taxonomyHandle);
        $taxonomy->save();
    }

    // A specialty and a subspecialty term in the non-medical `other_specialties`.
    /** @var Statamic\Taxonomies\Term $specialtyTerm */
    $specialtyTerm = Term::make();
    $specialtyTerm->taxonomy('other_specialties');
    $specialtyTerm->slug('other-spec');
    $specialtyTerm->dataForLocale($specialtyTerm->defaultLocale(), ['title' => 'Other Spec', 'specialty_subspecialty_type' => 'specialty']);
    $specialtyTerm->saveQuietly();

    /** @var Statamic\Taxonomies\Term $subspecialtyTerm */
    $subspecialtyTerm = Term::make();
    $subspecialtyTerm->taxonomy('other_specialties');
    $subspecialtyTerm->slug('other-subspec');
    $subspecialtyTerm->dataForLocale($subspecialtyTerm->defaultLocale(), ['title' => 'Other Subspec', 'specialty_subspecialty_type' => 'subspecialty']);
    $subspecialtyTerm->saveQuietly();

    // A medical-specialty term that must NOT leak into the non-medical pickers.
    /** @var Statamic\Taxonomies\Term $medicalTerm */
    $medicalTerm = Term::make();
    $medicalTerm->taxonomy('specialties_acgme');
    $medicalTerm->slug('acgme-spec');
    $medicalTerm->dataForLocale($medicalTerm->defaultLocale(), ['title' => 'ACGME Spec', 'specialty_subspecialty_type' => 'specialty']);
    $medicalTerm->saveQuietly();
});

it('lists only the type=specialty other_specialties term for specialty_type_only', function (): void {
    expect(specialtyScopeSlugs(['other_specialties'], ['specialty_type_only']))
        ->toBe(['other-spec']);
});

it('lists only the type=subspecialty other_specialties term for subspecialty_type_only', function (): void {
    expect(specialtyScopeSlugs(['other_specialties'], ['subspecialty_type_only']))
        ->toBe(['other-subspec']);
});

it('the bare specialty alias still unions the medical taxonomies (why type-only exists)', function (): void {
    // Documents the Statamic union semantics the leak rode on, and proves the
    // medical `specialty` picker is unchanged: applied over a query already
    // scoped to `other_specialties`, the bare alias pulls in `specialties_acgme`.
    expect(specialtyScopeSlugs(['other_specialties'], ['specialty']))
        ->toBe(['acgme-spec', 'other-spec']);
});
