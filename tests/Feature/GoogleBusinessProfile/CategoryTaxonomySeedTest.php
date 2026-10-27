<?php

declare(strict_types=1);

use App\Services\GoogleBusinessProfile\Categories\CategoryList;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Term as TaxonomyTerm;
use Tests\Concerns\RequiresContentSubmodule;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(TestCase::class, RequiresContentSubmodule::class);

/**
 * Coverage for UAMS-Web/uams-statamic-archive#2614: the `google_business_profile_categories` taxonomy is seeded with the
 * curated editor list, keyed by Google's category id (`gcid`), the key UAMS-Web/uams-statamic-archive#2091 chose because
 * Google renames categories and a slug is editable. Two terms carrying one GCID would split
 * one Google category in two, and the category check (UAMS-Web/uams-statamic-archive#2617) would read only one of them.
 *
 * These read the disk-loaded content; nothing here saves a term.
 *
 * @command php artisan test --compact tests/Feature/GoogleBusinessProfile/CategoryTaxonomySeedTest.php
 */

/** The taxonomy the seed fills. */
const GBP_CATEGORY_TAXONOMY = 'google_business_profile_categories';

/**
 * Each category term in its default locale, keyed by term id.
 *
 * Reads the base terms, not one localization per site: the taxonomy is enabled on two sites,
 * and a per-site read would list every term twice.
 *
 * @return array<string, LocalizedTerm>
 */
function gbpCategoryTermsById(): array
{
    if (Taxonomy::findByHandle(GBP_CATEGORY_TAXONOMY) === null) {
        ContentSubmodule::reportMissing('taxonomy '.GBP_CATEGORY_TAXONOMY);
    }

    $terms = [];

    foreach (Term::whereTaxonomy(GBP_CATEGORY_TAXONOMY) as $localized) {
        $term = $localized instanceof LocalizedTerm ? $localized->term() : null;
        $default = $term instanceof TaxonomyTerm ? $term->inDefaultLocale() : null;

        if ($term instanceof TaxonomyTerm && $default instanceof LocalizedTerm) {
            $terms[mustBeString($term->id(), 'the category term id')] = $default;
        }
    }

    return $terms;
}

/**
 * The GCIDs that appear on more than one term, each with the ids of the terms carrying it.
 *
 * @param  array<string, mixed>  $gcidById
 * @return array<string, list<string>>
 */
function gbpGcidDuplicates(array $gcidById): array
{
    $idsByGcid = [];

    foreach ($gcidById as $id => $gcid) {
        $idsByGcid[is_string($gcid) ? $gcid : ''][] = $id;
    }

    return array_filter($idsByGcid, fn (array $ids): bool => count($ids) > 1);
}

it('gives no GCID to two category terms', function (): void {
    $gcidById = array_map(fn (LocalizedTerm $term): mixed => $term->get('gcid'), gbpCategoryTermsById());

    // The seed's 250 terms. A read that found none would pass the uniqueness check vacuously.
    expect(count($gcidById))->toBeGreaterThanOrEqual(250);
    expect(gbpGcidDuplicates($gcidById))->toBe([]);
});

it('reports a GCID shared by two terms', function (): void {
    $planted = [
        'google_business_profile_categories::allergist' => 'allergist',
        'google_business_profile_categories::allergist-2' => 'allergist',
        'google_business_profile_categories::audiologist' => 'audiologist',
    ];

    expect(gbpGcidDuplicates($planted))->toBe([
        'allergist' => ['google_business_profile_categories::allergist', 'google_business_profile_categories::allergist-2'],
    ]);
});

it('gives every category term a GCID and a curated scope', function (): void {
    $problems = [];

    foreach (gbpCategoryTermsById() as $id => $term) {
        $gcid = $term->get('gcid');

        if (! is_string($gcid) || preg_match(CategoryList::GCID_PATTERN, $gcid) !== 1) {
            $problems[$id] = 'gcid';
        } elseif (! in_array($term->get('collections'), [['locations'], ['people', 'locations']], true)) {
            $problems[$id] = 'collections';
        }
    }

    expect($problems)->toBe([]);
});
