<?php

declare(strict_types=1);

use App\Support\People\PersonKnowsAboutComputer;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Tests\Support\StacheIsolatedTestCase;

uses(StacheIsolatedTestCase::class);

/**
 * `knows_about_*` for a provider's clinical specializations once they are
 * `clinical_specializations` collection entries (UAMS-Web/uams-statamic-archive#2596). Before, each specialization
 * was a term, emitted into `knows_about_terms` with its `training_specialty` terms
 * expanded after it. Now the specialization is an entry, listed in
 * `knows_about_entries`, and its `training_specialty` terms still join
 * `knows_about_terms`, so the medical training specialties a provider knows about
 * are unchanged.
 *
 * Person ids are unique per test because the computer memoizes by person id for the
 * life of the process.
 *
 * @command  php artisan test --compact tests/Feature/Support/People/ClinicalSpecializationKnowsAboutTest.php
 */
beforeEach(function (): void {
    /** @var Collection $specializations */
    $specializations = CollectionFacade::make('clinical_specializations');
    $specializations->title('Clinical Specializations');
    $specializations->sites(['default']);
    $specializations->structureContents(['root' => false]);
    $specializations->save();

    /** @var Collection $people */
    $people = CollectionFacade::make('people');
    $people->title('People');
    $people->save();

    /** @var Statamic\Taxonomies\Taxonomy $acgme */
    $acgme = Taxonomy::make('specialties_acgme');
    $acgme->title('ACGME Specialties');
    $acgme->save();

    /** @var Statamic\Taxonomies\Term $term */
    $term = Term::make();
    $term->taxonomy('specialties_acgme');
    $term->slug('thoracic-surgery');
    $term->dataForLocale($term->defaultLocale(), ['title' => 'Thoracic Surgery']);
    $term->saveQuietly();
});

/**
 * Save a specialization entry naming the given training specialty terms.
 *
 * @param  list<string>  $trainingSpecialties  `taxonomy::slug` term ids
 */
function saveKnowsAboutSpecialization(string $id, array $trainingSpecialties): void
{
    /** @var StatamicEntry $entry */
    $entry = Entry::make();
    $entry->id($id);
    $entry->collection('clinical_specializations');
    $entry->locale('default');
    $entry->slug($id);
    $entry->published(true);
    $entry->data(['title' => $id, 'training_specialty' => $trainingSpecialties]);
    $entry->saveQuietly();
}

/**
 * An unsaved person with the given stored specializations.
 *
 * @param  list<string>  $specializations  Stored entry ids
 */
function knowsAboutSpecializationPerson(string $id, array $specializations): StatamicEntry
{
    /** @var StatamicEntry $person */
    $person = Entry::make();
    $person->id($id);
    $person->collection('people');
    $person->slug($id);
    $person->data(['title' => 'Leonard McCoy', 'clinical_specializations' => $specializations]);

    return $person;
}

it('lists the specialization in knows_about_entries and expands its training specialty into knows_about_terms', function (): void {
    saveKnowsAboutSpecialization('spec-thoracic', ['specialties_acgme::thoracic-surgery']);

    $person = knowsAboutSpecializationPerson('zz-knows-about-mccoy', ['spec-thoracic']);
    $computer = app(PersonKnowsAboutComputer::class);

    expect($computer->computeEntries($person))->toBe(['spec-thoracic'])
        ->and($computer->computeTerms($person))->toContain('specialties_acgme::thoracic-surgery')
        // The specialization itself is an entry now, never a term id.
        ->and(array_filter($computer->computeTerms($person), fn (string $term): bool => str_starts_with($term, 'clinical_specializations::')))->toBe([]);
});

it('ignores an id that is not a clinical specialization, in both lists', function (): void {
    /** @var StatamicEntry $other */
    $other = Entry::make();
    $other->id('zz-not-a-specialization');
    $other->collection('people');
    $other->slug('zz-not-a-specialization');
    $other->data(['title' => 'Nyota Uhura', 'training_specialty' => ['specialties_acgme::thoracic-surgery']]);
    $other->saveQuietly();

    $person = knowsAboutSpecializationPerson('zz-knows-about-chekov', ['zz-not-a-specialization', 'clinical_specializations::spec-thoracic']);
    $computer = app(PersonKnowsAboutComputer::class);

    expect($computer->computeEntries($person))->toBe([])
        ->and($computer->computeTerms($person))->not->toContain('specialties_acgme::thoracic-surgery');
});
