<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\JsonLdCompositionProfiler;
use App\Services\SchemaTemplates\MedicalOrganizationSubtypeAtTypeResolver;
use App\Services\SchemaTemplates\PodcastEpisodeAtTypeResolver;
use App\Services\SchemaTemplates\SchemaClassificationsIdentifiersMerger;
use App\Services\SchemaTemplates\SchemaJsonLdAtIdDeduplication;
use App\Services\SchemaTemplates\SchemaJsonLdSchemaObjectEmissionCoordinator;
use App\Services\SchemaTemplates\SchemaOutputService;
use App\Services\SchemaTemplates\SchemaTemplateInstanceWatermarkBag;
use App\Services\SchemaTemplates\SchemaTemplatesCascadeService;
use Illuminate\Support\Collection;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Assets\Asset as StatamicAsset;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Contracts\Query\Builder as QueryBuilderContract;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Term as TermFacade;
use Statamic\Facades\URL;
use Statamic\Fields\Value;
use Statamic\Fieldtypes\Link\ArrayableLink;
use Statamic\Structures\Page;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Term as TaxonomyTerm;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(SchemaOutputService::class);

/**
 * Pins three value-shaping helpers of {@see SchemaOutputService}:
 * `normalizeLinkAugmentedValueForSchemaJsonLd()`, which turns an augmented Link
 * field into an absolute URL string; the bare `@id` inflation that replaces a
 * `{"@id": …}` reference with the schema object it names when no full node for
 * it exists in the graph; and `hydrateRawTermSlugValueToAugmentedValue()` with
 * the term lookups behind it, which turn stored term slugs into term models.
 * Collaborators are stubbed (the node builder on a partial mock, the Entry and
 * Term facades by partial facade mocks) so each branch is asserted on its own.
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaOutputServiceValueHydrationTest.php
 */

/**
 * The service as a partial mock, with the given methods stubbed.
 *
 * @param  array<string, Closure>  $stubs  Replacement bodies by method name.
 */
function sosValueService(array $stubs = []): SchemaOutputService&MockInterface
{
    /** @var SchemaOutputService&MockInterface $service */
    $service = Mockery::mock(SchemaOutputService::class, [
        app(SchemaTemplatesCascadeService::class),
        app(SchemaClassificationsIdentifiersMerger::class),
        app(SchemaJsonLdAtIdDeduplication::class),
        app(SchemaJsonLdSchemaObjectEmissionCoordinator::class),
        app(JsonLdCompositionProfiler::class),
        app(MedicalOrganizationSubtypeAtTypeResolver::class),
        app(PodcastEpisodeAtTypeResolver::class),
    ])
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    foreach ($stubs as $method => $body) {
        $service->shouldReceive($method)->andReturnUsing($body);
    }

    return $service;
}

/**
 * Call a non-public method of the service.
 */
function sosValueCall(SchemaOutputService $service, string $method, mixed ...$args): mixed
{
    return (new ReflectionMethod(SchemaOutputService::class, $method))->invoke($service, ...$args);
}

/**
 * Run the bare `@id` inflation over a graph and return the graph it leaves.
 *
 * @param  array<int, mixed>  $graph  Top-level `@graph` list.
 * @return array<int, mixed>
 */
function sosValueInflate(SchemaOutputService $service, array $graph, ?EntryContract $entity = null): array
{
    $emittedEntryIds = [];
    $emittedNodeIds = [];
    $materialized = [];
    $method = new ReflectionMethod(SchemaOutputService::class, 'inflateBareAtIdReferencesMissingFullNodesInGraph');
    $method->invokeArgs($service, [
        &$graph,
        $entity ?? sosValueEntry('ctx-1', 'pages'),
        ['slot-1'],
        &$emittedEntryIds,
        &$emittedNodeIds,
        &$materialized,
        new SchemaTemplateInstanceWatermarkBag,
    ]);

    return $graph;
}

/**
 * A mocked entry with an id and a collection handle.
 */
function sosValueEntry(string $id, string $collection): EntryModel&MockInterface
{
    /** @var EntryModel&MockInterface $entry */
    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('id')->andReturn($id);
    $entry->shouldReceive('collectionHandle')->andReturn($collection);

    return $entry;
}

/**
 * Make `Entry::find()` answer from the given map, and null for any other id.
 *
 * @param  array<string, EntryModel|null>  $entries  Found entries by id.
 * @param  list<string>  $lookups  Every id looked up, by reference.
 */
function sosValueFindEntries(array $entries, array &$lookups = []): void
{
    EntryFacade::partialMock()
        ->shouldReceive('find')
        ->andReturnUsing(function (string $id) use ($entries, &$lookups): ?EntryModel {
            $lookups[] = $id;

            return $entries[$id] ?? null;
        });
}

/**
 * A node builder stub that records each call and returns the given node.
 *
 * @param  list<array{entry_id: string, entity: mixed, graph: mixed, context: mixed}>  $calls  Collected calls, by reference.
 * @param  Closure(string): (array<mixed>|null)  $node  The node for an entry id.
 */
function sosValueBuilder(array &$calls, Closure $node): Closure
{
    return function (string $entryId, mixed $entity, array $graphEntryIds, array $visited, array &$emittedEntryIds, array &$emittedNodeIds, array &$materialized, array $antlersContext) use (&$calls, $node): ?array {
        $calls[] = ['entry_id' => $entryId, 'entity' => $entity, 'graph' => $graphEntryIds, 'context' => $antlersContext];

        return $node($entryId);
    };
}

/**
 * A mocked taxonomy term with an id.
 */
function sosValueTerm(mixed $id = 'tags::a'): TaxonomyTerm&MockInterface
{
    /** @var TaxonomyTerm&MockInterface $term */
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('id')->andReturn($id);

    return $term;
}

/**
 * A mocked localized term.
 */
function sosValueLocalizedTerm(): LocalizedTerm&MockInterface
{
    /** @var LocalizedTerm&MockInterface $term */
    $term = Mockery::mock(LocalizedTerm::class);

    return $term;
}

/**
 * Make the Term facade answer qualified ids from `$found` and bare slugs from `$matches`.
 *
 * @param  array<string, mixed>  $found  `Term::find()` results by qualified id.
 * @param  array<string, list<mixed>>  $matches  `Term::query()->where('slug', …)->get()` results by slug.
 * @param  list<string>  $lookups  Every reference looked up, by reference.
 */
function sosValueTerms(array $found = [], array $matches = [], array &$lookups = []): void
{
    $facade = TermFacade::partialMock();
    $facade->shouldReceive('find')->andReturnUsing(function (string $id) use ($found, &$lookups): mixed {
        $lookups[] = $id;

        return $found[$id] ?? null;
    });
    $record = function (string $slug) use (&$lookups): void {
        $lookups[] = $slug;
    };
    $facade->shouldReceive('query')->andReturnUsing(function () use ($matches, $record): object {
        return new class($matches, $record)
        {
            private string $slug = '';

            /**
             * @param  array<string, list<mixed>>  $matches
             * @param  Closure(string): void  $record
             */
            public function __construct(private array $matches, private Closure $record) {}

            public function where(string $column, string $value): self
            {
                expect($column)->toBe('slug');
                $this->slug = $value;
                ($this->record)($value);

                return $this;
            }

            /**
             * @return Collection<int, mixed>
             */
            public function get(): Collection
            {
                return collect($this->matches[$this->slug] ?? []);
            }
        };
    });
}

/**
 * Hydrate a stored terms value through the real service.
 */
function sosValueHydrate(mixed $present): mixed
{
    return sosValueCall(app(SchemaOutputService::class), 'hydrateRawTermSlugValueToAugmentedValue', $present);
}

/**
 * Hydrate a stored terms value that must come back as a collection, and return its items.
 *
 * @return array<int|string, mixed>
 */
function sosValueHydratedItems(mixed $present): array
{
    return mustBe(Collection::class, sosValueHydrate($present), 'the hydrated terms')->all();
}

/**
 * Normalize an augmented link value through the real service.
 */
function sosValueLink(mixed $resolved): mixed
{
    return sosValueCall(app(SchemaOutputService::class), 'normalizeLinkAugmentedValueForSchemaJsonLd', $resolved);
}

const SOS_VALUE_UUID = '0f8fad5b-d9cb-469f-a165-70867728950e';

// ---
// normalizeLinkAugmentedValueForSchemaJsonLd()
// ---

it('passes a value that is not an augmented link through unchanged', function (mixed $value): void {
    expect(sosValueLink($value))->toBe($value);
})->with([
    'string' => ['/about'],
    'null' => [null],
    'array' => [['url' => '/about']],
]);

it('reads a linked entry or page as its absolute URL', function (string $class): void {
    $target = Mockery::mock($class);
    $target->shouldReceive('absoluteUrl')->andReturn('https://example.test/about');
    $target->shouldReceive('url')->never();

    expect(sosValueLink(new ArrayableLink($target)))->toBe('https://example.test/about');
})->with([
    'entry' => [EntryModel::class],
    'page' => [Page::class],
    'asset' => [StatamicAsset::class],
]);

it('falls back to the link URL, as stored, when the target has no absolute URL', function (string $class, mixed $absolute, mixed $url, string $expected): void {
    $target = Mockery::mock($class);
    $target->shouldReceive('absoluteUrl')->andReturn($absolute);
    $target->shouldReceive('url')->andReturn($url);

    expect(sosValueLink(new ArrayableLink($target)))->toBe($expected);
})->with([
    'entry, empty absolute URL' => [EntryModel::class, '', '/about', '/about'],
    'entry, null absolute URL, null URL' => [EntryModel::class, null, null, ''],
    'page, empty absolute URL' => [Page::class, '', '/news', '/news'],
    'asset, empty absolute URL' => [StatamicAsset::class, '', '/assets/a.jpg', '/assets/a.jpg'],
    'asset, null absolute URL, numeric URL' => [StatamicAsset::class, null, 7, '7'],
    'asset, null absolute URL, array URL' => [StatamicAsset::class, null, ['x'], ''],
]);

it('reads any other linked object through the link URL', function (): void {
    $target = new class
    {
        public function url(): string
        {
            return 'https://elsewhere.test/page';
        }
    };

    expect(sosValueLink(new ArrayableLink($target)))->toBe('https://elsewhere.test/page');
});

it('keeps a scalar link that is empty, absolute or not a path', function (mixed $stored, string $expected): void {
    URL::partialMock()->shouldReceive('makeAbsolute')->never();

    expect(sosValueLink(new ArrayableLink($stored)))->toBe($expected);
})->with([
    'empty' => ['', ''],
    'null' => [null, ''],
    'array' => [['x'], ''],
    'https' => ['https://example.test/a', 'https://example.test/a'],
    'http' => ['http://example.test/a', 'http://example.test/a'],
    'mailto' => ['mailto:someone@example.test', 'mailto:someone@example.test'],
    'relative path' => ['about', 'about'],
    'number' => [42, '42'],
]);

it('makes a root-relative link absolute against the current site', function (): void {
    URL::partialMock()->shouldReceive('makeAbsolute')->once()->with('/about')->andReturn('https://example.test/about');

    expect(sosValueLink(new ArrayableLink('/about')))->toBe('https://example.test/about');
});

it('keeps a root-relative link when it cannot be made absolute', function (): void {
    URL::partialMock()->shouldReceive('makeAbsolute')->once()->with('/about')->andReturn('');

    expect(sosValueLink(new ArrayableLink('/about')))->toBe('/about');
});

// ---
// Bare @id inflation
// ---

it('inflates a bare reference to a schema object with the node built for it', function (): void {
    $calls = [];
    $lookups = [];
    $object = sosValueEntry(SOS_VALUE_UUID, 'schema_objects');
    sosValueFindEntries([SOS_VALUE_UUID => $object], $lookups);
    $service = sosValueService(['schemaObjectEntryToNode' => sosValueBuilder($calls, fn (string $id): array => ['@id' => 'https://example.test/#'.$id, '@type' => 'Thing', 'name' => 'Built'])]);
    $entity = sosValueEntry('ctx-1', 'pages');

    $graph = sosValueInflate($service, [
        ['@type' => 'WebPage', 'about' => ['@id' => 'https://example.test/#'.SOS_VALUE_UUID]],
    ], $entity);

    expect(data_get($graph, '0.about'))->toBe(['@id' => 'https://example.test/#'.SOS_VALUE_UUID, '@type' => 'Thing', 'name' => 'Built'])
        ->and($lookups)->toBe([SOS_VALUE_UUID])
        ->and($calls)->toHaveCount(1)
        ->and($calls[0]['entry_id'])->toBe(SOS_VALUE_UUID)
        ->and($calls[0]['entity'])->toBe($entity)
        ->and($calls[0]['graph'])->toBe(['slot-1'])
        ->and($calls[0]['context'])->toBe([]);
});

it('passes an instance index suffix to the node builder as Antlers context', function (): void {
    $calls = [];
    sosValueFindEntries([SOS_VALUE_UUID => sosValueEntry(SOS_VALUE_UUID, 'schema_objects')]);
    $service = sosValueService(['schemaObjectEntryToNode' => sosValueBuilder($calls, fn (string $id): array => ['@id' => 'https://example.test/#'.$id.'-2', '@type' => 'Thing'])]);

    $graph = sosValueInflate($service, [
        ['@type' => 'WebPage', 'about' => ['@id' => 'https://example.test/#'.SOS_VALUE_UUID.'-2']],
    ]);

    expect(data_get($graph, '0.about'))->toBe(['@id' => 'https://example.test/#'.SOS_VALUE_UUID.'-2', '@type' => 'Thing'])
        ->and($calls)->toHaveCount(1)
        ->and($calls[0]['entry_id'])->toBe(SOS_VALUE_UUID)
        ->and($calls[0]['context'])->toBe(['schema_template_instance_index' => 2]);
});

it('builds a repeated reference once, then leaves the repeat bare', function (): void {
    $calls = [];
    sosValueFindEntries([SOS_VALUE_UUID => sosValueEntry(SOS_VALUE_UUID, 'schema_objects')]);
    $service = sosValueService(['schemaObjectEntryToNode' => sosValueBuilder($calls, fn (string $id): array => ['@id' => 'https://example.test/#'.$id, '@type' => 'Thing'])]);
    $bare = ['@id' => 'https://example.test/#'.SOS_VALUE_UUID];

    $graph = sosValueInflate($service, [
        ['@type' => 'WebPage', 'about' => $bare, 'mentions' => [$bare]],
    ]);

    expect(data_get($graph, '0.about'))->toBe(['@id' => 'https://example.test/#'.SOS_VALUE_UUID, '@type' => 'Thing'])
        ->and(data_get($graph, '0.mentions'))->toBe([$bare])
        ->and($calls)->toHaveCount(1);
});

it('leaves a bare reference whose full node is already in the graph', function (): void {
    $lookups = [];
    sosValueFindEntries([SOS_VALUE_UUID => sosValueEntry(SOS_VALUE_UUID, 'schema_objects')], $lookups);
    $service = sosValueService(['schemaObjectEntryToNode' => fn (): never => throw new LogicException('not reached')]);
    $graph = [
        ['@id' => 'https://example.test/#'.SOS_VALUE_UUID, '@type' => 'Thing'],
        ['@type' => 'WebPage', 'about' => ['@id' => 'https://example.test/#'.SOS_VALUE_UUID]],
    ];

    expect(sosValueInflate($service, $graph))->toBe($graph)
        ->and($lookups)->toBe([]);
});

it('leaves a bare reference whose fragment is not a schema object id', function (string $atId): void {
    $lookups = [];
    sosValueFindEntries([], $lookups);
    $service = sosValueService(['schemaObjectEntryToNode' => fn (): never => throw new LogicException('not reached')]);
    $graph = [['@type' => 'WebPage', 'about' => ['@id' => $atId]]];

    expect(sosValueInflate($service, $graph))->toBe($graph)
        ->and($lookups)->toBe([]);
})->with([
    'named fragment' => ['https://example.test/#organization'],
    'empty fragment' => ['https://example.test/#'],
]);

it('leaves a bare reference to an entry that is missing or not a schema object', function (?string $collection): void {
    $lookups = [];
    sosValueFindEntries($collection === null ? [] : [SOS_VALUE_UUID => sosValueEntry(SOS_VALUE_UUID, $collection)], $lookups);
    $service = sosValueService(['schemaObjectEntryToNode' => fn (): never => throw new LogicException('not reached')]);
    $graph = [['@type' => 'WebPage', 'about' => ['@id' => 'https://example.test/#'.SOS_VALUE_UUID]]];

    expect(sosValueInflate($service, $graph))->toBe($graph)
        ->and($lookups)->toBe([SOS_VALUE_UUID]);
})->with([
    'missing' => [null],
    'another collection' => ['pages'],
]);

it('leaves a bare reference when the built node is empty or has no type', function (?array $built): void {
    $calls = [];
    sosValueFindEntries([SOS_VALUE_UUID => sosValueEntry(SOS_VALUE_UUID, 'schema_objects')]);
    $service = sosValueService(['schemaObjectEntryToNode' => sosValueBuilder($calls, fn (): ?array => $built)]);
    $bare = ['@id' => 'https://example.test/#'.SOS_VALUE_UUID];

    $graph = sosValueInflate($service, [
        ['@type' => 'WebPage', 'about' => $bare, 'mentions' => [$bare]],
    ]);

    expect(data_get($graph, '0.about'))->toBe($bare)
        ->and(data_get($graph, '0.mentions'))->toBe([$bare])
        ->and($calls)->toHaveCount(2);
})->with([
    'null' => [null],
    'no @type' => [['@id' => 'https://example.test/#'.SOS_VALUE_UUID, 'name' => 'Untyped']],
]);

it('inflates references nested in maps and lists, and inside a node it built', function (): void {
    $second = '1b4e28ba-2fa1-11d2-883f-0016d3cca427';
    $calls = [];
    sosValueFindEntries([
        SOS_VALUE_UUID => sosValueEntry(SOS_VALUE_UUID, 'schema_objects'),
        $second => sosValueEntry($second, 'schema_objects'),
    ]);
    $service = sosValueService(['schemaObjectEntryToNode' => sosValueBuilder($calls, fn (string $id): array => $id === SOS_VALUE_UUID
        ? ['@id' => 'https://example.test/#'.$id, '@type' => 'Thing', 'subjectOf' => [['@id' => 'https://example.test/#'.$second]]]
        : ['@id' => 'https://example.test/#'.$id, '@type' => 'CreativeWork'])]);

    $graph = sosValueInflate($service, [
        ['@type' => 'WebPage', 'mainEntity' => ['@type' => 'ItemList', 'itemListElement' => [['item' => ['@id' => 'https://example.test/#'.SOS_VALUE_UUID]]]]],
        'not a node',
    ]);

    expect(data_get($graph, '0.mainEntity.itemListElement.0.item'))->toBe([
        '@id' => 'https://example.test/#'.SOS_VALUE_UUID,
        '@type' => 'Thing',
        'subjectOf' => [['@id' => 'https://example.test/#'.$second, '@type' => 'CreativeWork']],
    ])
        ->and($graph[1])->toBe('not a node')
        ->and(array_column($calls, 'entry_id'))->toBe([SOS_VALUE_UUID, $second]);
});

it('does not treat a map with more than an @id, or with a type, as a bare reference', function (): void {
    $lookups = [];
    sosValueFindEntries([SOS_VALUE_UUID => sosValueEntry(SOS_VALUE_UUID, 'schema_objects')], $lookups);
    $service = sosValueService(['schemaObjectEntryToNode' => fn (): never => throw new LogicException('not reached')]);
    $graph = [['@type' => 'WebPage', 'about' => ['@id' => 'https://example.test/#'.SOS_VALUE_UUID, 'name' => 'Named']]];

    expect(sosValueInflate($service, $graph))->toBe($graph)
        ->and($lookups)->toBe([]);
});

// ---
// hydrateRawTermSlugValueToAugmentedValue(): values already resolved
// ---

it('returns the results of a query builder', function (): void {
    $term = sosValueTerm();
    $builder = Mockery::mock(QueryBuilderContract::class);
    $builder->shouldReceive('get')->once()->andReturn(collect([$term]));

    expect(sosValueHydratedItems($builder))->toBe([$term]);
});

it('wraps a single term model in a collection', function (Closure $make): void {
    $term = $make();

    $items = sosValueHydratedItems(new Value($term));

    expect($items)->toBe([$term]);
})->with([
    'localized term' => [fn (): LocalizedTerm => sosValueLocalizedTerm()],
    'term' => [fn (): TaxonomyTerm => sosValueTerm()],
]);

it('returns a collection of terms, or an empty collection, as it is', function (Closure $make): void {
    $collection = $make();
    sosValueTerms();

    expect(sosValueHydrate($collection))->toBe($collection);
})->with([
    'empty' => [fn (): Collection => collect()],
    'terms' => [fn (): Collection => collect([sosValueTerm(), 'x'])],
    'localized terms' => [fn (): Collection => collect([sosValueLocalizedTerm()])],
    'terms in values' => [fn (): Collection => collect([new Value(sosValueTerm())])],
]);

// ---
// hydrateRawTermSlugValueToAugmentedValue(): stored slugs
// ---

it('hydrates a collection of slugs, skipping empty and non-string items', function (): void {
    $a = sosValueTerm('tags::a');
    $b = sosValueTerm('topics::b');
    $lookups = [];
    sosValueTerms(['tags::a' => $a], ['b' => [$b]], $lookups);

    $items = sosValueHydratedItems(collect(['tags::a', new Value('b'), '', 5, new Value(null)]));

    expect($items)->toBe([$a, $b])
        ->and($lookups)->toBe(['tags::a', 'b']);
});

it('returns a collection as it is when no item is a slug, or no slug resolves', function (array $items): void {
    $collection = collect($items);
    sosValueTerms();

    expect(sosValueHydrate($collection))->toBe($collection);
})->with([
    'no slugs' => [[5, '', null]],
    'unresolved slugs' => [['missing']],
]);

it('hydrates a single stored slug, or returns it when it does not resolve', function (): void {
    $a = sosValueTerm('tags::a');
    sosValueTerms(['tags::a' => $a]);

    expect(sosValueHydratedItems('tags::a'))->toBe([$a])
        ->and(sosValueHydrate('tags::missing'))->toBe('tags::missing');
});

it('returns a value that is neither a slug nor a list of them as it is', function (mixed $value): void {
    sosValueTerms();

    expect(sosValueHydrate($value))->toBe($value);
})->with([
    'empty string' => [''],
    'empty list' => [[]],
    'map' => [['a' => 'tags::a']],
    'number' => [5],
    'null' => [null],
    'list starting with null' => [[null, 'tags::a']],
    'list starting with a number' => [[5, 'tags::a']],
    'list with an empty slug' => [['tags::a', '']],
    'list with a number after a slug' => [['tags::a', 5]],
]);

it('keeps only the term models of a list that starts with one', function (): void {
    $localized = sosValueLocalizedTerm();
    $term = sosValueTerm();

    $items = sosValueHydratedItems([new Value($localized), $term, 'tags::a', new Value('tags::b'), 5]);

    expect($items)->toBe([$localized, $term]);
});

it('hydrates a list of slugs, or returns it when none resolves', function (): void {
    $a = sosValueTerm('tags::a');
    $b = sosValueTerm('tags::b');
    sosValueTerms(['tags::a' => $a, 'tags::b' => $b]);

    expect(sosValueHydratedItems([new Value('tags::a'), 'tags::b', 'tags::missing']))->toBe([$a, $b])
        ->and(sosValueHydrate(['tags::missing']))->toBe(['tags::missing']);
});

// ---
// Resolving one stored slug
// ---

it('skips the slug "0" without looking it up', function (): void {
    $lookups = [];
    sosValueTerms([], [], $lookups);

    expect(sosValueHydrate(['0']))->toBe(['0'])
        ->and($lookups)->toBe([]);
});

it('keeps a localized term that a qualified id finds', function (): void {
    $localized = sosValueLocalizedTerm();
    sosValueTerms(['tags::loc' => $localized]);

    expect(sosValueHydratedItems(['tags::loc', 'tags::missing']))->toBe([$localized]);
});

it('resolves a bare slug to its only match, and to nothing without one', function (): void {
    $a = sosValueTerm('tags::a');
    sosValueTerms([], ['a' => [$a], 'none' => []]);

    expect(sosValueHydratedItems(['a', 'none']))->toBe([$a]);
});

it('resolves a bare slug shared across taxonomies to the match with the lowest id', function (): void {
    $topics = sosValueTerm('topics::shared');
    $tags = sosValueTerm('tags::shared');
    sosValueTerms([], ['shared' => [$topics, $tags]]);

    expect(sosValueHydratedItems(['shared']))->toBe([$tags]);
});

it('sorts a match with no readable id first, which resolves to nothing', function (mixed $unreadable): void {
    $tags = sosValueTerm('tags::shared');
    sosValueTerms([], ['shared' => [$tags, $unreadable]]);

    expect(sosValueHydrate(['shared']))->toBe(['shared']);
})->with([
    'not an object' => ['shared'],
    'an object without an id' => [new stdClass],
]);

it('sorts a term with a non-string id first, and resolves to it', function (): void {
    $tags = sosValueTerm('tags::shared');
    $numbered = sosValueTerm(7);
    sosValueTerms([], ['shared' => [$tags, $numbered]]);

    expect(sosValueHydratedItems(['shared']))->toBe([$numbered]);
});
