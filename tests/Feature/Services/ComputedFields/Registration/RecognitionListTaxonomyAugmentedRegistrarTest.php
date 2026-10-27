<?php

declare(strict_types=1);

use App\Support\Statamic\Taxonomies\AugmentedRecognitionListsSeriesTerm;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Taxonomies\AugmentedTerm;
use Statamic\Taxonomies\LocalizedTerm;
use Tests\Support\StacheIsolatedTestCase;

uses(StacheIsolatedTestCase::class);

/**
 * `RecognitionListTaxonomyAugmentedRegistrar` hooks term augmentation so a
 * `recognition_lists_series` term augments through `AugmentedRecognitionListsSeriesTerm`,
 * and every other taxonomy's terms keep Statamic's own (#255 coverage batch 12).
 *
 * The registrar runs when the app boots, so these tests augment real terms on a throwaway
 * Stache.
 *
 * Mutation: 3 of 3 killed, no annotated equivalents (UAMS-Web/uams-statamic-archive#3023), run serial, `--covered-only`,
 * and scoped with `--path` and `--class`.
 *
 * @covers \App\Services\ComputedFields\Registration\RecognitionListTaxonomyAugmentedRegistrar
 *
 * @command  php artisan test --compact tests/Feature/Services/ComputedFields/Registration/RecognitionListTaxonomyAugmentedRegistrarTest.php
 */

/**
 * Save a taxonomy and one term in it on the default site, and return the localized term.
 */
function recognitionListAugmentedTerm(string $taxonomy, string $slug, string $title): LocalizedTerm
{
    $collection = mustBe(Statamic\Taxonomies\Taxonomy::class, Taxonomy::make($taxonomy), 'the taxonomy');
    $collection->sites(['default']);
    $collection->save();

    $term = mustBe(Statamic\Taxonomies\Term::class, Term::make($slug), 'the new term');
    $term->taxonomy($taxonomy);
    $term->dataForLocale('default', ['title' => $title]);
    $term->save();

    // find() answers with the term localized to the default site
    return mustBe(LocalizedTerm::class, Term::find("{$taxonomy}::{$slug}"), "the {$taxonomy} term");
}

it('augments a recognition list series term through the series wrapper, for that term', function (): void {
    $term = recognitionListAugmentedTerm('recognition_lists_series', 'deans-list', "Dean's List");

    $augmented = $term->augmented();

    expect($augmented)->toBeInstanceOf(AugmentedRecognitionListsSeriesTerm::class)
        ->and($augmented->get('title')->value())->toBe("Dean's List")
        ->and($augmented->get('slug')->value())->toBe('deans-list');
});

it('leaves every other taxonomy\'s terms on Statamic\'s augmentation', function (string $taxonomy): void {
    $term = recognitionListAugmentedTerm($taxonomy, 'plain-term', 'Plain Term');

    $augmented = $term->augmented();

    expect($augmented::class)->toBe(AugmentedTerm::class)
        ->and($augmented->get('title')->value())->toBe('Plain Term');
})->with([
    'a recognition list taxonomy that is not the series one' => ['recognition_lists'],
    'an unrelated taxonomy' => ['topics_probe'],
]);
