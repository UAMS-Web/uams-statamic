<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaJsonLdAtIdDeduplication;
use App\Services\SchemaTemplates\SchemaJsonLdSchemaObjectEmissionCoordinator;
use App\Services\SchemaTemplates\SchemaOutputService;
use App\Services\SchemaTemplates\SchemaThingRefsResolver;
use Illuminate\Support\Collection;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Entries\EntryCollection;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Term;
use Statamic\Fields\Value;
use Statamic\Globals\GlobalSet as GlobalSetModel;
use Statamic\Globals\Variables;
use Statamic\Query\OrderedQueryBuilder;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Term as TaxonomyTerm;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(SchemaThingRefsResolver::class);

/**
 * Pins every resolution mode of the `schema_thing_refs` resolver against
 * in-memory doubles: the Entry, Term and GlobalSet facades are mocked, the
 * canonical @id comes from a mocked {@see SchemaOutputService}, and the @id
 * reference builder and dedupe helper are the real classes.
 *
 * Every mutant of the class is killed by this file alone, without
 * --covered-only (UAMS-Web/uams-statamic#320).
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaThingRefsResolverTest.php
 */

/**
 * Build the resolver over a mocked output service whose canonical @id is
 * `https://example.test/#<entry id>`, or empty for the id `blank`.
 */
function refsResolver(): SchemaThingRefsResolver
{
    $output = Mockery::mock(SchemaOutputService::class);
    $output->shouldReceive('getCanonicalAtIdForSchemaEntry')
        ->andReturnUsing(function (EntryModel $entry): string {
            $id = $entry->id();
            $id = \is_scalar($id) ? (string) $id : '';

            return match ($id) {
                'blank' => '',
                'same-a', 'same-b' => 'https://example.test/#same',
                default => 'https://example.test/#'.$id,
            };
        });

    $dedupe = new SchemaJsonLdAtIdDeduplication;

    return new SchemaThingRefsResolver($output, $dedupe, new SchemaJsonLdSchemaObjectEmissionCoordinator($dedupe));
}

/**
 * Build a mocked entry with an id, a collection and field values.
 *
 * @param  mixed  $id  Value id() returns.
 * @param  array<string, mixed>  $values  Field values value() returns by handle.
 */
function refsEntry(mixed $id, string $collection = 'schema_objects', array $values = []): EntryModel
{
    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('id')->andReturn($id);
    $entry->shouldReceive('collectionHandle')->andReturn($collection);
    $entry->shouldReceive('value')->andReturnUsing(fn (string $handle): mixed => $values[$handle] ?? null);

    return $entry;
}

/**
 * Serve `Entry::whereInId()` from a fixed set of schema_objects entries and
 * record each id list it is asked for.
 *
 * @param  list<mixed>  $entries  Entries (or other values) the repository holds.
 * @return ArrayObject<int, array<mixed>> The id lists requested, in call order.
 */
function refsRepository(array $entries): ArrayObject
{
    /** @var ArrayObject<int, array<mixed>> $requests */
    $requests = new ArrayObject;

    EntryFacade::shouldReceive('whereInId')->andReturnUsing(function (array $ids) use ($entries, $requests): EntryCollection {
        $requests[] = $ids;

        return new EntryCollection(array_values(array_filter(
            $entries,
            fn (mixed $entry): bool => ! $entry instanceof EntryModel || \in_array($entry->id(), $ids, false)
        )));
    });

    return $requests;
}

/**
 * The JSON the resolver emits for a list of entry ids.
 *
 * @param  list<string>  $ids  Entry ids in output order.
 */
function refsJson(array $ids): string
{
    return json_encode(
        array_map(fn (string $id): array => ['@id' => 'https://example.test/#'.$id], $ids),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );
}

/**
 * Build a mocked taxonomy term whose default-locale `field` value is fixed.
 *
 * @param  array<string, mixed>  $values  Field values by handle.
 */
function refsTerm(array $values): TaxonomyTerm
{
    $localized = Mockery::mock(LocalizedTerm::class);
    $localized->shouldReceive('value')->andReturnUsing(fn (string $handle): mixed => $values[$handle] ?? null);

    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('inDefaultLocale')->andReturn($localized);

    return $term;
}

/**
 * Wrap a term in a localized term whose term() returns it.
 */
function refsLocalized(mixed $term): LocalizedTerm
{
    $localized = Mockery::mock(LocalizedTerm::class);
    $localized->shouldReceive('term')->andReturn($term);

    return $localized;
}

/**
 * Build a mocked ordered query whose get() returns the given items.
 *
 * @param  list<mixed>  $items  Items the query yields.
 */
function refsQuery(array $items): OrderedQueryBuilder
{
    $query = Mockery::mock(OrderedQueryBuilder::class);
    $query->shouldReceive('get')->andReturn(collect($items));

    return $query;
}

// ---
// Encoding: one batched load, encounter order, dedupe by canonical @id
// ---

it('loads the ids once, deduplicated, and emits refs in encounter order', function (): void {
    $requests = refsRepository([refsEntry('a'), refsEntry('b'), refsEntry('c')]);

    $json = refsResolver()->resolveFromEntryEntriesField(['f' => ['c', 'a', '', 'c', 'b']], ['field' => 'f']);

    expect($json)->toBe(refsJson(['c', 'a', 'b']))
        ->and($requests->getArrayCopy())->toBe([['c', 'a', 'b']]);
});

it('skips ids that load nothing, outside schema_objects, or with an unusable @id', function (): void {
    refsRepository([
        refsEntry('a'),
        refsEntry('page', 'pages'),
        refsEntry('blank'),
        'not an entry',
        refsEntry('b'),
    ]);

    $json = refsResolver()->resolveFromEntryEntriesField(['f' => ['missing', 'page', 'a', 'blank', 'b']], ['field' => 'f']);

    expect($json)->toBe(refsJson(['a', 'b']));
});

it('keeps one ref when two entries share a canonical @id', function (): void {
    refsRepository([refsEntry('same-a'), refsEntry('same-b'), refsEntry('z')]);

    $json = refsResolver()->resolveFromEntryEntriesField(['f' => ['same-a', 'z', 'same-b']], ['field' => 'f']);

    expect($json)->toBe('[{"@id":"https://example.test/#same"},{"@id":"https://example.test/#z"}]');
});

it('encodes slashes and unicode unescaped', function (): void {
    refsRepository([refsEntry('é/1')]);

    expect(refsResolver()->resolveFromEntryEntriesField(['f' => 'é/1'], ['field' => 'f']))
        ->toBe('[{"@id":"https://example.test/#é/1"}]');
});

it('emits an empty array when a ref cannot be encoded', function (): void {
    refsRepository([refsEntry("\xB1\x31")]);

    expect(refsResolver()->resolveFromEntryEntriesField(['f' => "\xB1\x31"], ['field' => 'f']))->toBe('[]');
});

it('indexes an entry with an integer id by its string form', function (): void {
    refsRepository([refsEntry(7)]);

    expect(refsResolver()->resolveFromEntryEntriesField(['f' => '7'], ['field' => 'f']))->toBe(refsJson(['7']));
});

it('does not query the repository when every id is empty', function (): void {
    EntryFacade::shouldReceive('whereInId')->never();

    expect(refsResolver()->resolveFromEntryEntriesField(['f' => ['', '']], ['field' => 'f']))->toBe('[]');
});

// ---
// Entries mode: context field ids
// ---

it('returns an empty array in entries mode without a usable field', function (mixed $field): void {
    EntryFacade::shouldReceive('whereInId')->never();

    expect(refsResolver()->resolveFromEntryEntriesField(['f' => ['a']], ['field' => $field]))->toBe('[]');
})->with([
    'missing' => [null],
    'empty' => [''],
    'delimiters only' => [' | '],
    'not a string' => [['f']],
]);

it('falls back across piped field handles to the first with ids', function (): void {
    refsRepository([refsEntry('a'), refsEntry('b')]);

    $json = refsResolver()->resolveFromEntryEntriesField(['empty' => [], 'f' => ['a'], 'g' => ['b']], ['field' => 'missing|empty|f|g']);

    expect($json)->toBe(refsJson(['a']));
});

it('returns an empty array in entries mode when no handle has ids', function (): void {
    EntryFacade::shouldReceive('whereInId')->never();

    expect(refsResolver()->resolveFromEntryEntriesField(['f' => null, 'g' => ''], ['field' => 'f|g']))->toBe('[]');
});

it('caps the entries mode ids at a positive entry limit', function (mixed $limit, array $expected): void {
    /** @var list<string> $expected */
    refsRepository([refsEntry('a'), refsEntry('b'), refsEntry('c')]);

    $json = refsResolver()->resolveFromEntryEntriesField(['f' => ['a', 'b', 'c']], ['field' => 'f', 'entry_limit' => $limit]);

    expect($json)->toBe(refsJson($expected));
})->with([
    'integer' => [2, ['a', 'b']],
    'one' => [1, ['a']],
    'one string' => ['1', ['a']],
    'digit string' => ['2', ['a', 'b']],
    'zero' => [0, ['a', 'b', 'c']],
    'negative' => [-1, ['a', 'b', 'c']],
    'zero string' => ['0', ['a', 'b', 'c']],
    'empty string' => ['', ['a', 'b', 'c']],
    'non-digit string' => ['2x', ['a', 'b', 'c']],
    'signed string' => ['-1', ['a', 'b', 'c']],
    'float' => [1.0, ['a', 'b', 'c']],
]);

it('reads entries mode ids through dotted paths', function (array $context, string $field): void {
    /** @var array<string, mixed> $context */
    refsRepository([refsEntry('a')]);

    expect(refsResolver()->resolveFromEntryEntriesField($context, ['field' => $field]))->toBe(refsJson(['a']));
})->with([
    'nested array' => [['outer' => ['inner' => ['a']]], 'outer.inner'],
    'Value at the root' => [['outer' => new Value(['inner' => 'a'])], 'outer.inner'],
    'Value at the leaf' => [['f' => new Value(['a'])], 'f'],
    'entry segment' => [['outer' => refsEntry('host', 'pages', ['inner' => ['a']])], 'outer.inner'],
    'collection segment' => [['outer' => collect(['inner' => 'a'])], 'outer.inner'],
    'entry, then array' => [['outer' => refsEntry('host', 'pages', ['mid' => ['inner' => ['a']]])], 'outer.mid.inner'],
    'collection, then array' => [['outer' => collect(['mid' => ['inner' => ['a']]])], 'outer.mid.inner'],
    'array, then array' => [['outer' => ['mid' => ['inner' => ['a']]]], 'outer.mid.inner'],
]);

it('reads nothing through a dotted path that does not resolve', function (array $context, string $field): void {
    /** @var array<string, mixed> $context */
    EntryFacade::shouldReceive('whereInId')->never();

    expect(refsResolver()->resolveFromEntryEntriesField($context, ['field' => $field]))->toBe('[]');
})->with([
    'missing array key' => [['outer' => ['other' => 'a']], 'outer.inner'],
    'missing collection key' => [['outer' => collect(['other' => 'a'])], 'outer.inner'],
    'scalar segment' => [['outer' => 'a'], 'outer.inner'],
    'object segment' => [['outer' => new stdClass], 'outer.inner'],
]);

// ---
// Entries field values: every shape an entries field can hold
// ---

it('normalizes every entries field value shape to ids', function (Closure $raw, array $expected): void {
    /** @var list<string> $expected */
    // The repository also holds entries an unusable id would reach
    $requests = refsRepository([refsEntry('a'), refsEntry('b'), refsEntry(9), refsEntry(5)]);

    expect(refsResolver()->resolveFromEntryEntriesField(['f' => $raw()], ['field' => 'f']))->toBe(refsJson($expected))
        ->and($requests->getArrayCopy())->toBe([$expected]);
})->with([
    'id string' => [fn (): mixed => 'a', ['a']],
    'entry' => [fn (): EntryModel => refsEntry('a'), ['a']],
    'list of entries and ids' => [fn (): array => [refsEntry('a'), 'b', '', 5, refsEntry(''), refsEntry(9), null], ['a', 'b']],
    'ordered query' => [fn (): OrderedQueryBuilder => refsQuery([refsEntry('b'), 'a', refsEntry(''), refsEntry(9), refsEntry('a')]), ['b', 'a']],
    'Value of a list' => [fn (): mixed => new Value(['b', 'a']), ['b', 'a']],
    'Value of a Value' => [fn (): Value => new Value(new Value('a')), ['a']],
]);

it('reads no ids from an unusable entries field value', function (Closure $raw): void {
    EntryFacade::shouldReceive('whereInId')->never();

    expect(refsResolver()->resolveFromEntryEntriesField(['f' => $raw()], ['field' => 'f']))->toBe('[]');
})->with([
    'integer' => [fn (): mixed => 5],
    'object' => [fn (): mixed => new stdClass],
    'entry with an empty id' => [fn (): EntryModel => refsEntry('')],
    'entry with a non-string id' => [fn (): EntryModel => refsEntry(5)],
    'Value of null' => [fn (): mixed => new Value(null)],
    'empty list' => [fn (): mixed => []],
]);

// ---
// Global mode
// ---

/**
 * Register a mocked global set whose variables hold the given values.
 *
 * @param  array<string, mixed>  $values  Variable values by handle.
 * @param  string|null  $site  The site in() must be asked for, or null for the current site.
 */
function refsGlobal(array $values, ?string $site = null): void
{
    $variables = Mockery::mock(Variables::class);
    $variables->shouldReceive('get')->andReturnUsing(fn (string $handle): mixed => $values[$handle] ?? null);

    $set = Mockery::mock(GlobalSetModel::class);
    if ($site === null) {
        $set->shouldReceive('inCurrentSite')->once()->andReturn($variables);
        $set->shouldNotReceive('in');
    } else {
        $set->shouldReceive('in')->once()->with($site)->andReturn($variables);
        $set->shouldNotReceive('inCurrentSite');
    }

    GlobalSet::shouldReceive('findByHandle')->with('site_settings')->andReturn($set);
}

it('returns an empty array in global mode without a usable global or field', function (array $params): void {
    /** @var array<string, mixed> $params */
    GlobalSet::shouldReceive('findByHandle')->never();

    expect(refsResolver()->resolveFromGlobalEntriesField($params))->toBe('[]');
})->with([
    'no global' => [['field' => 'f']],
    'empty global' => [['global' => '', 'field' => 'f']],
    'non-string global' => [['global' => 5, 'field' => 'f']],
    'no field' => [['global' => 'site_settings']],
    'empty field' => [['global' => 'site_settings', 'field' => ' | ']],
]);

it('returns an empty array in global mode when the set is missing', function (): void {
    GlobalSet::shouldReceive('findByHandle')->with('site_settings')->andReturn(null);
    EntryFacade::shouldReceive('whereInId')->never();

    expect(refsResolver()->resolveFromGlobalEntriesField(['global' => 'site_settings', 'field' => 'f']))->toBe('[]');
});

it('reads the global variables of the current site, falling back across handles', function (mixed $site): void {
    refsGlobal(['empty' => new Value(null), 'f' => new Value(['b', 'a']), 'g' => ['c']]);
    refsRepository([refsEntry('a'), refsEntry('b'), refsEntry('c')]);

    $json = refsResolver()->resolveFromGlobalEntriesField(['global' => 'site_settings', 'field' => 'empty|f|g', 'site' => $site]);

    expect($json)->toBe(refsJson(['b', 'a']));
})->with([
    'no site' => [null],
    'empty site' => [''],
    'non-string site' => [5],
]);

it('reads the global variables of a named site', function (): void {
    refsGlobal(['f' => 'a'], 'health');
    refsRepository([refsEntry('a')]);

    expect(refsResolver()->resolveFromGlobalEntriesField(['global' => 'site_settings', 'field' => 'f', 'site' => 'health']))
        ->toBe(refsJson(['a']));
});

it('caps the global ids at the entry limit', function (): void {
    refsGlobal(['f' => ['a', 'b', 'c']]);
    refsRepository([refsEntry('a'), refsEntry('b'), refsEntry('c')]);

    expect(refsResolver()->resolveFromGlobalEntriesField(['global' => 'site_settings', 'field' => 'f', 'entry_limit' => 2]))
        ->toBe(refsJson(['a', 'b']));
});

it('returns an empty array in global mode when no handle has ids', function (): void {
    refsGlobal(['f' => null]);
    EntryFacade::shouldReceive('whereInId')->never();

    expect(refsResolver()->resolveFromGlobalEntriesField(['global' => 'site_settings', 'field' => 'f|g']))->toBe('[]');
});

// ---
// Linked entries mode
// ---

/**
 * Serve `Entry::find()` from a fixed map of linked entries.
 *
 * @param  array<string, mixed>  $linked  Values find() returns by id.
 */
function refsLinked(array $linked): void
{
    EntryFacade::shouldReceive('find')->andReturnUsing(fn (string $id): mixed => $linked[$id] ?? null);
}

it('returns an empty array in linked mode without a usable field', function (): void {
    EntryFacade::shouldReceive('find')->never();

    expect(refsResolver()->resolveFromLinkedEntries(['f' => ['l1']], ['field' => ' ']))->toBe('[]');
});

it('reads each linked entry schema object field in linked order', function (mixed $linkedField, string $handle): void {
    refsLinked([
        'l1' => refsEntry('l1', 'locations', [$handle => ['b', 'a']]),
        'l2' => refsEntry('l2', 'locations', [$handle => 'c']),
        'l3' => refsEntry('l3', 'locations', [$handle => 'd']),
    ]);
    refsRepository([refsEntry('a'), refsEntry('b'), refsEntry('c')]);

    $json = refsResolver()->resolveFromLinkedEntries(
        ['empty' => [], 'f' => ['l1', 'missing', 'l2'], 'g' => ['l3']],
        ['field' => 'empty|f|g', 'linked_entries_field' => $linkedField]
    );

    expect($json)->toBe(refsJson(['b', 'a', 'c']));
})->with([
    'default field' => [null, 'schema_object'],
    'empty field' => ['', 'schema_object'],
    'non-string field' => [5, 'schema_object'],
    'named field' => ['org', 'org'],
]);

it('caps linked entries and ids per linked entry', function (): void {
    refsLinked([
        'l1' => refsEntry('l1', 'locations', ['schema_object' => ['a', 'b']]),
        'l2' => refsEntry('l2', 'locations', ['schema_object' => ['c', 'd']]),
        'l3' => refsEntry('l3', 'locations', ['schema_object' => ['e']]),
    ]);
    refsRepository(array_map(fn (string $id): EntryModel => refsEntry($id), ['a', 'b', 'c', 'd', 'e']));

    $json = refsResolver()->resolveFromLinkedEntries(['f' => ['l1', 'l2', 'l3']], ['field' => 'f', 'linked_limit' => 2, 'entry_limit' => 1]);

    expect($json)->toBe(refsJson(['a', 'c']));
});

it('returns an empty array in linked mode when nothing links', function (): void {
    EntryFacade::shouldReceive('find')->never();
    EntryFacade::shouldReceive('whereInId')->never();

    expect(refsResolver()->resolveFromLinkedEntries(['f' => null], ['field' => 'f']))->toBe('[]');
});

// ---
// Terms mode
// ---

it('returns an empty array in terms mode without a usable field', function (): void {
    Term::shouldReceive('find')->never();

    expect(refsResolver()->resolveFromTaxonomyTerms(['f' => ['slug']], ['field' => null]))->toBe('[]');
});

it('returns an empty array in terms mode when no handle has terms', function (): void {
    Term::shouldReceive('find')->never();
    EntryFacade::shouldReceive('whereInId')->never();

    expect(refsResolver()->resolveFromTaxonomyTerms(['f' => null, 'g' => new Value(null)], ['field' => 'f|g|missing']))->toBe('[]');
});

it('resolves slugs in the configured taxonomy', function (mixed $taxonomy, string $expected): void {
    Term::shouldReceive('find')->with($expected.'::little-rock')->andReturn(refsTerm(['schema_object' => ['a']]));
    refsRepository([refsEntry('a')]);

    expect(refsResolver()->resolveFromTaxonomyTerms(['f' => 'little-rock'], ['field' => 'f', 'taxonomy' => $taxonomy]))
        ->toBe(refsJson(['a']));
})->with([
    'default' => [null, 'geographic_locations'],
    'empty' => ['', 'geographic_locations'],
    'non-string' => [5, 'geographic_locations'],
    'named' => ['regions', 'regions'],
]);

it('reads the configured term entries field', function (mixed $field, string $handle): void {
    Term::shouldReceive('find')->andReturn(refsTerm([$handle => ['a'], 'schema_object' => ['b']]));
    refsRepository([refsEntry('a'), refsEntry('b')]);

    $expected = $handle === 'schema_object' ? ['b'] : ['a'];

    expect(refsResolver()->resolveFromTaxonomyTerms(['f' => 'slug'], ['field' => 'f', 'term_entries_field' => $field]))
        ->toBe(refsJson($expected));
})->with([
    'default' => [null, 'schema_object'],
    'empty' => ['', 'schema_object'],
    'non-string' => [5, 'schema_object'],
    'named' => ['org', 'org'],
]);

it('resolves every term item shape, in term order', function (): void {
    $found = [
        'geographic_locations::one' => refsTerm(['schema_object' => ['a']]),
        'geographic_locations::two' => refsLocalized(refsTerm(['schema_object' => ['b']])),
        'geographic_locations::three' => refsLocalized('not a term'),
        'geographic_locations::four' => Mockery::mock(Statamic\Contracts\Taxonomies\Term::class),
    ];
    Term::shouldReceive('find')->andReturnUsing(fn (string $id): mixed => $found[$id] ?? null);
    refsRepository(array_map(fn (string $id): EntryModel => refsEntry($id), ['a', 'b', 'c', 'd', 'e']));

    $items = [
        'one',
        ['slug' => 'two'],
        'three',
        'four',
        'missing',
        refsLocalized(refsTerm(['schema_object' => 'c'])),
        refsLocalized(null),
        refsTerm(['schema_object' => ['d']]),
        new Value(refsTerm(['schema_object' => ['e']])),
        ['slug' => ''],
        ['slug' => 5],
        ['title' => 'no slug'],
        '',
        5,
    ];

    expect(refsResolver()->resolveFromTaxonomyTerms(['f' => $items], ['field' => 'f']))->toBe(refsJson(['a', 'b', 'c', 'd', 'e']));
});

it('reads terms from a collection, an ordered query, or a single item', function (Closure $value): void {
    Term::shouldReceive('find')->never();
    refsRepository([refsEntry('a'), refsEntry('b')]);

    $first = refsTerm(['schema_object' => 'a']);
    $second = refsTerm(['schema_object' => 'b']);

    expect(refsResolver()->resolveFromTaxonomyTerms(['f' => $value($first, $second)], ['field' => 'f']))
        ->toBe(refsJson(['a', 'b']));
})->with([
    'collection with keys' => [fn (TaxonomyTerm $a, TaxonomyTerm $b): Collection => collect(['x' => $a, 'y' => $b])],
    'ordered query' => [fn (TaxonomyTerm $a, TaxonomyTerm $b): OrderedQueryBuilder => refsQuery([$a, $b])],
    'Value of a list' => [fn (TaxonomyTerm $a, TaxonomyTerm $b): Value => new Value([$a, $b])],
    'keyed array' => [fn (TaxonomyTerm $a, TaxonomyTerm $b): array => ['x' => $a, 'y' => $b]],
]);

it('reads a single term item', function (): void {
    refsRepository([refsEntry('a')]);

    expect(refsResolver()->resolveFromTaxonomyTerms(['f' => refsTerm(['schema_object' => 'a'])], ['field' => 'f']))
        ->toBe(refsJson(['a']));
});

it('caps terms and ids per term', function (): void {
    refsRepository(array_map(fn (string $id): EntryModel => refsEntry($id), ['a', 'b', 'c', 'd', 'e']));

    $terms = [
        refsTerm(['schema_object' => ['a', 'b']]),
        refsTerm(['schema_object' => ['c', 'd']]),
        refsTerm(['schema_object' => ['e']]),
    ];

    expect(refsResolver()->resolveFromTaxonomyTerms(['f' => $terms], ['field' => 'f', 'term_limit' => '2', 'entry_limit' => 1]))
        ->toBe(refsJson(['a', 'c']));
});

it('skips a term whose fields cannot be read', function (): void {
    refsRepository([refsEntry('a')]);

    $broken = Mockery::mock(TaxonomyTerm::class);
    $broken->shouldReceive('inDefaultLocale')->andThrow(new RuntimeException('no blueprint'));

    expect(refsResolver()->resolveFromTaxonomyTerms(['f' => [$broken, refsTerm(['schema_object' => 'a'])]], ['field' => 'f']))
        ->toBe(refsJson(['a']));
});

it('falls back across piped term field handles', function (): void {
    refsRepository([refsEntry('a'), refsEntry('b')]);

    $context = ['f' => [], 'g' => [refsTerm(['schema_object' => 'a'])], 'h' => [refsTerm(['schema_object' => 'b'])]];

    expect(refsResolver()->resolveFromTaxonomyTerms($context, ['field' => 'f|g|h']))->toBe(refsJson(['a']));
});

it('unwraps a nested Value in a global variable', function (): void {
    refsGlobal(['f' => new Value(new Value('a'))]);
    refsRepository([refsEntry('a')]);

    expect(refsResolver()->resolveFromGlobalEntriesField(['global' => 'site_settings', 'field' => 'f']))->toBe(refsJson(['a']));
});

it('falls back past a term field that is absent', function (): void {
    refsRepository([refsEntry('a')]);

    expect(refsResolver()->resolveFromTaxonomyTerms(['f' => null, 'g' => [refsTerm(['schema_object' => 'a'])]], ['field' => 'f|g']))
        ->toBe(refsJson(['a']));
});

it('never looks up an empty slug', function (): void {
    // A term stored under the empty slug would be emitted if the lookup ran
    Term::shouldReceive('find')->andReturnUsing(fn (string $id): mixed => $id === 'geographic_locations::'
        ? refsTerm(['schema_object' => 'z'])
        : null);
    refsRepository([refsEntry('z')]);

    expect(refsResolver()->resolveFromTaxonomyTerms(['f' => [['slug' => ''], '']], ['field' => 'f']))->toBe('[]');
});

it('rejects a localized term whose term is not a taxonomy term', function (): void {
    Term::shouldReceive('find')->never();

    expect(refsResolver()->resolveFromTaxonomyTerms(['f' => [refsLocalized(new stdClass)]], ['field' => 'f']))->toBe('[]');
});
