<?php

declare(strict_types=1);

use App\Scopes\ExpertiseByAssociatedOntologySearch;
use Illuminate\Support\Collection;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Query\Scopes\Scope;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Coverage for the Areas of Expertise index faceted-search scope (UAMS-Web/uams-statamic-archive#1440):
 * App\Scopes\ExpertiseByAssociatedOntologySearch. Given a visitor's `q`, it
 * narrows a `clinical_expertise` query to the Areas of Expertise whose associated
 * `clinical_conditions` / `clinical_services` match the term by title OR by
 * `alternate_name` (a replicator flattened via ReplicatorSetFlattener).
 *
 * Builds an isolated Stache with real condition/service/expertise entries so the
 * term→id resolution and the `orWhereJsonOverlaps` narrowing run against the real
 * query layer. Companion render coverage lives in ExpertiseListingSearchTest.
 *
 * @command php artisan test --compact tests/Feature/Scopes/ExpertiseByAssociatedOntologySearchScopeTest.php
 */
uses(StacheIsolatedTestCase::class);

/**
 * Make, populate, and quietly save an ontology or Area-of-Expertise fixture entry.
 *
 * @param  array<string, mixed>  $data
 */
function makeOntologyEntry(string $collection, string $slug, bool $published, array $data): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection($collection);
    $entry->slug($slug);
    $entry->published($published);
    $entry->data($data);
    $entry->saveQuietly();

    return $entry;
}

beforeEach(function (): void {
    $expertise = CollectionFacade::make('clinical_expertise');
    $expertise->title('Areas of Expertise');
    $expertise->save();

    $conditions = CollectionFacade::make('clinical_conditions');
    $conditions->title('Clinical Conditions');
    $conditions->save();

    $services = CollectionFacade::make('clinical_services');
    $services->title('Clinical Services');
    $services->save();

    // Ontology terms — conditions and services carrying a title and, for two of
    // them, an `alternate_name` replicator row (the `text` handle the flattener reads).
    $this->conditionDiabetes = $conditionDiabetes = makeOntologyEntry('clinical_conditions', 'type-2-diabetes', true, [
        'title' => 'Type 2 Diabetes',
        'alternate_name' => [['type' => 'new_set', 'enabled' => true, 'text' => 'T2D']],
    ]);

    $this->conditionAsthma = $conditionAsthma = makeOntologyEntry('clinical_conditions', 'asthma', true, [
        'title' => 'Asthma',
    ]);

    $this->serviceKnee = $serviceKnee = makeOntologyEntry('clinical_services', 'knee-replacement', true, [
        'title' => 'Knee Replacement',
        'alternate_name' => [['type' => 'new_set', 'enabled' => true, 'text' => 'Arthroplasty']],
    ]);

    // A DRAFT condition that should never surface an Area of Expertise.
    $this->conditionDraft = makeOntologyEntry('clinical_conditions', 'secret-condition', false, [
        'title' => 'Diabetes Insipidus',
    ]);

    // Areas of Expertise, each associated with one ontology term (or none).
    $this->endocrinology = makeOntologyEntry('clinical_expertise', 'endocrinology', true, [
        'title' => 'Endocrinology',
        'clinical_conditions' => [$conditionDiabetes->id()],
    ]);

    $this->pulmonology = makeOntologyEntry('clinical_expertise', 'pulmonology', true, [
        'title' => 'Pulmonology',
        'clinical_conditions' => [$conditionAsthma->id()],
    ]);

    $this->orthopedics = makeOntologyEntry('clinical_expertise', 'orthopedics', true, [
        'title' => 'Orthopedics',
        'clinical_services' => [$serviceKnee->id()],
    ]);

    $this->cardiology = makeOntologyEntry('clinical_expertise', 'cardiology', true, [
        'title' => 'Cardiology',
    ]);
});

/**
 * Apply a Statamic query scope through its base `Scope` contract, to the Statamic
 * query builder the scope operates on.
 *
 * @param  array<string, mixed>  $values
 */
function applyExpertiseOntologyScope(Scope $scope, EntryQueryBuilder $query, array $values): void
{
    $scope->apply($query, $values);
}

/**
 * Apply the scope with a term and return the matching Area-of-Expertise slugs.
 *
 * @return list<string>
 */
function expertiseSearchSlugs(string $term): array
{
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'clinical_expertise');

    // Invoke through the base `Scope` contract: the concrete scope's PHPDoc narrows
    // `$query` to an Eloquent builder, but Statamic passes — and this scope operates
    // on — the Statamic query builder that the inherited signature types correctly.
    applyExpertiseOntologyScope(new ExpertiseByAssociatedOntologySearch, $query, ['q' => $term]);

    /** @var Collection<int, Statamic\Entries\Entry> $results */
    $results = $query->get();

    /** @var list<string> $slugs */
    $slugs = $results
        ->map(function (Statamic\Entries\Entry $entry): string {
            /** @var string|null $slug */
            $slug = $entry->slug();

            return (string) $slug;
        })
        ->sort()
        ->values()
        ->all();

    return $slugs;
}

it('leaves the listing unfiltered for a blank term', function (): void {
    expect(expertiseSearchSlugs(''))
        ->toEqualCanonicalizing(['cardiology', 'endocrinology', 'orthopedics', 'pulmonology']);
    expect(expertiseSearchSlugs('   '))
        ->toEqualCanonicalizing(['cardiology', 'endocrinology', 'orthopedics', 'pulmonology']);
});

it('narrows to Areas of Expertise whose condition matches by title', function (): void {
    expect(expertiseSearchSlugs('diabetes'))->toEqual(['endocrinology']);
});

it('narrows by an alternate name of an associated condition', function (): void {
    expect(expertiseSearchSlugs('t2d'))->toEqual(['endocrinology']);
});

it('narrows by an associated service, matched by title and by alternate name', function (): void {
    expect(expertiseSearchSlugs('knee'))->toEqual(['orthopedics']);
    expect(expertiseSearchSlugs('arthroplasty'))->toEqual(['orthopedics']);
});

it('matches case-insensitively', function (): void {
    expect(expertiseSearchSlugs('DIABETES'))->toEqual(['endocrinology']);
    expect(expertiseSearchSlugs('ArThRoPlAsTy'))->toEqual(['orthopedics']);
});

it('returns an empty listing when a non-blank term matches no condition or service', function (): void {
    expect(expertiseSearchSlugs('nonexistent-term'))->toEqual([]);
});

it('never surfaces an Area of Expertise via an unpublished condition', function (): void {
    // "Diabetes Insipidus" is a draft condition; searching its title must not
    // surface any Area of Expertise, even one that referenced it.
    /** @var Statamic\Entries\Entry $conditionDraft */
    $conditionDraft = $this->conditionDraft;
    makeOntologyEntry('clinical_expertise', 'nephrology', true, [
        'title' => 'Nephrology',
        'clinical_conditions' => [$conditionDraft->id()],
    ]);

    expect(expertiseSearchSlugs('insipidus'))->toEqual([]);
});

it('never surfaces an Area of Expertise via a searchable:false condition (#1060/#1489)', function (): void {
    // A condition explicitly flagged out of interactive search…
    $hidden = makeOntologyEntry('clinical_conditions', 'hyperthyroidism', true, [
        'title' => 'Hyperthyroidism',
        'searchable' => false,
    ]);

    // …and an Area of Expertise whose only associated condition is that hidden one.
    makeOntologyEntry('clinical_expertise', 'thyroid-clinic', true, [
        'title' => 'Thyroid Clinic',
        'clinical_conditions' => [$hidden->id()],
    ]);

    expect(expertiseSearchSlugs('hyperthyroidism'))->toEqual([]);
});

it('never surfaces an Area of Expertise via a searchable:false service (#1060/#1489)', function (): void {
    $hidden = makeOntologyEntry('clinical_services', 'experimental-graft', true, [
        'title' => 'Experimental Graft',
        'searchable' => false,
    ]);

    makeOntologyEntry('clinical_expertise', 'transplant', true, [
        'title' => 'Transplant',
        'clinical_services' => [$hidden->id()],
    ]);

    expect(expertiseSearchSlugs('graft'))->toEqual([]);
});

it('still surfaces an Area of Expertise via an explicitly searchable:true condition', function (): void {
    $shown = makeOntologyEntry('clinical_conditions', 'psoriasis', true, [
        'title' => 'Psoriasis',
        'searchable' => true,
    ]);

    makeOntologyEntry('clinical_expertise', 'dermatology', true, [
        'title' => 'Dermatology',
        'clinical_conditions' => [$shown->id()],
    ]);

    expect(expertiseSearchSlugs('psoriasis'))->toEqual(['dermatology']);
});

it('treats a missing searchable value as searchable (no silent flip)', function (): void {
    // `conditionDiabetes` (beforeEach) stores no `searchable` key; it must keep
    // surfacing its Area of Expertise exactly as before the gate existed.
    /** @var Statamic\Entries\Entry $conditionDiabetes */
    $conditionDiabetes = $this->conditionDiabetes;
    expect($conditionDiabetes->get('searchable'))->toBeNull();
    expect(expertiseSearchSlugs('diabetes'))->toEqual(['endocrinology']);
});

it('gates per condition, not per Area of Expertise, on mixed searchable states', function (): void {
    // One Area of Expertise associated with two conditions of opposite searchable
    // state: the hidden one must not surface it; the visible one must — proving the
    // gate is per-condition, not whole-AoE.
    $hidden = makeOntologyEntry('clinical_conditions', 'lupus', true, [
        'title' => 'Lupus',
        'searchable' => false,
    ]);
    $shown = makeOntologyEntry('clinical_conditions', 'rheumatoid-arthritis', true, [
        'title' => 'Rheumatoid Arthritis',
        'searchable' => true,
    ]);

    makeOntologyEntry('clinical_expertise', 'rheumatology', true, [
        'title' => 'Rheumatology',
        'clinical_conditions' => [$hidden->id(), $shown->id()],
    ]);

    expect(expertiseSearchSlugs('lupus'))->toEqual([]);
    expect(expertiseSearchSlugs('arthritis'))->toEqual(['rheumatology']);
});
