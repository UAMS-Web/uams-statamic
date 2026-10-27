<?php

declare(strict_types=1);

use App\Support\People\PersonKnowsAboutComputer;
use Statamic\Contracts\Taxonomies\Term as TermContract;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Fields\Value;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Term as TaxonomyTerm;
use Tests\Support\StacheIsolatedTestCase;

uses(StacheIsolatedTestCase::class);

/**
 * The knows-about graph PersonKnowsAboutComputer walks from a person: credentials,
 * education, certifications, leadership activities and research interests seed term
 * ids, each term expands by its taxonomy, and every published subject reached along
 * the way joins knows_about_entries and lends its keywords to knows_about_strings.
 *
 * Person ids are unique per test because the computer memoizes by person id for the
 * life of the request.
 *
 * @command  php artisan test --compact tests/Feature/Support/People/PersonKnowsAboutComputerGraphTest.php
 */
beforeEach(function (): void {
    foreach (['people', 'subject_matter', 'clinical_specializations', 'clinical_expertise', 'clinical_conditions', 'clinical_services'] as $handle) {
        /** @var Collection $collection */
        $collection = CollectionFacade::make($handle);
        $collection->title($handle);
        $collection->save();
    }

    foreach (['credentials', 'certifications', 'fields_of_study', 'specialties_acgme', 'research_specialties_acgme', 'other_specialties', 'professional_and_leadership_development_activities', 'audiences'] as $handle) {
        /** @var Statamic\Taxonomies\Taxonomy $taxonomy */
        $taxonomy = Taxonomy::make($handle);
        $taxonomy->title($handle);
        $taxonomy->save();
    }

    knowsAboutGraphTerm('credentials', 'md', ['subject_matter' => ['subj-credential'], 'certifications_term' => ['board']]);
    knowsAboutGraphTerm('credentials', 'phd', ['subject_matter' => ['subj-phd']]);
    knowsAboutGraphTerm('certifications', 'board', [
        'medical_specialty' => ['specialties_acgme::surgery'],
        'non_medical_research_specialty' => ['research_specialties_acgme::genomics'],
        'other_specialty' => ['other_specialties::wellness'],
    ]);
    knowsAboutGraphTerm('specialties_acgme', 'surgery', ['subject_matter' => ['subj-surgery']]);
    knowsAboutGraphTerm('research_specialties_acgme', 'genomics', ['subject_matter' => ['subj-genomics']]);
    knowsAboutGraphTerm('other_specialties', 'wellness', ['subject_matter' => ['subj-wellness']]);
    knowsAboutGraphTerm('fields_of_study', 'biology', ['subject_matter' => ['subj-biology']]);
    knowsAboutGraphTerm('professional_and_leadership_development_activities', 'lead', ['subject_matter' => ['subj-lead']]);
    knowsAboutGraphTerm('audiences', 'patients', ['subject_matter' => ['subj-ignored']]);
    knowsAboutGraphTerm('fields_of_study', 'anatomy', ['subject_matter' => ['subj-biology', 'subj-lead']]);

    foreach (['subj-credential', 'subj-surgery', 'subj-genomics', 'subj-wellness', 'subj-biology', 'subj-lead', 'subj-interest', 'subj-ignored', 'subj-phd'] as $id) {
        knowsAboutGraphEntry($id, 'subject_matter', ['keywords' => [['value' => ' shared '], $id.'-keyword']]);
    }

    knowsAboutGraphEntry('subj-draft', 'subject_matter', ['keywords' => ['draft-keyword']], published: false);
    knowsAboutGraphEntry('exp-1', 'clinical_expertise', []);
    knowsAboutGraphEntry('spec-1', 'clinical_specializations', ['keywords' => ['spec-keyword']]);
    knowsAboutGraphEntry('cond-1', 'clinical_conditions', []);
    knowsAboutGraphEntry('svc-1', 'clinical_services', []);
});

/**
 * Save a term with the given data in its default locale.
 *
 * @param  array<string, mixed>  $data
 */
function knowsAboutGraphTerm(string $taxonomy, string $slug, array $data): void
{
    /** @var TaxonomyTerm $term */
    $term = Term::make();
    $term->taxonomy($taxonomy);
    $term->slug($slug);
    $term->dataForLocale($term->defaultLocale(), ['title' => $slug, ...$data]);
    $term->saveQuietly();
}

/**
 * Save an entry in the given collection.
 *
 * @param  array<string, mixed>  $data
 */
function knowsAboutGraphEntry(string $id, string $collection, array $data, bool $published = true): void
{
    /** @var StatamicEntry $entry */
    $entry = Entry::make();
    $entry->id($id);
    $entry->collection($collection);
    $entry->locale('default');
    $entry->slug($id);
    $entry->published($published);
    $entry->data(['title' => $id, ...$data]);
    $entry->saveQuietly();
}

/**
 * An unsaved person with the given data.
 *
 * @param  array<string, mixed>  $data
 */
function knowsAboutGraphPerson(string $id, array $data): StatamicEntry
{
    /** @var StatamicEntry $person */
    $person = Entry::make();
    $person->id($id);
    $person->collection('people');
    $person->slug($id);
    $person->data(['title' => 'Beverly Crusher', ...$data]);

    return $person;
}

it('walks every seed and expands each term by its taxonomy', function (): void {
    $person = knowsAboutGraphPerson('zz-graph-full', [
        'credentials' => ['md', 'nope'],
        'education' => [
            ['type' => 'secondary', 'activity' => 'ignored', 'specialty' => 'ignored'],
            ['type' => 'post_secondary', 'activity' => 'phd', 'specialty' => 'biology'],
            'not a row',
        ],
        'certifications' => new Value([['activity' => 'board']]),
        'professional_and_leadership_development_activities' => collect([['activity' => 'lead']]),
        'research_interests' => new Value([
            ['type' => 'free_text', 'text' => ' Robotic surgery '],
            ['type' => 'free_text', 'text' => 'Robotic surgery'],
            ['type' => 'free_text', 'text' => '   '],
            ['type' => 'free_text', 'text' => ['not text']],
            ['type' => 'controlled_vocabulary', 'subject_matter' => ['subj-interest', 'subj-draft', 'cond-1', 'subj-interest']],
            ['type' => 'other', 'text' => 'ignored'],
            ['type' => 'free_text', 'text' => 'Lasers'],
        ]),
        'expertise' => ['nope', 'cond-1', 'exp-1'],
        'conditions' => ['cond-1'],
        'treatments' => ['svc-1', 'svc-1'],
    ]);
    $computer = app(PersonKnowsAboutComputer::class);

    expect($computer->computeTerms($person))->toBe([
        'fields_of_study::biology',
        'specialties_acgme::surgery',
        'research_specialties_acgme::genomics',
        'other_specialties::wellness',
    ])->and($computer->computeEntries($person))->toBe([
        'subj-interest',
        'subj-credential',
        'subj-phd',
        'subj-biology',
        'subj-lead',
        'subj-surgery',
        'subj-genomics',
        'subj-wellness',
        'exp-1',
        'cond-1',
        'svc-1',
    ])->and($computer->computeStrings($person))->toBe([
        'Robotic surgery',
        'Lasers',
        'shared',
        'subj-interest-keyword',
        'subj-credential-keyword',
        'subj-phd-keyword',
        'subj-biology-keyword',
        'subj-lead-keyword',
        'subj-surgery-keyword',
        'subj-genomics-keyword',
        'subj-wellness-keyword',
    ]);
});

it('expands a term once even when several seeds reach it', function (): void {
    $person = knowsAboutGraphPerson('zz-graph-repeat', [
        'credentials' => ['md'],
        'certifications' => [['activity' => 'board'], ['activity' => 'certifications::board']],
        'education' => [['type' => 'post_secondary', 'specialty' => ['biology', 'fields_of_study::biology']]],
    ]);

    expect(app(PersonKnowsAboutComputer::class)->computeTerms($person))->toBe([
        'fields_of_study::biology',
        'specialties_acgme::surgery',
        'research_specialties_acgme::genomics',
        'other_specialties::wellness',
    ]);
});

it('ignores terms in a taxonomy it does not expand, and terms that do not exist', function (): void {
    $person = knowsAboutGraphPerson('zz-graph-ignored', [
        'certifications' => [['activity' => ['audiences::patients', 'certifications::missing']]],
    ]);
    $computer = app(PersonKnowsAboutComputer::class);

    expect($computer->computeTerms($person))->toBe([])
        ->and($computer->computeEntries($person))->toBe([])
        ->and($computer->computeStrings($person))->toBe([]);
});

it('returns empty lists for a person with nothing stored', function (): void {
    $person = knowsAboutGraphPerson('zz-graph-empty', ['education' => 'not rows', 'research_interests' => null]);
    $computer = app(PersonKnowsAboutComputer::class);

    expect($computer->computeTerms($person))->toBe([])
        ->and($computer->computeEntries($person))->toBe([])
        ->and($computer->computeStrings($person))->toBe([]);
});

it('remembers the computed lists for a person id within the request', function (): void {
    $computer = app(PersonKnowsAboutComputer::class);
    $first = knowsAboutGraphPerson('zz-graph-memo', ['research_interests' => [['type' => 'free_text', 'text' => 'Once']]]);
    $second = knowsAboutGraphPerson('zz-graph-memo', ['research_interests' => [['type' => 'free_text', 'text' => 'Twice']]]);

    expect($computer->computeStrings($first))->toBe(['Once'])
        ->and($computer->computeStrings($second))->toBe(['Once']);
});

it('lists each related entry once across fields, and a subject already listed as a specialization once', function (): void {
    $person = knowsAboutGraphPerson('zz-graph-overlap', [
        'clinical_specializations' => ['spec-1'],
        'education' => [['type' => 'post_secondary', 'specialty' => ['biology', 'anatomy']]],
        'research_interests' => [['type' => 'controlled_vocabulary', 'subject_matter' => ['spec-1']]],
        'expertise' => ['exp-1'],
        'conditions' => ['exp-1', 'cond-1'],
    ]);
    $computer = app(PersonKnowsAboutComputer::class);

    expect($computer->computeEntries($person))->toBe(['spec-1', 'subj-biology', 'subj-lead', 'exp-1', 'cond-1'])
        ->and($computer->computeStrings($person))->toBe(['spec-keyword', 'shared', 'subj-biology-keyword', 'subj-lead-keyword']);
});

it('expands a plain term, skips a term of another implementation, and survives a term whose fields cannot be read', function (): void {
    /** @var LocalizedTerm $biology */
    $biology = Term::find('fields_of_study::biology');
    $plain = $biology->term();
    $otherTerm = Mockery::mock(TermContract::class);
    $broken = Mockery::mock(TaxonomyTerm::class);
    $broken->shouldReceive('taxonomyHandle')->andReturn('fields_of_study');
    $broken->shouldReceive('id')->andReturn('fields_of_study::broken');
    $broken->shouldReceive('inDefaultLocale')->andThrow(new RuntimeException('no blueprint'));

    $proxy = Mockery::mock(Term::getFacadeRoot());
    $proxy->shouldReceive('find')->with('credentials::plain')->andReturn($plain);
    $proxy->shouldReceive('find')->with('credentials::odd')->andReturn($otherTerm);
    $proxy->shouldReceive('find')->with('credentials::broken')->andReturn($broken);
    $proxy->shouldReceive('find')->with('fields_of_study::biology')->andReturn($biology);
    $localizedOther = Mockery::mock(LocalizedTerm::class);
    $localizedOther->shouldReceive('term')->andReturn($otherTerm);
    $proxy->shouldReceive('find')->with('credentials::wrapped')->andReturn($localizedOther);
    Term::swap($proxy);

    $person = knowsAboutGraphPerson('zz-graph-lookups', [
        'credentials' => ['plain', 'odd', 'broken', 'wrapped'],
        // Reaches biology a second time, by its own id
        'education' => [['type' => 'post_secondary', 'specialty' => 'biology']],
    ]);
    $computer = app(PersonKnowsAboutComputer::class);

    expect($plain)->toBeInstanceOf(TaxonomyTerm::class)
        ->and($computer->computeTerms($person))->toBe(['fields_of_study::biology', 'fields_of_study::broken'])
        ->and($computer->computeEntries($person))->toBe(['subj-biology']);
});

it('follows a credential to the certification it names', function (): void {
    $person = knowsAboutGraphPerson('zz-graph-credential', ['credentials' => ['md']]);
    $computer = app(PersonKnowsAboutComputer::class);

    expect($computer->computeTerms($person))->toBe([
        'specialties_acgme::surgery',
        'research_specialties_acgme::genomics',
        'other_specialties::wellness',
    ])->and($computer->computeEntries($person))->toBe(['subj-credential', 'subj-surgery', 'subj-genomics', 'subj-wellness']);
});

it('follows a certification row, and passes over a row with no activity', function (): void {
    $person = knowsAboutGraphPerson('zz-graph-certification', [
        'certifications' => [['note' => 'no activity'], ['activity' => 'board']],
        'education' => [['type' => 'post_secondary']],
    ]);

    expect(app(PersonKnowsAboutComputer::class)->computeTerms($person))->toBe([
        'specialties_acgme::surgery',
        'research_specialties_acgme::genomics',
        'other_specialties::wellness',
    ]);
});

it('keeps separate lists for different person ids, including a numeric id', function (): void {
    $computer = app(PersonKnowsAboutComputer::class);
    $first = knowsAboutGraphPerson('zz-graph-first', ['research_interests' => [['type' => 'free_text', 'text' => 'First']]]);
    $numeric = knowsAboutGraphPerson('zz-graph-numeric', ['research_interests' => [['type' => 'free_text', 'text' => 'Numeric']]]);
    $numeric->id(4242);

    expect($computer->computeStrings($first))->toBe(['First'])
        ->and($computer->computeStrings($numeric))->toBe(['Numeric']);
});

it('computes for a person that has no id yet', function (): void {
    $person = knowsAboutGraphPerson('zz-graph-unsaved', ['research_interests' => [['type' => 'free_text', 'text' => 'Draft']]]);
    $person->id(null);

    expect(app(PersonKnowsAboutComputer::class)->computeStrings($person))->toBe(['Draft']);
});
