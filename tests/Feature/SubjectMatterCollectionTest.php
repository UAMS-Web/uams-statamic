<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaJsonLdFragmentAtId;
use App\Services\SchemaTemplates\SchemaOutputService;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint as BlueprintFacade;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Value;
use Statamic\Query\StatusQueryBuilder;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Structures\CollectionStructure;
use Statamic\Structures\CollectionTree;
use Statamic\Taxonomies\LocalizedTerm;
use Symfony\Component\Yaml\Yaml;
use Tests\Concerns\RequiresContentSubmodule;
use Tests\TestCase;

uses(TestCase::class, RequiresContentSubmodule::class);

/**
 * Coverage for UAMS-Web/uams-statamic-archive#2822: `subject_matter` was a taxonomy on `default`, and is now a
 * structured collection on `default` only, picked across sites, as UAMS-Web/uams-statamic-archive#2597 did for
 * `geographic_locations`. The term `parent` field allowed two values; the tree
 * keeps one, so `health-sciences` sits under `applied-science`, the first value it
 * had stored. UAMS-Web/uams-statamic-archive#2823 then moved the 42 subjects the NUCC code set covers to
 * `clinical_specializations`; the 11 that stay, and the references that moved, are
 * pinned below.
 *
 * Nothing here saves an entry: the cross-site and rendering cases build unsaved
 * entries and terms in memory, so no write reaches the shared content mirror.
 *
 * @command  php artisan test --compact tests/Feature/SubjectMatterCollectionTest.php
 */
afterEach(function (): void {
    Site::setCurrent('default');
});

/**
 * The `subject_matter` entries on `default`, keyed by slug.
 *
 * @return array<string, StatamicEntry>
 */
function subjectMatterEntriesBySlug(): array
{
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'subject_matter');

    /** @var Illuminate\Support\Collection<int, StatamicEntry> $entries */
    $entries = $query->get();

    return $entries->keyBy(fn (StatamicEntry $entry): string => mustBeString($entry->slug(), 'a subject slug'))->all();
}

/**
 * Each subject's parent slug in the default tree, keyed by slug (null for a root).
 *
 * @return array<string, ?string>
 */
function subjectMatterTreeParents(): array
{
    /** @var Statamic\Entries\Collection $collection */
    $collection = Collection::findByHandle('subject_matter');
    $tree = mustBe(CollectionTree::class, mustBe(CollectionStructure::class, $collection->structure(), 'the structure')->in('default'), 'the default tree');

    $slugOf = fn (mixed $id): string => mustBeString(
        mustBe(StatamicEntry::class, Entry::find(mustBeString($id, 'a tree entry id')), 'a subject entry')->slug(),
        'a subject slug',
    );

    $parents = [];
    $walk = function (array $nodes, ?string $parent) use (&$walk, &$parents, $slugOf): void {
        foreach ($nodes as $node) {
            $node = mustBeArray($node, 'a tree node');
            $slug = $slugOf($node['entry'] ?? null);

            // An entry placed twice would overwrite its first parent here, so count placements
            expect($parents)->not->toHaveKey($slug);

            $parents[$slug] = $parent;
            $walk(mustBeArray($node['children'] ?? [], 'a node\'s children'), $slug);
        }
    };
    $walk(mustBeArray($tree->tree(), 'the default tree'), null);

    return $parents;
}

/**
 * Every committed field definition that picks subjects, read from the files.
 *
 * Fieldsets and blueprints nest fields in tabs, sections, and replicator sets the
 * fieldset API does not all reach, so the definitions are read from the YAML.
 *
 * @return array<string, array<mixed>> Field config keyed by file and handle
 */
function subjectMatterPickerDefinitions(): array
{
    $pickers = [];

    $paths = [
        ...(glob(resource_path('blueprints/**/*.yaml')) ?: []),
        ...(glob(resource_path('blueprints/*/*/*.yaml')) ?: []),
        ...(glob(resource_path('fieldsets/*.yaml')) ?: []),
    ];

    // On Windows resource_path() and glob() join with backslashes, so both sides are
    // normalized to forward slashes before the prefix is stripped (UAMS-Web/uams-statamic-archive#3175)
    $resources = str_replace('\\', '/', resource_path()).'/';

    foreach (array_unique($paths) as $path) {
        $relative = str_replace($resources, '', str_replace('\\', '/', $path));
        $walk = function (mixed $node) use (&$walk, &$pickers, $relative): void {
            if (! \is_array($node)) {
                return;
            }

            $field = $node['field'] ?? null;

            if (
                \is_array($field)
                && (
                    \in_array('subject_matter', (array) ($field['collections'] ?? []), true)
                    || \in_array('subject_matter', (array) ($field['taxonomies'] ?? []), true)
                    // Since UAMS-Web/uams-statamic-archive#2823 three specialty pickers offer clinical_specializations only, so
                    // a picker is also found by the handle it kept
                    || (($node['handle'] ?? null) === 'subject_matter' && ($field['type'] ?? null) === 'entries')
                )
            ) {
                $handle = mustBeString($node['handle'] ?? null, 'a field handle');
                $pickers[$relative.':'.$handle] = $field;
            }

            foreach ($node as $child) {
                $walk($child);
            }
        };
        $walk(Yaml::parseFile($path));
    }

    ksort($pickers);

    return $pickers;
}

it('is a structured collection on default only, with its blueprint, and the taxonomy is retired', function (): void {
    $this->requireContentCollection('subject_matter');

    /** @var Statamic\Entries\Collection $collection */
    $collection = Collection::findByHandle('subject_matter');

    $blueprint = mustBe(Blueprint::class, $collection->entryBlueprint('subject_matter'), 'the subject_matter blueprint');

    expect($collection->hasStructure())->toBeTrue()
        ->and($collection->propagate())->toBeFalse()
        // One tree, on one site. The pickers reach it from every site.
        ->and(mustBe(Illuminate\Support\Collection::class, $collection->sites(), 'subject_matter sites')->all())
        ->toBe(['default'])
        // Subjects have no public page, as the terms had none.
        ->and($collection->route('default'))->toBeNull()
        // By fields only the committed file has: a fallback blueprint shares the handle and title.
        ->and($blueprint->hasField('alternate_name'))->toBeTrue()
        ->and($blueprint->hasField('keywords'))->toBeTrue()
        ->and($blueprint->hasField('selectable'))->toBeTrue()
        // The tree carries the hierarchy now, so the term-era parent field is gone.
        ->and($blueprint->hasField('parent'))->toBeFalse()
        ->and(config('statamic.search.indexes.subject_matter.searchables'))->toBe('collection:subject_matter');

    expect(Taxonomy::findByHandle('subject_matter'))->toBeNull()
        ->and(BlueprintFacade::in('taxonomies/subject_matter')->count())->toBe(0);
});

it('holds the 37 subjects the code set cannot express, each placed once in the tree', function (): void {
    $this->requireContentCollection('subject_matter');

    $entries = subjectMatterEntriesBySlug();
    $parents = subjectMatterTreeParents();

    // UAMS-Web/uams-statamic-archive#2823 moved the 42 subjects the NUCC code set covers to clinical_specializations;
    // the disciplines, recognition and workforce categories stay, UAMS-Web/uams-statamic-archive#2830 and UAMS-Web/uams-statamic-archive#3055 added
    // seven and five root subjects for credentials the code set does not cover, and UAMS-Web/uams-statamic-archive#3062
    // added 14 root subjects for fields of study it does not cover
    expect(array_keys($entries))->toEqualCanonicalizing([
        'agriculture', 'applied-science', 'architecture', 'area-studies', 'art', 'award', 'biochemical-genetics',
        'bioethics', 'biology', 'business', 'child-development', 'communication', 'computing', 'criminal-justice',
        'education', 'engineering', 'environmental-science', 'health-administration', 'health-sciences',
        'humanities', 'languages-and-literature', 'law', 'library-and-information-science', 'life-sciences',
        'mathematics', 'music', 'natural-science', 'professional-practice', 'psychology', 'public-administration',
        'recognition', 'research', 'science', 'social-sciences', 'theology', 'uams-employee', 'uams-worker',
    ])
        // Every entry is placed in the tree exactly once (the helper rejects a second placement).
        ->and(array_keys($parents))->toEqualCanonicalizing(array_keys($entries))
        ->and(array_keys(array_filter($parents, fn (?string $parent): bool => $parent === null)))
        ->toEqualCanonicalizing([
            'agriculture', 'architecture', 'area-studies', 'art', 'bioethics', 'business', 'child-development',
            'communication', 'computing', 'criminal-justice', 'education', 'engineering', 'environmental-science',
            'health-administration', 'humanities', 'languages-and-literature', 'law', 'library-and-information-science',
            'mathematics', 'music', 'professional-practice', 'psychology', 'public-administration', 'recognition',
            'research', 'science', 'social-sciences', 'theology', 'uams-worker',
        ])
        // health-sciences keeps the first of its two stored parents (UAMS-Web/uams-statamic-archive#2822)
        ->and($parents['health-sciences'])->toBe('applied-science')
        // genetics moved, so its non-clinical child re-parents under genetics' own parent
        ->and($parents['biochemical-genetics'])->toBe('biology')
        ->and($parents['award'])->toBe('recognition')
        ->and($parents['uams-employee'])->toBe('uams-worker');
});

it('stores every subject reference as an id in the collection', function (): void {
    $this->requireContentCollection('subject_matter');

    $slugById = [];

    foreach (subjectMatterEntriesBySlug() as $slug => $entry) {
        $slugById[mustBeString($entry->id(), 'a subject id')] = $slug;
    }

    // The one term file still naming subjects after UAMS-Web/uams-statamic-archive#2823
    $term = mustBe(LocalizedTerm::class, Term::find('video_series::faces-of-uams'), 'video_series::faces-of-uams');
    $ids = mustBeArray($term->get('about'), 'faces-of-uams about');

    // A left-over term slug would resolve to nothing through an entries field.
    expect(array_map(fn (mixed $id): ?string => $slugById[mustBeString($id, 'a stored subject id')] ?? null, $ids))
        ->toBe(['uams-employee', 'uams-worker']);
});

it('points every reference to a moved subject at its clinical specialization (#2823)', function (): void {
    $this->requireContentCollection('clinical_specializations');

    // The seven term files that named a subject UAMS-Web/uams-statamic-archive#2823 moved, with the entry each names now
    $stored = [
        'credentials::atc' => 'respiratory-developmental-rehabilitative-and-restorative-service-providers',
        'credentials::md' => 'allopathic-osteopathic-physicians',
        'research_specialties_acgme::clinical-biochemical-genetics' => '207sg0202x',
        'specialties_acgme::clinical-biochemical-genetics' => '207sg0202x',
        'specialties_acgme::medical-biochemical-genetics' => '207sg0207x',
        'specialties_acgme::orthopaedic-surgery' => '207x00000x',
        'specialties_aoa::urogynecology-and-reconstructive-pelvic-surgery' => '207vf0040x',
    ];

    foreach ($stored as $termId => $slug) {
        $term = mustBe(LocalizedTerm::class, Term::find($termId), $termId);
        $resolved = array_map(
            function (mixed $id) use ($termId): string {
                $entry = mustBe(StatamicEntry::class, Entry::find(mustBeString($id, 'a stored id')), "{$termId}'s entry");

                return mustBeString($entry->collectionHandle(), 'a collection handle').'/'.mustBeString($entry->slug(), 'a slug');
            },
            mustBeArray($term->get('subject_matter'), "{$termId} subject_matter"),
        );

        expect($resolved)->toBe(['clinical_specializations/'.$slug]);
    }
});

it('gives every picker the collections #2823 assigns it, reachable from every site, searched by a matching index', function (): void {
    $pickers = subjectMatterPickerDefinitions();

    // The eleven direct definitions: nine taxonomy blueprints and two fieldsets.
    // `about` and `mentions` on posts, articles, audio, video and the series
    // terms reuse `uamsweb_common.subject_matter` and so inherit its config.
    expect(array_keys($pickers))->toBe([
        'blueprints/taxonomies/credentials/credential.yaml:subject_matter',
        'blueprints/taxonomies/fields_of_study/field_of_study.yaml:subject_matter',
        'blueprints/taxonomies/other_specialties/other_specialty.yaml:subject_matter',
        'blueprints/taxonomies/professional_and_leadership_development_activities/professional_and_leadership_development_activity.yaml:subject_matter',
        'blueprints/taxonomies/research_specialties_acgme/research_specialty_acgme.yaml:subject_matter',
        'blueprints/taxonomies/research_specialties_non_accredited/research_specialty_non_accredited.yaml:subject_matter',
        'blueprints/taxonomies/specialties_acgme/specialty_acgme.yaml:subject_matter',
        'blueprints/taxonomies/specialties_aoa/specialty_aoa.yaml:subject_matter',
        'blueprints/taxonomies/specialties_non_accredited/specialty_non_accredited.yaml:subject_matter',
        'fieldsets/uamsweb_common.yaml:subject_matter',
        'fieldsets/uamsweb_common_people_research.yaml:subject_matter',
    ]);

    // UAMS-Web/uams-statamic-archive#2823's decision table: training specialties name a clinical concept, other
    // specialties never do, and everything else can be either
    $clinicalOnly = ['specialties_acgme', 'specialties_aoa', 'specialties_non_accredited'];
    $subjectOnly = ['other_specialties'];

    foreach ($pickers as $name => $field) {
        $taxonomy = preg_match('#blueprints/taxonomies/([a-z_]+)/#', $name, $match) === 1 ? $match[1] : null;
        [$collections, $index] = match (true) {
            \in_array($taxonomy, $clinicalOnly, true) => [['clinical_specializations'], 'clinical_specializations'],
            \in_array($taxonomy, $subjectOnly, true) => [['subject_matter'], 'subject_matter'],
            default => [['subject_matter', 'clinical_specializations'], 'subject_concepts'],
        };

        expect($field['type'] ?? null)->toBe('entries', $name)
            ->and($field['collections'] ?? null)->toBe($collections, $name)
            ->and($field['select_across_sites'] ?? null)->toBeTrue()
            ->and($field['search_index'] ?? null)->toBe($index, $name)
            ->and($field)->not->toHaveKey('taxonomies');
    }

    // The combined index reads both collections
    expect(config('statamic.search.indexes.subject_concepts.searchables'))
        ->toBe(['collection:subject_matter', 'collection:clinical_specializations']);
});

it('resolves a subject chosen for a post on another site', function (): void {
    $this->requireContentCollection('subject_matter');

    $subject = subjectMatterEntriesBySlug()['award'];

    // Unsaved: a post authored on `health` pointing at the one `default` subject.
    /** @var StatamicEntry $post */
    $post = Entry::make();
    $post->collection('posts');
    $post->blueprint('post');
    $post->locale('health');
    $post->slug('zz-subject-matter-cross-site');
    $post->data(['title' => 'Subject probe', 'about' => [$subject->id()]]);

    /** @var Value $value */
    $value = $post->augmentedValue('about');
    /** @var Illuminate\Support\Collection<int, StatamicEntry> $resolved */
    $resolved = mustBe(StatusQueryBuilder::class, $value->value(), 'the about relation')->get();

    expect($resolved->map(fn (StatamicEntry $entry): string => mustBeString($entry->slug(), 'a subject slug'))->all())
        ->toBe(['award']);
});

it('emits a subject read from an augmented entries field, for both relationship tags', function (): void {
    $this->requireContentCollection('subject_matter');

    $subject = subjectMatterEntriesBySlug()['award'];

    // Unsaved series term whose `about` augments to a StatusQueryBuilder, as an entries field
    // does; the resolver has to materialize it rather than wrap the builder as one item
    /** @var Statamic\Taxonomies\Term $series */
    $series = Term::make();
    $series->taxonomy('audio_series');
    $series->slug('zz-subject-matter-series');
    $series->blueprint('audio_series');
    $series->set('title', 'Subject probe series');
    $series->set('about', [$subject->id()]);

    $about = mustBe(LocalizedTerm::class, $series->in('default'), 'the series term')->augmentedValue('about');

    expect(mustBe(Value::class, $about, 'the about value')->value())->toBeInstanceOf(StatusQueryBuilder::class);

    $keywords = (string) Antlers::parse(
        '{{ schema_subject_matter_keywords mode="direct" current_field="about" direct_scope="subject_matter" }}',
        ['about' => $about],
        true,
    );

    // The entry's title, then its sameAs URL: the fields a term contributed before
    expect(json_decode($keywords, true))->toBe([
        'award',
        'https://www.wikidata.org/wiki/Q618779',
    ]);

    $node = (string) Antlers::parse(
        '{{ schema_thing_from_relationship mode="direct" current_field="about" direct_scope="subject_matter" schema_object="f15f2efa-258e-4b16-aa4e-9010bce7514c" }}',
        ['about' => $about],
        true,
    );

    // The Subject Matter DefinedTerm template rendered from the entry
    expect($node)->toContain('DefinedTerm')
        ->and($node)->toContain('award');
});

it('emits one DefinedTerm per subject, each with the @id its term had', function (): void {
    $this->requireContentCollection('subject_matter');

    $subjects = subjectMatterEntriesBySlug();

    // Unsaved series term with two subjects in `about`, as `faces-of-uams` stores
    /** @var Statamic\Taxonomies\Term $series */
    $series = Term::make();
    $series->taxonomy('audio_series');
    $series->slug('zz-subject-matter-two-subjects');
    $series->blueprint('audio_series');
    $series->set('title', 'Two subjects');
    $series->set('about', [$subjects['uams-employee']->id(), $subjects['uams-worker']->id()]);

    $out = (string) Antlers::parse(
        '{{ schema_thing_from_relationship mode="direct" current_field="about" direct_scope="subject_matter" schema_object="f15f2efa-258e-4b16-aa4e-9010bce7514c" }}',
        ['about' => mustBe(LocalizedTerm::class, $series->in('default'), 'the series term')->augmentedValue('about')],
        true,
    );

    /** @var list<array<string, mixed>> $nodes */
    $nodes = mustBeArray(json_decode($out, true), 'the rendered nodes');

    // Subjects have no URL, so each needs its own @id or the tag's dedupe keeps only the
    // first. Each keeps the one its term had, built from the term route /subject-matter/{slug}.
    expect(array_column($nodes, 'name'))->toBe(['UAMS employee', 'UAMS worker'])
        ->and(array_column($nodes, '@id'))->toBe([
            SchemaJsonLdFragmentAtId::atIdFromRootRelativePath('/subject-matter/uams-employee'),
            SchemaJsonLdFragmentAtId::atIdFromRootRelativePath('/subject-matter/uams-worker'),
        ]);
});

it('names each clinical specialization in an about field beside a subject, each with its own @id (#2823)', function (): void {
    $this->requireContentCollection('subject_matter');
    $this->requireContentCollection('clinical_specializations');

    // Moved concepts now sit in the same `about` fields as subjects; specializations have
    // no URL either, so without their own @id the tag's dedupe would keep only the first
    $ids = [];
    foreach (['dental-providers' => 'clinical_specializations', '207sg0202x' => 'clinical_specializations', 'award' => 'subject_matter'] as $slug => $collection) {
        /** @var EntryQueryBuilder $query */
        $query = Entry::query();
        $query->where('collection', $collection);
        $query->where('slug', $slug);
        $ids[] = mustBe(StatamicEntry::class, $query->first(), $slug)->id();
    }

    /** @var Statamic\Taxonomies\Term $series */
    $series = Term::make();
    $series->taxonomy('audio_series');
    $series->slug('zz-subject-and-specializations');
    $series->blueprint('audio_series');
    $series->set('title', 'Subject and specializations');
    $series->set('about', $ids);

    $out = (string) Antlers::parse(
        '{{ schema_thing_from_relationship mode="direct" current_field="about" direct_scope="subject_matter" schema_object="f15f2efa-258e-4b16-aa4e-9010bce7514c" }}',
        ['about' => mustBe(LocalizedTerm::class, $series->in('default'), 'the series term')->augmentedValue('about')],
        true,
    );

    /** @var list<array<string, mixed>> $nodes */
    $nodes = mustBeArray(json_decode($out, true), 'the rendered nodes');

    expect(array_column($nodes, 'name'))->toBe(['Dental Providers', 'Clinical Biochemical Genetics Physician', 'award'])
        ->and(array_column($nodes, '@id'))->toBe([
            SchemaJsonLdFragmentAtId::atIdFromRootRelativePath('/clinical-specializations/dental-providers'),
            SchemaJsonLdFragmentAtId::atIdFromRootRelativePath('/clinical-specializations/207sg0202x'),
            SchemaJsonLdFragmentAtId::atIdFromRootRelativePath('/subject-matter/award'),
        ]);
});

it('keeps a clinical specialization stored as an id in a post\'s about, under the templates\' widened scope (#2823)', function (): void {
    $this->requireContentCollection('subject_matter');
    $this->requireContentCollection('clinical_specializations');

    $ids = [];
    foreach (['207x00000x' => 'clinical_specializations', 'award' => 'subject_matter'] as $slug => $collection) {
        /** @var EntryQueryBuilder $query */
        $query = Entry::query();
        $query->where('collection', $collection);
        $query->where('slug', $slug);
        $ids[] = mustBe(StatamicEntry::class, $query->first(), $slug)->id();
    }

    // Unsaved post holding raw ids, as a stored post does: the tag reads the entry in
    // context directly, so the ids reach the scope check unresolved
    /** @var StatamicEntry $post */
    $post = Entry::make();
    $post->collection('posts');
    $post->blueprint('post');
    $post->id('zz-subject-scope-probe');
    $post->slug('zz-subject-scope-probe');
    $post->data(['title' => 'Scope probe', 'about' => $ids]);

    $render = fn (string $scope): array => array_column(mustBeArray(json_decode((string) Antlers::parse(
        '{{ schema_thing_from_relationship mode="direct" current_field="about" direct_scope="'.$scope.'" schema_object="f15f2efa-258e-4b16-aa4e-9010bce7514c" }}',
        ['entry' => $post],
        true,
    ), true) ?? [], 'the rendered nodes'), 'name');

    // The control: the scope before UAMS-Web/uams-statamic-archive#2823 drops the specialization on this path
    expect($render('subject_matter'))->toBe(['award'])
        ->and($render('subject_matter|clinical_specializations'))->toBe(['Orthopaedic Surgery Physician', 'award']);
});

it('emits a specialty term\'s moved subject as the code-set node, with its code, link and alternate title (#2823)', function (): void {
    $this->requireContentCollection('clinical_specializations');

    // specialties_acgme::orthopaedic-surgery named the subject orthopaedic-surgery, which
    // carried a Wikidata link and an alternate name; UAMS-Web/uams-statamic-archive#2823 moved both to 207X00000X
    $term = mustBe(LocalizedTerm::class, Term::find('specialties_acgme::orthopaedic-surgery'), 'the orthopaedic surgery specialty');
    $node = app(SchemaOutputService::class)->buildStandaloneSchemaObjectNode('945499ea-b33d-43cc-b1f6-3a17e1bbacd7', $term);

    $about = mustBeArray($node['about'] ?? null, 'the specialty about');
    $about = array_is_list($about) ? $about : [$about];

    $first = mustBeArray($about[0] ?? null, 'the specialty about node');

    expect($about)->toHaveCount(1)
        ->and($first['@type'] ?? null)->toBe('DefinedTerm')
        ->and($first['name'] ?? null)->toBe('Orthopaedic Surgery')
        ->and($first['termCode'] ?? null)->toBe('207X00000X')
        ->and((array) ($first['sameAs'] ?? []))->toContain('https://www.wikidata.org/wiki/Q15218776')
        // The NUCC display name, then the subject's alternate name, now an alternate title
        ->and((array) ($first['alternateName'] ?? []))->toContain('Orthopaedic Surgery Physician', 'orthopedic surgery');
});
