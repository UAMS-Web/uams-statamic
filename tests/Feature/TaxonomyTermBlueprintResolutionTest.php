<?php

declare(strict_types=1);

use Statamic\Facades\Blink;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Taxonomy;
use Tests\TestCase;

uses(TestCase::class);

// Blueprint::in() Blink-caches resolved file paths; flush between tests so a
// stale in-memory path cache can never mask a genuine resolution regression.
beforeEach(fn () => Blink::flush());

/*
|--------------------------------------------------------------------------
| Taxonomy term-blueprint resolution (UAMS-Web/uams-statamic-archive#882)
|--------------------------------------------------------------------------
|
| In Statamic 6, Taxonomy::termBlueprints() resolves the rich term blueprint
| via Blueprint::in('taxonomies/'.$handle) using the PLURAL taxonomy handle,
| silently falling back to a generated title/slug-only blueprint when that
| namespace is empty (Taxonomy.php L108-119, fallbackTermBlueprint() L162-173).
|
| A singular blueprint DIRECTORY therefore never resolves against a plural
| taxonomy handle, so the rich field set never loads and the CP shows only
| title/slug. These tests fail if any renamed directory regresses to a
| singular name that no longer matches its plural handle.
*/

/**
 * Plural taxonomy handles whose term-blueprint directories were renamed from
 * singular in UAMS-Web/uams-statamic-archive#882. Each must now resolve a real blueprint file on disk.
 */
$renamedHandles = [
    'affiliations_hospital',
    'education_types',
    // `patient_types` became a structured collection (UAMS-Web/uams-statamic-archive#2598); its blueprint moved
    // to collections/patient_types/ and the tree carries the hierarchy `parent` held.
    // `people_groups` migrated from a flat taxonomy to a structured collection
    // (UAMS-Web/uams-statamic-archive#1863), so its blueprint now lives at collections/people_groups/ — there
    // is no taxonomies/people_groups/ namespace to resolve here anymore.
    // `post_categories` migrated from a flat taxonomy to a structured collection
    // (UAMS-Web/uams-statamic-archive#1433), so its blueprint now lives at collections/post_categories/ — there
    // is no taxonomies/post_categories/ namespace to resolve here anymore.
    // `post_tags` followed as a site-scoped structured collection (UAMS-Web/uams-statamic-archive#2532); its
    // blueprint moved to collections/post_tags/ the same way.
    'residency_years',
];

it('resolves a real term-blueprint file at the plural taxonomy namespace', function (string $handle) {
    // Blueprint::in() reads resources/blueprints/taxonomies/<handle>/ from disk.
    // A non-zero count means the directory name matches the plural handle.
    // NOTE: assert count(), not toBeEmpty() — empty() is always false on a
    // Collection object, so toBeEmpty() would pass even for a missing namespace.
    expect(Blueprint::in("taxonomies/{$handle}")->count())->toBeGreaterThan(0);
})->with($renamedHandles);

it('loads the rich blueprint (not the title/slug fallback) for each rich handle', function (string $handle, string $richField) {
    // Each rich field below exists only in the on-disk blueprint, never in the
    // generated fallback — so resolving it proves the renamed directory loaded.
    // Guards blueprint CONTENT for several handles, not just one (the count()
    // dataset above guards only directory<->handle resolution).
    /** @var Statamic\Taxonomies\Taxonomy $taxonomy */
    $taxonomy = Taxonomy::make($handle);
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = $taxonomy->termBlueprint();

    expect($blueprint->hasField($richField))->toBeTrue();
})->with([
    'education_types schema_type' => ['education_types', 'schema_type'],
    'education_types same_as' => ['education_types', 'same_as'],
    'affiliations_hospital schema_object' => ['affiliations_hospital', 'schema_object'],
    'residency_years content' => ['residency_years', 'content'],
]);

/*
|--------------------------------------------------------------------------
| Taxonomy term-blueprint import readiness (UAMS-Web/uams-statamic-archive#942–UAMS-Web/uams-statamic-archive#952)
|--------------------------------------------------------------------------
|
| The 10 FaD taxonomy term blueprints normalized in UAMS-Web/uams-statamic-archive#942–UAMS-Web/uams-statamic-archive#952 must each
| resolve their FULL field set (not the title/slug fallback) so the
| wordpress-importer can match imported terms against a complete blueprint.
| A representative rich handle per taxonomy is asserted below — several are
| fields pulled in via `import: uamsweb_schema_basic`, so this also proves the
| imported schema fieldset flattens into the resolved blueprint.
|
| Directories are already plural on `main` (dfde3e9ad), so every handle here
| resolves its rich blueprint today; this locks that in against a future
| singular-directory regression that would silently drop the field set.
*/
it('resolves the full field set for each #942–#952 taxonomy term blueprint', function (string $handle, string $richField) {
    /** @var Statamic\Taxonomies\Taxonomy $taxonomy */
    $taxonomy = Taxonomy::make($handle);
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = $taxonomy->termBlueprint();

    expect($blueprint->hasField($richField))->toBeTrue();
})->with([
    // Label-only WP source; rich fields are Statamic-native editorial enrichment.
    // UAMS-Web/uams-statamic-archive#942 patient_types is no longer a taxonomy: it became a structured collection
    // (UAMS-Web/uams-statamic-archive#2598), covered by PatientTypesCollectionTest.
    '#946 residency_years content' => ['residency_years', 'content'],
    '#950 education_types schema_type' => ['education_types', 'schema_type'],
    // UAMS-Web/uams-statamic-archive#952 recognition_lists is no longer a taxonomy — it became a structured
    // collection (UAMS-Web/uams-statamic-archive#1546), whose `series` / `list_date` fields are covered by
    // RecognitionListsCollectionTest instead.
    // Schema-clone WP sources; targets land on the imported uamsweb_schema_basic.
    '#949 membership_organizations official_website' => ['membership_organizations', 'official_website'],
    '#949 membership_organizations clinical_query' => ['membership_organizations', 'clinical_query'],
    '#948 certification_organizations same_as' => ['certification_organizations', 'same_as'],
    '#945 education_organizations schema_type' => ['education_organizations', 'schema_type'],
    '#945 education_organizations additional_type' => ['education_organizations', 'additional_type'],
    // Rich WP sources; the full tab set must load, incl. deeply-nested fields.
    '#947 certifications organization' => ['certifications', 'organization'],
    '#947 certifications board_certification_query' => ['certifications', 'board_certification_query'],
    // UAMS-Web/uams-statamic-archive#943 clinical_specializations is no longer a taxonomy: it became a structured
    // collection (UAMS-Web/uams-statamic-archive#2596), whose `code` / `isco08_code` fields are covered by
    // ClinicalSpecializationsCollectionTest instead.
    '#944 affiliations_hospital associated_location_profile' => ['affiliations_hospital', 'associated_location_profile'],
]);

it('falls back to a title/slug-only blueprint when no directory matches the handle', function () {
    // Control: a handle with no blueprint directory must resolve the generated
    // fallback (title + slug only). This proves the rich-field assertion above
    // genuinely distinguishes the rich blueprint from the fallback.
    /** @var Statamic\Taxonomies\Taxonomy $taxonomy */
    $taxonomy = Taxonomy::make('nonexistent_taxonomy_xyz');
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = $taxonomy->termBlueprint();

    expect($blueprint->hasField('title'))->toBeTrue()
        ->and($blueprint->hasField('slug'))->toBeTrue()
        ->and($blueprint->hasField('schema_type'))->toBeFalse();
});
