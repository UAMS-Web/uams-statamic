<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Entry;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;
use Statamic\Fields\Validator;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Term as TaxonomyTerm;
use Tests\Concerns\RequiresContentSubmodule;
use Tests\TestCase;

uses(TestCase::class, RequiresContentSubmodule::class);

/**
 * Coverage for the `subject_matter` backfill on five taxonomies: UAMS-Web/uams-statamic-archive#2808, UAMS-Web/uams-statamic-archive#2830 and UAMS-Web/uams-statamic-archive#3055
 * for `credentials`, UAMS-Web/uams-statamic-archive#3057 for `research_specialties_acgme`, UAMS-Web/uams-statamic-archive#3058 for
 * `specialties_non_accredited`, UAMS-Web/uams-statamic-archive#3060 for `specialties_aoa` and UAMS-Web/uams-statamic-archive#3061 for
 * `specialties_acgme`. Each term blueprint marks `subject_matter` as required, and the
 * value is a list of entry ids, so a value can be present and still point at nothing.
 *
 * A value resolves when it names an existing entry in a collection the term's own
 * blueprint field offers: the three specialty taxonomies offer only
 * `clinical_specializations`, while `credentials` and `research_specialties_acgme` also
 * offer `subject_matter`. The collections are read from the blueprint, so a value an
 * editor could not have picked fails here too.
 *
 * Every credential carries a value. The last thirteen were written by UAMS-Web/uams-statamic-archive#3055, with the
 * subjects UAMS-Web/uams-statamic-archive#2824 decided for them, and are pinned below by subject title, so a value
 * moved to a different subject fails as well as one removed.
 *
 * UAMS-Web/uams-statamic-archive#3062 writes the 456 `fields_of_study` terms, which offer both collections, with the
 * values UAMS-Web/uams-statamic-archive#2825 approved. Those are pinned in `tests/Fixtures/fields_of_study_subject_matter.json`,
 * each value named by its subject's title or its specialization's NUCC code, so a value
 * moved to another entry fails as well as one removed.
 *
 * `training_specialty` on a clinical specialization is the reverse link to a specialty
 * term. UAMS-Web/uams-statamic-archive#3060 and UAMS-Web/uams-statamic-archive#3061 chose not to write it in this backfill: the term's
 * `subject_matter` is the record. The last tests keep any value someone does write
 * consistent with it, and show the check reports one that is not.
 *
 * @command  php artisan test --compact tests/Feature/TermSubjectMatterValuesTest.php
 */

// cspell:ignore dhce mhsa msha

/**
 * The subject title UAMS-Web/uams-statamic-archive#2824 decided for each credential UAMS-Web/uams-statamic-archive#3055 writes, keyed by term slug.
 */
const TERM_SUBJECT_MATTER_DECIDED_SUBJECTS = [
    'bba' => 'business',
    'cpa' => 'business',
    'dbe' => 'bioethics',
    'dhce' => 'bioethics',
    'dpp' => 'professional practice',
    'j-d' => 'law',
    'll-d' => 'law',
    'mba' => 'business',
    'mha' => 'health administration',
    'mhsa' => 'health administration',
    'mpa' => 'public administration',
    'msha' => 'health administration',
    'pmp' => 'business',
];

/**
 * The entry ids in each collection on `default`, keyed by collection handle.
 *
 * @param  list<string>  $collections
 * @return array<string, list<string>>
 */
function termSubjectMatterEntryIds(array $collections): array
{
    $ids = [];

    foreach ($collections as $collection) {
        /** @var EntryQueryBuilder $query */
        $query = Entry::query();
        $query->where('collection', $collection);

        /** @var Collection<int, StatamicEntry> $entries */
        $entries = $query->get();

        $ids[$collection] = array_values(array_map(static fn (StatamicEntry $entry): string => mustBeString($entry->id(), 'an entry id'), $entries->all()));
    }

    return $ids;
}

/**
 * The single blueprint a taxonomy's terms use.
 */
function termSubjectMatterBlueprint(string $taxonomy): Blueprint
{
    $blueprints = mustBe(Collection::class, mustBe(Statamic\Taxonomies\Taxonomy::class, Taxonomy::findByHandle($taxonomy), "the {$taxonomy} taxonomy")->termBlueprints(), "the {$taxonomy} blueprints");

    expect($blueprints)->toHaveCount(1);

    return mustBe(Blueprint::class, $blueprints->first(), "the {$taxonomy} blueprint");
}

/**
 * The collections a taxonomy's `subject_matter` field offers.
 *
 * @return list<string>
 */
function termSubjectMatterCollections(string $taxonomy): array
{
    $field = mustBe(Field::class, termSubjectMatterBlueprint($taxonomy)->field('subject_matter'), "the {$taxonomy} subject_matter field");

    /** @var list<string> $collections */
    $collections = mustBeArray($field->get('collections'), 'the offered collections');

    return $collections;
}

/**
 * Each term's problem with its `subject_matter` value, as "slug: reason", given the ids
 * the field may name.
 *
 * @param  iterable<LocalizedTerm>  $terms
 * @param  array<string, list<string>>  $offered
 * @return list<string>
 */
function termSubjectMatterProblems(iterable $terms, array $offered): array
{
    $resolvable = array_flip(array_merge(...array_values($offered)));
    $problems = [];

    foreach ($terms as $term) {
        $slug = mustBeString($term->slug(), 'a term slug');
        $values = $term->get('subject_matter');

        if (! is_array($values) || $values === []) {
            $problems[] = "{$slug}: no value";

            continue;
        }

        foreach ($values as $value) {
            if (! is_string($value) || ! isset($resolvable[$value])) {
                $problems[] = "{$slug}: ".(is_string($value) ? $value : get_debug_type($value)).' resolves to no entry in '.implode(' or ', array_keys($offered));
            }
        }
    }

    return $problems;
}

/**
 * The terms of a taxonomy on `default`.
 *
 * @return list<LocalizedTerm>
 */
function termSubjectMatterTerms(string $taxonomy): array
{
    $query = Term::query();
    $query->where('taxonomy', $taxonomy);
    $query->where('site', 'default');

    /** @var Collection<int, LocalizedTerm> $terms */
    $terms = $query->get();

    return array_values($terms->all());
}

/**
 * The errors the blueprint's own `subject_matter` field reports for a term's value.
 *
 * Only that field is validated: it is the one this backfill writes, and stored values
 * of other fields (a `terms` field holds a bare slug on disk) are not the shape the
 * Control Panel submits, so validating them raw reports errors no editor sees.
 *
 * @return list<string>
 */
function termSubjectMatterErrors(string $taxonomy, LocalizedTerm $term): array
{
    $field = mustBe(Field::class, termSubjectMatterBlueprint($taxonomy)->field('subject_matter'), "the {$taxonomy} subject_matter field");

    try {
        $fields = mustBe(Fields::class, (new Fields([['handle' => 'subject_matter', 'field' => $field->config()]]))->addValues(['subject_matter' => $term->get('subject_matter')]), 'the fields');
        mustBe(Validator::class, $fields->validator(), 'the validator')->validate();
    } catch (ValidationException $validationException) {
        $keys = array_keys($validationException->errors());
        sort($keys);

        return $keys;
    }

    return [];
}

it('gives every specialty term a subject_matter value that resolves to an offered entry', function (string $taxonomy, int $count): void {
    $this->requireContentCollection('clinical_specializations');

    $terms = termSubjectMatterTerms($taxonomy);
    $offered = termSubjectMatterEntryIds(termSubjectMatterCollections($taxonomy));

    // The term count pins the corpus, so a taxonomy that failed to load is not a pass
    expect($terms)->toHaveCount($count)
        ->and(termSubjectMatterProblems($terms, $offered))->toBe([]);
})->with([
    'research_specialties_acgme (#3057)' => ['research_specialties_acgme', 2],
    'specialties_non_accredited (#3058)' => ['specialties_non_accredited', 3],
    'specialties_aoa (#3060)' => ['specialties_aoa', 78],
    'specialties_acgme (#3061)' => ['specialties_acgme', 146],
]);

it('gives every credential a subject_matter value that resolves to an offered entry', function (): void {
    $this->requireContentCollection('subject_matter');
    $this->requireContentCollection('clinical_specializations');

    $terms = termSubjectMatterTerms('credentials');

    expect($terms)->toHaveCount(121)
        ->and(termSubjectMatterProblems($terms, termSubjectMatterEntryIds(termSubjectMatterCollections('credentials'))))->toBe([]);
});

it('gives the last thirteen credentials the subjects #2824 decided', function (): void {
    $this->requireContentCollection('subject_matter');

    $titles = [];

    foreach (termSubjectMatterTerms('credentials') as $term) {
        $slug = mustBeString($term->slug(), 'a term slug');

        if (! array_key_exists($slug, TERM_SUBJECT_MATTER_DECIDED_SUBJECTS)) {
            continue;
        }

        $titles[$slug] = array_map(
            static fn (mixed $id): string => mustBeString(mustBe(StatamicEntry::class, Entry::find(mustBeString($id, 'a subject_matter id')), "the entry {$slug} names")->get('title'), 'a subject title'),
            mustBeArray($term->get('subject_matter'), "the {$slug} subject_matter value"),
        );
    }

    ksort($titles);

    expect($titles)->toBe(array_map(static fn (string $title): array => [$title], TERM_SUBJECT_MATTER_DECIDED_SUBJECTS));
});

it('gives every field of study a subject_matter value that resolves to an offered entry (#3062)', function (): void {
    $this->requireContentCollection('subject_matter');
    $this->requireContentCollection('clinical_specializations');

    $terms = termSubjectMatterTerms('fields_of_study');

    expect($terms)->toHaveCount(456)
        ->and(termSubjectMatterProblems($terms, termSubjectMatterEntryIds(termSubjectMatterCollections('fields_of_study'))))->toBe([]);
});

it('gives every field of study exactly the values #2825 approved (#3062)', function (): void {
    $this->requireContentCollection('subject_matter');
    $this->requireContentCollection('clinical_specializations');

    /** @var array<string, list<string>> $approved */
    $approved = json_decode(mustBeString(file_get_contents(dirname(__DIR__).'/Fixtures/fields_of_study_subject_matter.json'), 'the approved mapping'), true, flags: JSON_THROW_ON_ERROR);

    // A subject is named by its title, a clinical specialization by its NUCC code
    $label = static function (mixed $id): string {
        $entry = mustBe(StatamicEntry::class, Entry::find(mustBeString($id, 'a subject_matter id')), 'the entry a field of study names');

        return mustBeString($entry->collectionHandle() === 'clinical_specializations' ? $entry->get('code') : $entry->get('title'), 'a subject title or code');
    };

    $actual = [];

    foreach (termSubjectMatterTerms('fields_of_study') as $term) {
        $actual[mustBeString($term->slug(), 'a term slug')] = array_map($label, mustBeArray($term->get('subject_matter'), 'a subject_matter value'));
    }

    ksort($actual);
    ksort($approved);

    expect($approved)->toHaveCount(456)
        ->and($actual)->toBe($approved);
});

it('points the shoulder and elbow term at the Orthopaedic Surgery classification, as #3169 decided', function (): void {
    $this->requireContentCollection('clinical_specializations');

    $term = mustBe(LocalizedTerm::class, Term::find('specialties_non_accredited::shoulder-and-elbow-surgery'), 'the shoulder-and-elbow-surgery term');
    $codes = array_map(
        static fn (mixed $id): mixed => mustBe(StatamicEntry::class, Entry::find(mustBeString($id, 'a subject_matter id')), 'the entry the term names')->get('code'),
        mustBeArray($term->get('subject_matter'), 'the term subject_matter value'),
    );

    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'clinical_specializations');
    $query->where('slug', 'shoulder-and-elbow-surgery');

    expect($codes)->toBe(['207X00000X'])
        ->and($query->count())->toBe(0)
        ->and(termSubjectMatterErrors('specialties_non_accredited', $term))->toBe([]);
});

it('passes the blueprint subject_matter rules on every backfilled term', function (string $taxonomy): void {
    $this->requireContentCollection('clinical_specializations');

    $failing = [];

    foreach (termSubjectMatterTerms($taxonomy) as $term) {
        $errors = termSubjectMatterErrors($taxonomy, $term);

        if ($errors !== []) {
            $failing[] = mustBeString($term->slug(), 'a term slug').': '.implode(', ', $errors);
        }
    }

    expect($failing)->toBe([]);
})->with(['credentials', 'fields_of_study', 'research_specialties_acgme', 'specialties_non_accredited', 'specialties_aoa', 'specialties_acgme']);

it('reports a missing value, an unknown id and an id from a collection the field does not offer', function (): void {
    $this->requireContentCollection('subject_matter');
    $this->requireContentCollection('clinical_specializations');

    $offered = termSubjectMatterEntryIds(termSubjectMatterCollections('specialties_acgme'));
    $subject = termSubjectMatterEntryIds(['subject_matter'])['subject_matter'][0];
    $specialization = $offered['clinical_specializations'][0];

    $planted = static function (string $slug, mixed $value): LocalizedTerm {
        $term = mustBe(TaxonomyTerm::class, Term::make(), 'a new term');
        $term->taxonomy('specialties_acgme');
        $term->slug($slug);
        $term->set('title', $slug);
        $term->set('subject_matter', $value);

        return mustBe(LocalizedTerm::class, $term->in('default'), 'a planted term');
    };

    // Controls: each shape of bad value is reported, and a good one is not
    expect(termSubjectMatterProblems([$planted('zz-none', null)], $offered))->toBe(['zz-none: no value'])
        ->and(termSubjectMatterProblems([$planted('zz-empty', [])], $offered))->toBe(['zz-empty: no value'])
        ->and(termSubjectMatterProblems([$planted('zz-unknown', ['00000000-0000-0000-0000-000000000000'])], $offered))->toHaveCount(1)
        ->and(termSubjectMatterProblems([$planted('zz-subject', [$subject])], $offered))->toHaveCount(1)
        ->and(termSubjectMatterProblems([$planted('zz-good', [$specialization])], $offered))->toBe([])
        ->and(termSubjectMatterErrors('specialties_acgme', $planted('zz-none', null)))->toBe(['subject_matter'])
        ->and(termSubjectMatterErrors('specialties_acgme', $planted('zz-good', [$specialization])))->toBe([]);
});

/**
 * Each specialization's `training_specialty` reference that does not come back to it, as
 * "slug: reason": the term it names must list the specialization in `subject_matter`.
 *
 * @param  iterable<StatamicEntry>  $entries
 * @return list<string>
 */
function termSubjectMatterTrainingMismatches(iterable $entries): array
{
    $mismatches = [];

    foreach ($entries as $entry) {
        $slug = mustBeString($entry->slug(), 'a specialization slug');

        foreach ((array) $entry->get('training_specialty') as $reference) {
            if (! is_string($reference) || ! str_contains($reference, '::')) {
                $mismatches[] = "{$slug}: an unreadable reference";

                continue;
            }

            $term = Term::find($reference);
            $values = $term instanceof LocalizedTerm ? $term->get('subject_matter') : null;

            if (! is_array($values) || ! in_array($entry->id(), $values, true)) {
                $mismatches[] = "{$slug}: {$reference} does not name this entry in its subject_matter";
            }
        }
    }

    return $mismatches;
}

it('keeps any training_specialty a clinical specialization carries consistent with that term', function (): void {
    $this->requireContentCollection('clinical_specializations');

    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'clinical_specializations');

    /** @var Collection<int, StatamicEntry> $entries */
    $entries = $query->get();

    expect($entries)->not->toBeEmpty()
        ->and(termSubjectMatterTrainingMismatches($entries))->toBe([]);
});

it('reports a training_specialty that its term does not name back, and accepts one it does', function (): void {
    $this->requireContentCollection('clinical_specializations');

    $term = mustBe(LocalizedTerm::class, Term::find('specialties_acgme::orthopaedic-surgery'), 'specialties_acgme::orthopaedic-surgery');
    $named = mustBeString(mustBeArray($term->get('subject_matter'), 'the term value')[0], 'a named id');

    $planted = static function (string $id, mixed $reference): StatamicEntry {
        $entry = mustBe(StatamicEntry::class, Entry::make(), 'a new entry');
        $entry->collection('clinical_specializations');
        $entry->id($id);
        $entry->slug('zz-'.$id);
        $entry->set('training_specialty', $reference);

        return $entry;
    };

    // Controls: a reference the term does not name back, an unknown term and an unreadable
    // value are each reported; the term's own specialization is not
    expect(termSubjectMatterTrainingMismatches([$planted('00000000-0000-4000-8000-000000000000', ['specialties_acgme::orthopaedic-surgery'])]))->toHaveCount(1)
        ->and(termSubjectMatterTrainingMismatches([$planted('00000000-0000-4000-8000-000000000001', ['specialties_acgme::zz-no-such-term'])]))->toHaveCount(1)
        ->and(termSubjectMatterTrainingMismatches([$planted('00000000-0000-4000-8000-000000000002', ['orthopaedic-surgery'])]))->toBe(['zz-00000000-0000-4000-8000-000000000002: an unreadable reference'])
        ->and(termSubjectMatterTrainingMismatches([$planted($named, ['specialties_acgme::orthopaedic-surgery'])]))->toBe([]);
});
