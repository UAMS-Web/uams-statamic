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
 * `knows_about_*` for the subjects a person reaches once `subject_matter` is a
 * collection (UAMS-Web/uams-statamic-archive#2822). Before, each subject was a `subject_matter::slug` term id in
 * `knows_about_terms`, and its `keywords` joined `knows_about_strings`. Now each is
 * an entry id in `knows_about_entries`, directly after the clinical specializations,
 * and the same `keywords` still join `knows_about_strings` in the same order.
 *
 * Person ids are unique per test because the computer memoizes by person id for the
 * life of the process.
 *
 * @command  php artisan test --compact tests/Feature/Support/People/SubjectMatterKnowsAboutTest.php
 */
beforeEach(function (): void {
    foreach (['subject_matter' => 'Subject Matter', 'clinical_specializations' => 'Clinical Specializations', 'clinical_expertise' => 'Clinical Expertise'] as $handle => $title) {
        /** @var Collection $collection */
        $collection = CollectionFacade::make($handle);
        $collection->title($title);
        $collection->sites(['default']);
        $collection->save();
    }

    /** @var Collection $people */
    $people = CollectionFacade::make('people');
    $people->title('People');
    $people->save();

    foreach (['credentials' => 'Credentials', 'specialties_acgme' => 'ACGME Specialties', 'fields_of_study' => 'Fields of Study', 'professional_and_leadership_development_activities' => 'Leadership Activities'] as $handle => $title) {
        /** @var Statamic\Taxonomies\Taxonomy $taxonomy */
        $taxonomy = Taxonomy::make($handle);
        $taxonomy->title($title);
        $taxonomy->save();
    }

    saveKnowsAboutSubject('subj-medicine', 'medicine', ['physic']);
    saveKnowsAboutSubject('subj-surgery', 'surgery', ['operative medicine', 'physic']);
    saveKnowsAboutEntry('spec-thoracic', 'clinical_specializations', ['training_specialty' => ['specialties_acgme::thoracic-surgery']]);
    saveKnowsAboutEntry('aoe-cardio', 'clinical_expertise', []);

    saveKnowsAboutTerm('credentials', 'md', ['subject_matter' => ['subj-medicine']]);
    saveKnowsAboutTerm('specialties_acgme', 'thoracic-surgery', ['subject_matter' => ['subj-surgery', 'subj-medicine']]);
    saveKnowsAboutSubject('subj-ethics', 'ethics', ['bioethics']);
    saveKnowsAboutTerm('fields_of_study', 'philosophy', ['subject_matter' => ['subj-ethics']]);
    saveKnowsAboutTerm('professional_and_leadership_development_activities', 'ethics-course', ['subject_matter' => ['subj-surgery']]);
});

/**
 * Save a subject entry with the given keywords.
 *
 * @param  list<string>  $keywords  Stored `keywords` list
 */
function saveKnowsAboutSubject(string $id, string $title, array $keywords): void
{
    saveKnowsAboutEntry($id, 'subject_matter', ['title' => $title, 'keywords' => $keywords]);
}

/**
 * Save an entry in a collection, published unless the data says otherwise.
 *
 * @param  array<string, mixed>  $data  Stored field values
 */
function saveKnowsAboutEntry(string $id, string $collection, array $data): void
{
    /** @var StatamicEntry $entry */
    $entry = Entry::make();
    $entry->id($id);
    $entry->collection($collection);
    $entry->locale('default');
    $entry->slug($id);
    $published = $data['published'] ?? true;
    unset($data['published']);
    $entry->published($published === true);
    $entry->data(['title' => $id, ...$data]);
    $entry->saveQuietly();
}

/**
 * Save a taxonomy term with the given default-locale data.
 *
 * @param  array<string, mixed>  $data  Stored field values
 */
function saveKnowsAboutTerm(string $taxonomy, string $slug, array $data): void
{
    /** @var Statamic\Taxonomies\Term $term */
    $term = Term::make();
    $term->taxonomy($taxonomy);
    $term->slug($slug);
    $term->dataForLocale($term->defaultLocale(), ['title' => $slug, ...$data]);
    $term->saveQuietly();
}

/**
 * An unsaved person with the given stored fields.
 *
 * @param  array<string, mixed>  $data  Stored person fields
 */
function knowsAboutSubjectPerson(string $id, array $data): StatamicEntry
{
    /** @var StatamicEntry $person */
    $person = Entry::make();
    $person->id($id);
    $person->collection('people');
    $person->slug($id);
    $person->data(['title' => 'Christine Chapel', ...$data]);

    return $person;
}

it('lists a research-interest subject in knows_about_entries and its keywords in knows_about_strings, never in knows_about_terms', function (): void {
    $person = knowsAboutSubjectPerson('zz-subject-chapel', [
        'research_interests' => [
            ['type' => 'controlled_vocabulary', 'subject_matter' => ['subj-surgery']],
            ['type' => 'free_text', 'text' => 'Xenobiology'],
        ],
    ]);
    $computer = app(PersonKnowsAboutComputer::class);

    expect($computer->computeEntries($person))->toBe(['subj-surgery'])
        // Free text first, then the subject's keywords, deduped in first-seen order
        ->and($computer->computeStrings($person))->toBe(['Xenobiology', 'operative medicine', 'physic'])
        ->and($computer->computeTerms($person))->toBe([]);
});

it('collects subjects reached through credentials and specialties, after the specializations and before other entries', function (): void {
    $person = knowsAboutSubjectPerson('zz-subject-mccoy', [
        'credentials' => ['md'],
        'clinical_specializations' => ['spec-thoracic'],
        'expertise' => ['aoe-cardio'],
    ]);
    $computer = app(PersonKnowsAboutComputer::class);

    // The credential reaches medicine; the specialization's training specialty reaches surgery
    // and medicine again, which is kept once. Expertise follows the subjects.
    expect($computer->computeEntries($person))->toBe(['spec-thoracic', 'subj-medicine', 'subj-surgery', 'aoe-cardio'])
        // The specialty term stays a term; no subject id or subject term id appears there
        ->and($computer->computeTerms($person))->toBe(['specialties_acgme::thoracic-surgery'])
        ->and($computer->computeStrings($person))->toBe(['physic', 'operative medicine']);
});

it('drops a stored subject reference that is not a subject entry', function (): void {
    $person = knowsAboutSubjectPerson('zz-subject-rand', [
        'research_interests' => [
            // A legacy term id, an entry in another collection, a missing id, then a real subject
            ['type' => 'controlled_vocabulary', 'subject_matter' => ['subject_matter::medicine', 'aoe-cardio', 'zz-missing', 'subj-medicine']],
        ],
    ]);
    $computer = app(PersonKnowsAboutComputer::class);

    expect($computer->computeEntries($person))->toBe(['subj-medicine'])
        ->and($computer->computeStrings($person))->toBe(['physic'])
        ->and($computer->computeTerms($person))->toBe([]);
});

it('collects subjects reached through a field of study and a leadership activity', function (): void {
    $person = knowsAboutSubjectPerson('zz-subject-spock', [
        'education' => [['type' => 'post_secondary', 'specialty' => ['philosophy']]],
        'professional_and_leadership_development_activities' => [['activity' => ['ethics-course']]],
    ]);
    $computer = app(PersonKnowsAboutComputer::class);

    // The field of study stays a term; both its subject and the activity's are entries
    expect($computer->computeEntries($person))->toBe(['subj-ethics', 'subj-surgery'])
        ->and($computer->computeTerms($person))->toBe(['fields_of_study::philosophy'])
        ->and($computer->computeStrings($person))->toBe(['bioethics', 'operative medicine', 'physic']);
});

it('skips a draft subject in both entries and strings', function (): void {
    saveKnowsAboutEntry('subj-draft', 'subject_matter', ['title' => 'draft subject', 'keywords' => ['unreleased'], 'published' => false]);

    $person = knowsAboutSubjectPerson('zz-subject-draft-reader', [
        'research_interests' => [['type' => 'controlled_vocabulary', 'subject_matter' => ['subj-draft', 'subj-medicine']]],
    ]);
    $computer = app(PersonKnowsAboutComputer::class);

    expect($computer->computeEntries($person))->toBe(['subj-medicine'])
        ->and($computer->computeStrings($person))->toBe(['physic']);
});

it('reads the keywords of a clinical specialization a specialty points at, as it does a subject\'s (#2823)', function (): void {
    // UAMS-Web/uams-statamic-archive#2823 moved orthopaedic-surgery, with its keywords, to a clinical specialization
    saveKnowsAboutEntry('spec-ortho', 'clinical_specializations', ['title' => 'Orthopaedic Surgery Physician', 'keywords' => ['arthroplasty', 'physic']]);
    saveKnowsAboutTerm('specialties_acgme', 'orthopaedic-surgery', ['subject_matter' => ['spec-ortho']]);
    saveKnowsAboutEntry('spec-ortho-training', 'clinical_specializations', ['training_specialty' => ['specialties_acgme::orthopaedic-surgery']]);

    $person = knowsAboutSubjectPerson('zz-subject-crusher', [
        'clinical_specializations' => ['spec-ortho-training'],
        'research_interests' => [['type' => 'controlled_vocabulary', 'subject_matter' => ['subj-medicine']]],
    ]);
    $computer = app(PersonKnowsAboutComputer::class);

    // The specialty's clinical specialization is listed like a subject, and its keywords
    // join the strings with the subject's, deduped in first-seen order
    expect($computer->computeEntries($person))->toContain('spec-ortho')
        ->and($computer->computeStrings($person))->toEqualCanonicalizing(['physic', 'arthroplasty']);
});
