<?php

declare(strict_types=1);

use App\Support\People\PersonKnowsAboutComputer;
use Illuminate\Validation\ValidationException;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint as BlueprintFacade;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Fieldset;
use Statamic\Facades\Search;
use Statamic\Facades\Site;
use Statamic\Facades\Taxonomy;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;
use Statamic\Fields\Validator;
use Statamic\Fields\Value;
use Statamic\Query\StatusQueryBuilder;
use Statamic\Search\Index;
use Statamic\Search\QueryBuilder;
use Statamic\Search\Result;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Structures\CollectionStructure;
use Statamic\Structures\CollectionTree;
use Tests\Concerns\RequiresContentSubmodule;
use Tests\TestCase;

uses(TestCase::class, RequiresContentSubmodule::class);

/**
 * Coverage for UAMS-Web/uams-statamic-archive#2596: `clinical_specializations` was a taxonomy enabled on `default`
 * and `health`, and is now a structured collection on `default` only, picked across
 * sites (UAMS-Web/uams-statamic-archive#2605's decision A). It is the Health Care Provider Taxonomy code set, a
 * national vocabulary, so there is one list, and the NUCC hierarchy that the terms'
 * `parent` field held is now the collection tree.
 *
 * Nothing here saves an entry: the cross-site and knows-about cases build unsaved
 * people in memory, so no write reaches the shared content mirror.
 *
 * @command  php artisan test --compact tests/Feature/ClinicalSpecializationsCollectionTest.php
 */
const CLINICAL_SPECIALIZATION_CHAIN = [
    'individual-or-groups-of-individuals',
    'allopathic-osteopathic-physicians',
    '208g00000x',
    'congenital-heart-surgeon',
];

afterEach(function (): void {
    Site::setCurrent('default');
});

/**
 * The `clinical_specializations` entries on `default`, keyed by slug.
 *
 * @return array<string, StatamicEntry>
 */
function clinicalSpecializationEntriesBySlug(): array
{
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'clinical_specializations');

    /** @var Illuminate\Support\Collection<int, StatamicEntry> $entries */
    $entries = $query->get();

    return $entries->keyBy(fn (StatamicEntry $entry): string => mustBeString($entry->slug(), 'a specialization slug'))->all();
}

/**
 * A field from a fieldset, by handle.
 */
function clinicalSpecializationFieldsetField(string $fieldset, string $handle): Field
{
    return mustBe(Field::class, mustBe(Statamic\Fields\Fieldset::class, Fieldset::find($fieldset), "the {$fieldset} fieldset")->field($handle), $handle);
}

/**
 * The id of the Thoracic Surgeon entry (NUCC `208G00000X`).
 */
function thoracicSurgeonSpecializationId(): string
{
    return mustBeString(clinicalSpecializationEntriesBySlug()['208g00000x']->id(), 'the thoracic surgeon id');
}

it('is a structured collection on default only, with its blueprint and search index, and the taxonomy is retired', function (): void {
    $this->requireContentCollection('clinical_specializations');

    /** @var Statamic\Entries\Collection $collection */
    $collection = Collection::findByHandle('clinical_specializations');
    $blueprint = mustBe(Blueprint::class, $collection->entryBlueprint('clinical_specialization'), 'the clinical_specialization blueprint');

    expect($collection->hasStructure())->toBeTrue()
        ->and($collection->propagate())->toBeFalse()
        // Decision A (UAMS-Web/uams-statamic-archive#2605): one list, on one site. Every picker reaches it from every site.
        ->and(mustBe(Illuminate\Support\Collection::class, $collection->sites(), 'clinical_specializations sites')->all())
        ->toBe(['default'])
        // A specialization has no public page, as the terms had none.
        ->and($collection->route('default'))->toBeNull()
        // By fields only the committed file has: with no file, Statamic builds a fallback
        // under the same handle, so neither the handle nor the title proves it. These are
        // the UAMS-Web/uams-statamic-archive#943 code-set fields the resolution test used to check on the term blueprint.
        ->and($blueprint->hasField('code'))->toBeTrue()
        ->and($blueprint->hasField('isco08_code'))->toBeTrue()
        // The hierarchy is the tree now, not a field.
        ->and($blueprint->hasField('parent'))->toBeFalse()
        ->and(config('statamic.search.indexes.clinical_specializations.searchables'))->toBe('collection:clinical_specializations')
        ->and(config('statamic.search.indexes.clinical_specializations.fields'))->toContain('code');

    expect(Taxonomy::findByHandle('clinical_specializations'))->toBeNull()
        ->and(BlueprintFacade::in('taxonomies/clinical_specializations')->count())->toBe(0);
});

it('holds the four former terms as entries, in their NUCC chain, with #2823\'s entries beside them', function (): void {
    $this->requireContentCollection('clinical_specializations');

    $entries = clinicalSpecializationEntriesBySlug();

    // The four former terms, the 46 entries UAMS-Web/uams-statamic-archive#2823 created for the subjects it moved, and
    // the 192 left of the 193 created for the specialty terms (UAMS-Web/uams-statamic-archive#3057, UAMS-Web/uams-statamic-archive#3058, UAMS-Web/uams-statamic-archive#3060, UAMS-Web/uams-statamic-archive#3061)
    // once UAMS-Web/uams-statamic-archive#3179 removed the shoulder and elbow extension: the 187 they resolve to and
    // five uncoded classifications placed above them; and the 31 UAMS-Web/uams-statamic-archive#3062 created for the
    // fields of study, 28 the terms name and three classifications placed above them
    expect(array_intersect(CLINICAL_SPECIALIZATION_CHAIN, array_keys($entries)))->toBe(CLINICAL_SPECIALIZATION_CHAIN)
        ->and($entries)->toHaveCount(273)
        ->and($entries['208g00000x']->get('title'))->toBe('Thoracic Surgeon')
        ->and($entries['208g00000x']->get('code'))->toBe('208G00000X');

    /** @var Statamic\Entries\Collection $collection */
    $collection = Collection::findByHandle('clinical_specializations');
    $tree = mustBe(CollectionTree::class, mustBe(CollectionStructure::class, $collection->structure(), 'the structure')->in('default'), 'the default tree');

    // Every entry sits in the tree once, under one root
    $placed = [];
    $parentOf = [];
    $walk = function (array $nodes, ?string $parent) use (&$walk, &$placed, &$parentOf): void {
        foreach ($nodes as $node) {
            $node = mustBeArray($node, 'a tree node');
            $id = mustBeString($node['entry'] ?? null, 'a tree entry id');
            $placed[] = $id;
            $parentOf[$id] = $parent;
            $walk(mustBeArray($node['children'] ?? [], 'the node children'), $id);
        }
    };
    $roots = mustBeArray($tree->tree(), 'the default tree');
    $walk($roots, null);

    $idOf = fn (string $slug): string => mustBeString($entries[$slug]->id(), "{$slug}'s id");

    expect($roots)->toHaveCount(1)
        ->and(array_count_values($placed))->each->toBe(1)
        ->and($placed)->toHaveCount(273)
        // The former chain is unchanged: each former term under the one before it
        ->and($parentOf[$idOf('allopathic-osteopathic-physicians')])->toBe($idOf('individual-or-groups-of-individuals'))
        ->and($parentOf[$idOf('208g00000x')])->toBe($idOf('allopathic-osteopathic-physicians'))
        ->and($parentOf[$idOf('congenital-heart-surgeon')])->toBe($idOf('208g00000x'));
});

it('stores every clinical specialization reference as an id of an entry in the collection', function (string $collection): void {
    $this->requireContentCollection('clinical_specializations');

    $ids = array_values(array_map(fn (StatamicEntry $entry): string => mustBeString($entry->id(), 'a specialization id'), clinicalSpecializationEntriesBySlug()));

    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', $collection);

    /** @var Illuminate\Support\Collection<int, StatamicEntry> $entries */
    $entries = $query->get();

    // A left-over term slug would resolve to nothing through an entries field. The content
    // set stores none today, so this guards the next import rather than a migrated value.
    $stored = $entries
        ->flatMap(fn (StatamicEntry $entry): array => mustBeArray($entry->get('clinical_specializations') ?? [], 'a stored clinical_specializations'))
        ->values();

    expect($stored->diff($ids)->all())->toBe([]);
})->with(['people', 'clinical_expertise']);

it('gives every field that referenced the taxonomy an entries picker on the collection, reachable from every site', function (Field $field, ?int $maxItems): void {
    expect($field->type())->toBe('entries')
        ->and($field->get('collections'))->toBe(['clinical_specializations'])
        ->and($field->get('select_across_sites'))->toBeTrue()
        // UAMS-Web/uams-statamic-archive#2606: Statamic's own `entries` finds an entry by a pasted code through this index.
        ->and($field->get('search_index'))->toBe('clinical_specializations')
        ->and($field->get('taxonomies'))->toBeNull()
        ->and($field->get('max_items'))->toBe($maxItems);
})->with([
    'people' => [fn (): Field => mustBe(Field::class, mustBe(Blueprint::class, Collection::findByHandle('people')?->entryBlueprint('person'), 'the person blueprint')->field('clinical_specializations'), 'the people field'), null],
    'areas of expertise' => [fn (): Field => mustBe(Field::class, mustBe(Blueprint::class, Collection::findByHandle('clinical_expertise')?->entryBlueprint('clinical_expertise'), 'the clinical_expertise blueprint')->field('clinical_specializations'), 'the clinical_expertise field'), null],
    'schema nucc_single' => [fn (): Field => clinicalSpecializationFieldsetField('uamsweb_schema', 'nucc_single'), 1],
    'schema nucc_multiple' => [fn (): Field => clinicalSpecializationFieldsetField('uamsweb_schema', 'nucc_multiple'), null],
]);

it('finds a specialization by its pasted NUCC code through the picker search index', function (): void {
    $this->requireContentCollection('clinical_specializations');

    $index = mustBe(Index::class, Search::index('clinical_specializations'), 'the clinical_specializations index');

    $slugs = [];
    foreach (mustBe(Illuminate\Support\Collection::class, mustBe(QueryBuilder::class, mustBe(Index::class, $index->ensureExists(), 'the built index')->search('208G00000X'), 'the search query')->get(), 'the results') as $result) {
        $slugs[] = mustBe(StatamicEntry::class, mustBe(Result::class, $result, 'a search result')->getSearchable(), 'a searchable')->slug();
    }

    expect($slugs)->toContain('208g00000x');
});

it('resolves a specialization chosen for a person on another site', function (): void {
    $this->requireContentCollection('clinical_specializations');

    // Unsaved, so nothing is written: a person authored on `health` pointing at the one
    // `default` entry, which is the reference decision A relies on.
    /** @var StatamicEntry $person */
    $person = Entry::make();
    $person->collection('people');
    $person->blueprint('person');
    $person->locale('health');
    $person->slug('zz-clinical-specializations-cross-site');
    $person->data(['title' => 'Montgomery Scott', 'clinical_specializations' => [thoracicSurgeonSpecializationId()]]);

    /** @var Value $value */
    $value = $person->augmentedValue('clinical_specializations');
    /** @var Illuminate\Support\Collection<int, StatamicEntry> $resolved */
    $resolved = mustBe(StatusQueryBuilder::class, $value->value(), 'the clinical_specializations relation')->get();

    expect($resolved->map(fn (StatamicEntry $entry): string => mustBeString($entry->slug(), 'a specialization slug'))->all())->toBe(['208g00000x'])
        // The shared clinical title reads the same entry from a `health` person.
        ->and($person->value('clinical_title'))->toBe('Thoracic Surgery');
});

/**
 * An unsaved provider on the given site carrying the given clinical specializations.
 *
 * @param  list<string>  $slugs  Specialization slugs, resolved to the entry ids the field stores.
 */
function specialtyFilterProvider(string $site, string $slug, array $slugs): StatamicEntry
{
    $entries = clinicalSpecializationEntriesBySlug();

    /** @var StatamicEntry $person */
    $person = Entry::make();
    $person->collection('people');
    $person->blueprint('person');
    $person->locale($site);
    $person->slug($slug);
    $person->data([
        'title' => $slug,
        'clinical_specializations' => array_map(fn (string $s): string => mustBeString($entries[$s]->id(), 'a specialization id'), $slugs),
    ]);

    return $person;
}

/**
 * The clinical title option values the section filter renders for the given providers.
 *
 * @param  list<StatamicEntry>  $providers
 * @return list<string>
 */
function specialtyFilterOptions(array $providers): array
{
    $out = (string) Antlers::parse(
        '{{ partial:clinical_expertise/section_filter show_specialty="true" filter_noun_plural="providers" :providers="providers" }}',
        ['providers' => $providers],
        true
    );

    preg_match_all('/<option value="([^"]+)">/', $out, $matches);

    return $matches[1];
}

it('lists only the clinical titles the listed providers carry, once each, by title, on both sites', function (string $site): void {
    $this->requireContentCollection('clinical_specializations');

    Site::setCurrent($site);

    $options = specialtyFilterOptions([
        specialtyFilterProvider($site, 'zz-filter-provider-one', ['208g00000x', '207sg0202x']),
        specialtyFilterProvider($site, 'zz-filter-provider-two', ['208g00000x']),
        specialtyFilterProvider($site, 'zz-filter-provider-none', []),
    ]);

    // Sorted by title: Clinical Biochemical Genetics Physician, then Thoracic Surgeon.
    expect($options)->toBe(['207sg0202x', '208g00000x'])
        // An entry no listed provider carries is not offered.
        ->and($options)->not->toContain('congenital-heart-surgeon');
})->with(['default', 'health']);

it('offers no clinical title when no listed provider carries one', function (array $providers): void {
    $this->requireContentCollection('clinical_specializations');

    /** @var list<StatamicEntry> $typedProviders */
    $typedProviders = $providers;

    expect(specialtyFilterOptions($typedProviders))->toBe([]);
})->with([
    'no providers' => [[]],
    'providers with none' => [fn (): array => [specialtyFilterProvider('default', 'zz-filter-provider-bare', [])]],
]);

it('reads the specialty options from the providers in the section bar, and the whole collection in the Find-a-Doc block', function (): void {
    $section = (string) file_get_contents(resource_path('views/clinical_expertise/_section_filter.antlers.html'));
    $block = (string) file_get_contents(resource_path('views/page_builder/components/_block_fad_filter.antlers.html'));
    $page = (string) file_get_contents(resource_path('views/clinical_expertise/section.antlers.html'));

    expect($section)->not->toContain('collection:clinical_specializations')
        ->and($section)->toContain('merge_array(clinical_specializations)')
        ->and($page)->toContain(':providers="eligible_providers"')
        ->and($block)->toContain('{{ collection:clinical_specializations site="default" sort="title:asc" }}')
        ->and($section.$block)->not->toContain('taxonomy:clinical_specializations');
});

it('lists a person\'s specializations as knows-about entries, and no longer as term ids', function (): void {
    $this->requireContentCollection('clinical_specializations');

    $id = thoracicSurgeonSpecializationId();

    /** @var StatamicEntry $person */
    $person = Entry::make();
    $person->collection('people');
    $person->blueprint('person');
    $person->locale('default');
    $person->id('zz-knows-about-scotty');
    $person->slug('zz-knows-about-scotty');
    $person->data(['title' => 'Montgomery Scott', 'clinical_specializations' => [$id]]);

    $computer = app(PersonKnowsAboutComputer::class);

    expect($computer->computeEntries($person))->toBe([$id])
        ->and(array_filter($computer->computeTerms($person), fn (string $term): bool => str_starts_with($term, 'clinical_specializations::')))->toBe([])
        ->and(clinicalSpecializationFieldsetField('uamsweb_computed_person', 'knows_about_terms')->get('taxonomies'))
        ->not->toContain('clinical_specializations');
});

it('gives every coded entry a well-formed, unique NUCC code, and a grouping or extension none (#2823)', function (): void {
    $this->requireContentCollection('clinical_specializations');

    $coded = [];
    $uncoded = [];

    foreach (clinicalSpecializationEntriesBySlug() as $slug => $entry) {
        $code = $entry->get('code');

        if ($entry->get('code_query') === true) {
            $coded[$slug] = mustBeString($code, "{$slug}'s code");
        } else {
            $uncoded[$slug] = $code;
        }
    }

    // 208G00000X, the 11 UAMS-Web/uams-statamic-archive#2823 created, the 174 the specialty terms resolve to and the 31
    // UAMS-Web/uams-statamic-archive#3062 created for the fields of study; the slug of each is its code in lower case
    expect($coded)->toHaveCount(217)
        ->and(array_values(array_unique($coded)))->toBe(array_values($coded))
        ->and(array_filter($coded, fn (string $code): bool => preg_match('/^\d{3}[0-9A-Z]{2}\d{4}X$/D', $code) !== 1))->toBe([])
        ->and(array_filter($coded, fn (string $code, string $slug): bool => strtolower($code) !== $slug, ARRAY_FILTER_USE_BOTH))->toBe([])
        // A NUCC grouping, a classification NUCC gives no code, and a UAMS extension carry none
        ->and(array_filter($uncoded, fn (mixed $code): bool => $code !== null))->toBe([])
        // 38 before, plus five classifications NUCC gives no code and thirteen extensions
        ->and($uncoded)->toHaveCount(56);
});

it('refuses an extension with no accreditor or examination, and accepts one with both (#3169)', function (bool $answer, array $errors): void {
    $blueprint = mustBe(Blueprint::class, BlueprintFacade::find('collections/clinical_specializations/clinical_specialization'), 'the clinical_specialization blueprint');
    $handles = ['extension_training_accreditation_query', 'extension_accrediting_authority_query', 'extension_examination_query'];

    $config = array_map(
        static fn (string $handle): array => ['handle' => $handle, 'field' => mustBe(Field::class, $blueprint->field($handle), "the {$handle} field")->config()],
        $handles,
    );
    $fields = mustBe(Fields::class, (new Fields($config))->addValues(array_fill_keys($handles, $answer)), 'the fields');

    try {
        mustBe(Validator::class, $fields->validator(), 'the validator')->validate();
        $failed = [];
    } catch (ValidationException $validationException) {
        $failed = array_keys($validationException->errors());
        sort($failed);
    }

    expect($failed)->toBe($errors);
})->with([
    'all three false, as the shoulder and elbow fellowship answered' => [false, ['extension_accrediting_authority_query', 'extension_examination_query', 'extension_training_accreditation_query']],
    'all three true' => [true, []],
]);

it('carries orthopaedic-surgery\'s keywords on 207X00000X, in a field every entry kind shows (#2823)', function (): void {
    $this->requireContentCollection('clinical_specializations');

    $keywords = mustBeArray(clinicalSpecializationEntriesBySlug()['207x00000x']->get('keywords'), 'the keywords');
    $blueprint = mustBe(Blueprint::class, Collection::findByHandle('clinical_specializations')?->entryBlueprint('clinical_specialization'), 'the blueprint');
    $field = mustBe(Field::class, $blueprint->field('keywords'), 'the keywords field');

    expect($keywords)->toHaveCount(59)
        ->and($keywords)->toContain('arthroplasty')
        // No condition, so Statamic keeps the value on a grouping or extension entry too
        ->and($field->get('if'))->toBeNull();
});
