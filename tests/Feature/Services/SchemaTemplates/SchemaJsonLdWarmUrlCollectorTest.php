<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaJsonLdWarmUrlCollector;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Contracts\Globals\GlobalSet as GlobalSetContract;
use Statamic\Contracts\Globals\Variables as GlobalVariablesContract;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Facades\Term as TermFacade;
use Statamic\Sites\Site as StatamicSite;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Term;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * @covers \App\Services\SchemaTemplates\SchemaJsonLdWarmUrlCollector
 *
 * Mutation: not recorded. An earlier figure here (100% on App\Services\SchemaTemplates\SchemaJsonLdWarmUrlCollector,
 * 252 mutants, --covered-only) named no command or revision and was removed (UAMS-Web/uams-statamic-archive#2915).
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaJsonLdWarmUrlCollectorTest.php
 */

/**
 * Base warm config slice shared across the collector scenarios.
 *
 * @return array<string, mixed>
 */
function schemaJsonLdWarmUrlCollectorBaseSlice(): array
{
    return [
        'global_fallback_site_handle' => null,
        'global_entry_fields_by_collection' => [],
        'global_taxonomy_entry_traversals' => [],
        'homepage_collection_handle' => 'pages',
        'homepage_uri_candidates' => ['/'],
    ];
}

/**
 * A published `pages` home entry whose absoluteUrl is the given value.
 *
 * @return EntryModel&MockInterface
 */
function schemaJsonLdWarmUrlCollectorMockHomeEntry(
    string $absoluteUrl
): EntryModel {
    $collection = Mockery::mock();
    $collection->shouldReceive('handle')->andReturn('pages');

    /** @var EntryModel&MockInterface $entry */
    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('published')->andReturn(true);
    $entry->shouldReceive('collection')->andReturn($collection);
    $entry->shouldReceive('absoluteUrl')->andReturn($absoluteUrl);

    return $entry;
}

it('includes homepage and people urls from the globals slice', function () {
    $site = Mockery::mock(StatamicSite::class);
    $site->shouldReceive('handle')->andReturn('default');

    $home = schemaJsonLdWarmUrlCollectorMockHomeEntry('https://example.test/');
    EntryFacade::shouldReceive('findByUri')
        ->once()
        ->with(
            '/',
            'default'
        )
        ->andReturn($home);

    $globals = Mockery::mock(GlobalVariablesContract::class);
    $globals->shouldReceive('get')
        ->with('schema_subject')
        ->once()
        ->andReturn(['schema-entry-uuid']);
    $globals->shouldReceive('get')
        ->with('founder')
        ->once()
        ->andReturn(['person-entry-uuid']);

    $set = Mockery::mock(GlobalSetContract::class);
    $set->shouldReceive('in')
        ->once()
        ->with('default')
        ->andReturn($globals);

    GlobalSet::shouldReceive('findByHandle')
        ->once()
        ->with('uams_multisite_settings')
        ->andReturn($set);

    $person = Mockery::mock(EntryModel::class);
    $person->shouldReceive('published')->andReturnTrue();
    $person->shouldReceive('absoluteUrl')->andReturn('https://example.test/person');

    EntryFacade::shouldReceive('find')
        ->once()
        ->with('person-entry-uuid')
        ->andReturn($person);

    $slice = schemaJsonLdWarmUrlCollectorBaseSlice();
    $slice['global_entry_fields_by_collection'] = [
        'schema_objects' => [
            'uams_multisite_settings.schema_subject',
        ],
        'people' => [
            'uams_multisite_settings.founder',
        ],
    ];

    $collector = new SchemaJsonLdWarmUrlCollector(
        $slice
    );
    $result = $collector->collectForSite($site);

    expect($result['urls'])->toBe([
        'https://example.test/',
        'https://example.test/person',
    ]);
    expect($result['schema_object_entry_ids'])->toBe(['schema-entry-uuid']);
    expect($result['people_entry_ids'])->toBe(['person-entry-uuid']);
    expect($result['globals_site_handle_used'])->toBe('default');
    expect($result['homepage_uri'])->toBe('/');
    expect($result['globals_localization_site_by_global_set'])->toBe([
        'uams_multisite_settings' => 'default',
    ]);
});

it('uses fallback site globals when the site row is missing', function () {
    Site::shouldReceive('default')
        ->once()
        ->andReturnUsing(function () {
            $s = Mockery::mock(StatamicSite::class);
            $s->shouldReceive('handle')->andReturn('default');

            return $s;
        });

    $site = Mockery::mock(StatamicSite::class);
    $site->shouldReceive('handle')->andReturn('child');

    $home = schemaJsonLdWarmUrlCollectorMockHomeEntry('https://child.example/');
    EntryFacade::shouldReceive('findByUri')
        ->once()
        ->with(
            '/',
            'child'
        )
        ->andReturn($home);

    $globals = Mockery::mock(GlobalVariablesContract::class);
    $globals->shouldReceive('get')
        ->once()
        ->with('schema_subject')
        ->andReturn(null);

    $set = Mockery::mock(GlobalSetContract::class);
    $set->shouldReceive('in')
        ->once()
        ->with('child')
        ->andReturn(null);
    $set->shouldReceive('in')
        ->once()
        ->with('default')
        ->andReturn($globals);

    GlobalSet::shouldReceive('findByHandle')
        ->once()
        ->with('uams_multisite_settings')
        ->andReturn($set);

    $slice = schemaJsonLdWarmUrlCollectorBaseSlice();
    $slice['global_entry_fields_by_collection'] = [
        'schema_objects' => [
            'uams_multisite_settings.schema_subject',
        ],
    ];

    $collector = new SchemaJsonLdWarmUrlCollector(
        $slice
    );
    $result = $collector->collectForSite($site);

    expect($result['warnings'])->toContain(
        'Site child has no localized row for global set uams_multisite_settings; used fallback site default.'
    );
    expect($result['globals_site_handle_used'])->toBe('default');
});

it('resolves people via a taxonomy term traversal', function () {
    $site = Mockery::mock(StatamicSite::class);
    $site->shouldReceive('handle')->andReturn('default');

    $home = schemaJsonLdWarmUrlCollectorMockHomeEntry('https://example.test/');
    EntryFacade::shouldReceive('findByUri')
        ->once()
        ->andReturn($home);

    $globals = Mockery::mock(GlobalVariablesContract::class);
    $globals->shouldReceive('get')
        ->once()
        ->with('media_contact')
        ->andReturn(['full']);

    $set = Mockery::mock(GlobalSetContract::class);
    $set->shouldReceive('in')
        ->once()
        ->with('default')
        ->andReturn($globals);

    GlobalSet::shouldReceive('findByHandle')
        ->once()
        ->andReturn($set);

    $term = Mockery::mock(LocalizedTerm::class);
    $term->shouldReceive('id')->andReturn('media_contact_groups::full');
    $term->shouldReceive('get')
        ->with('people')
        ->once()
        ->andReturn(['media-person-uuid']);

    $termQuery = Mockery::mock();
    $termQuery->shouldReceive('where')
        ->once()
        ->with(
            'taxonomy',
            'media_contact_groups'
        )
        ->andReturnSelf();
    $termQuery->shouldReceive('where')
        ->once()
        ->with(
            'slug',
            'full'
        )
        ->andReturnSelf();
    $termQuery->shouldReceive('first')
        ->once()
        ->andReturn($term);
    TermFacade::shouldReceive('query')
        ->once()
        ->andReturn($termQuery);

    $person = Mockery::mock(EntryModel::class);
    $person->shouldReceive('published')->andReturnTrue();
    $person->shouldReceive('absoluteUrl')->andReturn('https://example.test/media-person');

    EntryFacade::shouldReceive('find')
        ->once()
        ->with('media-person-uuid')
        ->andReturn($person);

    $slice = schemaJsonLdWarmUrlCollectorBaseSlice();
    $slice['global_taxonomy_entry_traversals'] = [
        [
            'global_set_field_handles' => [
                'uams_multisite_settings.media_contact',
            ],
            'taxonomy_handle' => 'media_contact_groups',
            'term_entries_field' => 'people',
            'term_entries_field_collection_handle' => 'people',
        ],
    ];

    $collector = new SchemaJsonLdWarmUrlCollector(
        $slice
    );
    $result = $collector->collectForSite($site);

    expect($result['urls'])->toContain('https://example.test/media-person');
});

/**
 * A site that answers only its handle.
 */
function warmSite(string $handle): StatamicSite
{
    $site = Mockery::mock(StatamicSite::class);
    $site->shouldReceive('handle')->andReturn($handle);

    return $site;
}

/**
 * An entry with the given publish state, absolute URL and collection handle.
 */
function warmEntry(bool $published = true, mixed $url = null, ?string $collection = 'pages'): EntryModel
{
    $handle = $collection === null ? null : tap(Mockery::mock(), fn ($mock) => $mock->shouldReceive('handle')->andReturn($collection));

    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('published')->andReturn($published);
    $entry->shouldReceive('collection')->andReturn($handle);
    $entry->shouldReceive('absoluteUrl')->andReturn($url);

    return $entry;
}

/**
 * Global variables answering from the given data.
 *
 * @param  array<string, mixed>  $data
 */
function warmVariables(array $data): GlobalVariablesContract
{
    $variables = Mockery::mock(GlobalVariablesContract::class);
    $variables->shouldReceive('get')->andReturnUsing(fn (string $field): mixed => $data[$field] ?? null);

    return $variables;
}

/**
 * Serve entries by uri and id, global sets by handle (each a map of site handle to
 * variables), and terms by full id or by taxonomy and slug.
 *
 * @param  array<string, EntryModel>  $byUri
 * @param  array<string, EntryModel>  $byId
 * @param  array<string, array<array-key, mixed>>  $globalSets  Global set handle to site handle to variables
 * @param  array<string, mixed>  $termsById
 * @param  array<string, mixed>  $termsBySlug  Keyed "taxonomy/slug"
 * @param  ArrayObject<int, string>|null  $setLookups  Records each global set handle looked up
 */
function warmServe(array $byUri = [], array $byId = [], array $globalSets = [], array $termsById = [], array $termsBySlug = [], ?ArrayObject $setLookups = null): void
{
    EntryFacade::shouldReceive('findByUri')->andReturnUsing(fn (string $uri): ?EntryModel => $byUri[$uri] ?? null);
    EntryFacade::shouldReceive('find')->andReturnUsing(fn (string $id): ?EntryModel => $byId[$id] ?? null);

    GlobalSet::shouldReceive('findByHandle')->andReturnUsing(function (string $handle) use ($globalSets, $setLookups): ?GlobalSetContract {
        $setLookups?->append($handle);

        if (! array_key_exists($handle, $globalSets)) {
            return null;
        }

        $set = Mockery::mock(GlobalSetContract::class);
        $set->shouldReceive('in')->andReturnUsing(function (string $site) use ($globalSets, $handle): ?GlobalVariablesContract {
            $variables = $globalSets[$handle][$site] ?? null;

            return $variables instanceof GlobalVariablesContract ? $variables : null;
        });

        return $set;
    });

    TermFacade::shouldReceive('find')->andReturnUsing(fn (string $id): mixed => $termsById[$id] ?? null);
    TermFacade::shouldReceive('query')->andReturnUsing(function () use ($termsBySlug) {
        $where = [];
        $query = Mockery::mock();
        $query->shouldReceive('where')->andReturnUsing(function (string $column, string $value) use (&$where, $query) {
            $where[$column] = $value;

            return $query;
        });
        $query->shouldReceive('first')->andReturnUsing(function () use (&$where, $termsBySlug): mixed {
            return $termsBySlug[($where['taxonomy'] ?? '').'/'.($where['slug'] ?? '')] ?? null;
        });

        return $query;
    });

    Site::shouldReceive('default')->andReturn(warmSite('default'));
}

/**
 * A term with the given id whose fields answer from the given data.
 *
 * @param  array<string, mixed>  $data
 */
function warmTerm(mixed $id, array $data): LocalizedTerm
{
    $term = Mockery::mock(LocalizedTerm::class);
    $term->shouldReceive('id')->andReturn($id);
    $term->shouldReceive('get')->andReturnUsing(fn (string $field): mixed => $data[$field] ?? null);

    return $term;
}

/**
 * A taxonomy-level term (not localized) with the given id whose fields answer from the given data.
 *
 * @param  array<string, mixed>  $data
 */
function warmPlainTerm(string $id, array $data): Term
{
    $term = Mockery::mock(Term::class);
    $term->shouldReceive('id')->andReturn($id);
    $term->shouldReceive('get')->andReturnUsing(fn (string $field): mixed => $data[$field] ?? null);

    return $term;
}

/**
 * Collect for the given site with the given slice over the base one.
 *
 * @param  array<array-key, mixed>  $slice
 * @return array{urls: list<string>, people_entry_ids: list<string>, schema_object_entry_ids: list<string>, warnings: list<string>, globals_site_handle_used: string|null, homepage_uri: string|null, globals_localization_site_by_global_set: array<string, string|null>}
 */
function warmCollect(array $slice, string $site = 'default'): array
{
    /** @var array<string, mixed> $merged */
    $merged = [...schemaJsonLdWarmUrlCollectorBaseSlice(), ...$slice];

    return (new SchemaJsonLdWarmUrlCollector($merged))->collectForSite(warmSite($site));
}

it('walks the homepage candidates to the first published page with a URL', function () {
    warmServe(byUri: [
        '/draft' => warmEntry(published: false, url: 'https://example.test/draft'),
        '/landing' => warmEntry(url: 'https://example.test/landing', collection: 'landing'),
        '/orphan' => warmEntry(url: 'https://example.test/orphan', collection: null),
        '/no-url' => warmEntry(url: ''),
        '/home' => warmEntry(url: 'https://example.test/'),
        '/after' => warmEntry(url: 'https://example.test/after'),
    ]);

    $result = warmCollect(['homepage_uri_candidates' => ['/missing', '/draft', '/landing', '/orphan', '/no-url', 42, '/home', '/after']]);

    expect($result)->toBe([
        'urls' => ['https://example.test/'],
        'people_entry_ids' => [],
        'schema_object_entry_ids' => [],
        'warnings' => [
            'Home candidate uri "\/draft" resolves to an unpublished entry for site default.',
            'Home entry for site default (uri "\/no-url") has no absoluteUrl.',
        ],
        'globals_site_handle_used' => null,
        'homepage_uri' => '/home',
        'globals_localization_site_by_global_set' => [],
    ]);
});

it('warns when no homepage is found, with the configured collection and candidates', function (array $slice, string $warning) {
    warmServe(byUri: ['/' => warmEntry(url: null)]);

    $result = warmCollect($slice);

    expect($result['homepage_uri'])->toBeNull()
        ->and(end($result['warnings']))->toBe($warning);
})->with([
    'the default candidates' => [['homepage_uri_candidates' => 'not a list'], 'No published homepage found for site default using collection pages and uri candidates ["\/"].'],
    'no text candidates' => [['homepage_uri_candidates' => [7, null]], 'No published homepage found for site default using collection pages and uri candidates ["\/"].'],
    'a custom collection' => [['homepage_collection_handle' => 'landing'], 'No published homepage found for site default using collection landing and uri candidates ["\/"].'],
    'a blank collection' => [['homepage_collection_handle' => ''], 'No published homepage found for site default using collection pages and uri candidates ["\/"].'],
    'a non-text collection' => [['homepage_collection_handle' => 5], 'No published homepage found for site default using collection pages and uri candidates ["\/"].'],
]);

it('accepts a homepage from a custom collection', function () {
    warmServe(byUri: ['/' => warmEntry(url: 'https://example.test/', collection: 'landing')]);

    expect(warmCollect(['homepage_collection_handle' => 'landing'])['urls'])->toBe(['https://example.test/']);
});

it('reads the warm slice from config when none is given, and none when config is not a list', function (mixed $config, array $urls) {
    warmServe(byUri: [
        '/' => warmEntry(url: 'https://example.test/'),
        '/configured' => warmEntry(url: 'https://example.test/configured'),
    ]);
    config(['schema_templates.json_ld_cache.warm' => $config]);

    expect((new SchemaJsonLdWarmUrlCollector)->collectForSite(warmSite('default'))['urls'])->toBe($urls);
})->with([
    'configured' => [['homepage_uri_candidates' => ['/configured']], ['https://example.test/configured']],
    'not a list' => ['nonsense', ['https://example.test/']],
]);

it('skips unusable collection keys and refs, and warns on a malformed reference', function () {
    warmServe(
        byUri: ['/' => warmEntry(url: 'https://example.test/')],
        byId: [
            'page-1' => warmEntry(url: 'https://example.test/page-1'),
            'page-2' => warmEntry(url: 'https://example.test/page-2'),
        ],
        globalSets: ['settings' => ['default' => warmVariables(['featured' => ['page-1'], 'skipped' => ['page-2']])]],
    );

    $result = warmCollect(['global_entry_fields_by_collection' => [
        0 => ['settings.skipped'],
        '' => ['settings.skipped'],
        'pages' => 'settings.skipped',
        'articles' => ['', 7, 'no-dot', '.field', 'settings.', 'settings.featured'],
    ]]);

    expect($result['urls'])->toBe(['https://example.test/', 'https://example.test/page-1'])
        ->and($result['warnings'])->toBe([
            'Invalid global field reference "no-dot" (expected global_set_handle.field_handle).',
            'Invalid global field reference ".field" (expected global_set_handle.field_handle).',
            'Invalid global field reference "settings." (expected global_set_handle.field_handle).',
        ])
        ->and($result['globals_site_handle_used'])->toBe('default');
});

it('reads each global set once, and warns once for one that is missing', function () {
    $lookups = new ArrayObject;
    warmServe(globalSets: ['settings' => ['default' => warmVariables(['first' => null, 'second' => null])]], setLookups: $lookups);

    $result = warmCollect(['global_entry_fields_by_collection' => [
        'pages' => ['settings.first', 'settings.second', 'missing.one', 'missing.two'],
    ]]);

    expect(array_values(array_filter($result['warnings'], fn (string $w): bool => ! str_starts_with($w, 'No published homepage'))))
        ->toBe(['Global set missing was not found.'])
        ->and($result['globals_localization_site_by_global_set'])->toBe(['settings' => 'default', 'missing' => null])
        ->and($lookups->getArrayCopy())->toBe(['settings', 'missing']);
});

it('falls back to the configured site, then the default site, and warns when neither has the set', function (array $slice, array $localizations, ?string $used, string $warning) {
    warmServe(globalSets: ['settings' => $localizations]);

    $result = warmCollect(['global_entry_fields_by_collection' => ['pages' => ['settings.featured']], ...$slice], site: 'child');

    expect($result['globals_site_handle_used'])->toBe($used)
        ->and($result['globals_localization_site_by_global_set'])->toBe(['settings' => $used])
        ->and(array_values(array_filter($result['warnings'], fn (string $w): bool => ! str_starts_with($w, 'No published homepage'))))->toBe([$warning]);
})->with([
    'the configured fallback' => [['global_fallback_site_handle' => 'health'], ['health' => warmVariables([])], 'health', 'Site child has no localized row for global set settings; used fallback site health.'],
    'the multisite fallback key' => [['global_fallback_site_handle' => null, 'multisite_global_fallback_site_handle' => 'health'], ['health' => warmVariables([])], 'health', 'Site child has no localized row for global set settings; used fallback site health.'],
    'a blank fallback uses the default site' => [['global_fallback_site_handle' => ''], ['default' => warmVariables([])], 'default', 'Site child has no localized row for global set settings; used fallback site default.'],
    'neither has it' => [['global_fallback_site_handle' => 'health'], [], null, 'Global set settings: no localized variables for site child (fallback health also missing).'],
]);

it('takes one schema object id from a field, in order and once each', function () {
    warmServe(globalSets: ['settings' => ['default' => warmVariables([
        'none' => null,
        'blank' => '',
        'empty_first' => ['', 'skipped'],
        'false_first' => [false],
        'nested' => [['a']],
        'object' => new stdClass,
        'number' => [42],
        'bare_number' => 7,
        'text' => 'object-1',
        'list' => ['object-2', 'object-3'],
        'again' => 'object-1',
    ])]]);

    $refs = array_map(fn (string $f): string => "settings.{$f}", ['none', 'blank', 'empty_first', 'false_first', 'nested', 'object', 'number', 'bare_number', 'text', 'list', 'again']);

    expect(warmCollect(['global_entry_fields_by_collection' => ['schema_objects' => $refs]])['schema_object_entry_ids'])
        ->toBe(['42', '7', 'object-1', 'object-2']);
});

it('collects published entry URLs from global entries fields, and people ids once each', function () {
    warmServe(
        byId: [
            'person-1' => warmEntry(url: 'https://example.test/people/one', collection: 'people'),
            'person-2' => warmEntry(published: false, url: 'https://example.test/people/two', collection: 'people'),
            'person-3' => warmEntry(url: null, collection: 'people'),
            'person-4' => warmEntry(url: '', collection: 'people'),
            'page-1' => warmEntry(url: 'https://example.test/people/one'),
        ],
        globalSets: ['settings' => ['default' => warmVariables([
            'team' => ['person-1', '', 9, 'person-2', 'person-3', 'person-4', 'person-gone', 'person-1'],
            'lead' => 'person-1',
            'page' => 'page-1',
            'nothing' => '',
            'number' => 5,
        ])]],
    );

    $result = warmCollect(['global_entry_fields_by_collection' => [
        'people' => ['settings.team', 'settings.lead', 'settings.nothing', 'settings.number'],
        'pages' => ['settings.page'],
    ]]);

    expect($result['urls'])->toBe(['https://example.test/people/one'])
        ->and($result['people_entry_ids'])->toBe(['person-1', 'person-2', 'person-3', 'person-4', 'person-gone'])
        ->and(array_values(array_filter($result['warnings'], fn (string $w): bool => str_starts_with($w, 'Entry '))))->toBe([
            'Entry person-2 (global settings.team) is not published; skipping URL.',
            'Entry person-3 (global settings.team) has no absoluteUrl.',
            'Entry person-4 (global settings.team) has no absoluteUrl.',
            'Entry person-gone (global settings.team) was not found.',
        ]);
});

it('reports the first global set that resolved to a site', function () {
    warmServe(globalSets: ['present' => ['default' => warmVariables([])]]);

    $result = warmCollect(['global_entry_fields_by_collection' => ['pages' => ['absent.field', 'present.field']]]);

    expect($result['globals_site_handle_used'])->toBe('default')
        ->and($result['globals_localization_site_by_global_set'])->toBe(['absent' => null, 'present' => 'default']);
});

it('starts each collection afresh', function () {
    $lookups = new ArrayObject;
    warmServe(globalSets: ['settings' => ['default' => warmVariables([]), 'child' => warmVariables([])]], setLookups: $lookups);
    $collector = new SchemaJsonLdWarmUrlCollector([...schemaJsonLdWarmUrlCollectorBaseSlice(), 'global_entry_fields_by_collection' => ['pages' => ['settings.field']]]);

    $collector->collectForSite(warmSite('child'));
    $second = $collector->collectForSite(warmSite('default'));

    // The second collection looks the set up again rather than reusing the first site's row
    expect($second['globals_localization_site_by_global_set'])->toBe(['settings' => 'default'])
        ->and($lookups->getArrayCopy())->toBe(['settings', 'settings']);
});

it('warns on an incomplete taxonomy rule and skips one that is not a list', function (array $rule) {
    warmServe();

    $result = warmCollect(['global_taxonomy_entry_traversals' => ['not a rule', $rule]]);

    expect(array_values(array_filter($result['warnings'], fn (string $w): bool => ! str_starts_with($w, 'No published homepage'))))
        ->toBe(['A global_taxonomy_entry_traversals rule is incomplete; expected global_set_field_handles, taxonomy_handle, and term_entries_field.']);
})->with([
    'no handles' => [['taxonomy_handle' => 'groups', 'term_entries_field' => 'people']],
    'no taxonomy' => [['global_set_field_handles' => ['settings.groups'], 'term_entries_field' => 'people']],
    'a blank taxonomy' => [['global_set_field_handles' => ['settings.groups'], 'taxonomy_handle' => '', 'term_entries_field' => 'people']],
    'no entries field' => [['global_set_field_handles' => ['settings.groups'], 'taxonomy_handle' => 'groups']],
    'a blank entries field' => [['global_set_field_handles' => ['settings.groups'], 'taxonomy_handle' => 'groups', 'term_entries_field' => '']],
    'a non-text entries field' => [['global_set_field_handles' => ['settings.groups'], 'taxonomy_handle' => 'groups', 'term_entries_field' => 7]],
]);

it('follows global terms to their entries, by slug or by full id, inferring each entry\'s collection', function () {
    warmServe(
        byId: [
            'person-1' => warmEntry(url: 'https://example.test/people/one', collection: 'people'),
            'page-1' => warmEntry(url: 'https://example.test/page', collection: 'pages'),
            'object-1' => warmEntry(url: 'https://example.test/object', collection: 'schema_objects'),
            'orphan-1' => warmEntry(url: 'https://example.test/orphan', collection: null),
        ],
        globalSets: ['settings' => ['default' => warmVariables([
            'groups' => ['full', '', 3, 'groups::other', 'gone', 'groups::gone'],
            'single' => 'odd',
            'number' => 12,
            'blank' => '',
            'plain' => ['groups::plain', 'plain-slug'],
        ])]],
        termsById: [
            'groups::other' => warmTerm('groups::other', ['members' => 'page-1']),
            'groups::plain' => warmPlainTerm('groups::plain', ['members' => ['object-1']]),
        ],
        termsBySlug: [
            'groups/full' => warmTerm('groups::full', ['members' => ['person-1', 'object-1', 'orphan-1', 'unknown-1']]),
            'groups/odd' => warmTerm(['not', 'scalar'], ['members' => ['missing-entry']]),
            'groups/plain-slug' => warmPlainTerm('groups::plain-slug', ['members' => ['person-1']]),
        ],
    );

    $result = warmCollect(['global_taxonomy_entry_traversals' => [[
        // A missing set ahead of the rest, so the walk must pass over it and go on
        'global_set_field_handles' => ['absent.groups', 'settings.groups', 'no-dot', 'settings.single', 'settings.number', 'settings.blank', 'settings.plain'],
        'taxonomy_handle' => 'groups',
        'term_entries_field' => 'members',
        'term_entries_field_collection_handle' => '',
    ]]]);

    expect($result['urls'])->toBe(['https://example.test/people/one', 'https://example.test/page'])
        ->and($result['people_entry_ids'])->toBe(['person-1'])
        ->and($result['schema_object_entry_ids'])->toBe(['object-1'])
        ->and(array_values(array_filter($result['warnings'], fn (string $w): bool => ! str_starts_with($w, 'No published homepage'))))->toBe([
            'Global set absent was not found.',
            'Could not determine entry collection for id orphan-1 (term groups::full); set term_entries_field_collection_handle on the rule.',
            'Could not determine entry collection for id unknown-1 (term groups::full); set term_entries_field_collection_handle on the rule.',
            'Could not resolve term "gone" for taxonomy groups (settings.groups).',
            'Could not resolve term "groups::gone" for taxonomy groups (settings.groups).',
            'Invalid global field reference "no-dot" in taxonomy traversal rule.',
            'Could not determine entry collection for id missing-entry (term ); set term_entries_field_collection_handle on the rule.',
        ]);
});

it('files a traversal\'s entries under the rule\'s collection when it names one', function (string $collection, array $people, array $objects, array $urls) {
    warmServe(
        byId: ['entry-1' => warmEntry(url: 'https://example.test/entry', collection: 'pages')],
        globalSets: ['settings' => ['default' => warmVariables(['groups' => 'full'])]],
        termsBySlug: ['groups/full' => warmTerm('groups::full', ['members' => ['entry-1']])],
    );

    $result = warmCollect(['global_taxonomy_entry_traversals' => [[
        'global_set_field_handles' => ['settings.groups'],
        'taxonomy_handle' => 'groups',
        'term_entries_field' => 'members',
        'term_entries_field_collection_handle' => $collection,
    ]]]);

    expect($result['people_entry_ids'])->toBe($people)
        ->and($result['schema_object_entry_ids'])->toBe($objects)
        ->and($result['urls'])->toBe($urls);
})->with([
    'people' => ['people', ['entry-1'], [], ['https://example.test/entry']],
    'schema objects' => ['schema_objects', [], ['entry-1'], []],
]);

it('reads the field after the first dot whole, so a field handle may itself hold a dot', function () {
    warmServe(globalSets: ['settings' => ['default' => warmVariables(['nested.field' => 'object-1', 'nested' => 'wrong'])]]);

    expect(warmCollect(['global_entry_fields_by_collection' => ['schema_objects' => ['settings.nested.field']]])['schema_object_entry_ids'])
        ->toBe(['object-1']);
});

it('reports the first global set with a non-empty site, past one localized under an empty handle', function () {
    warmServe(globalSets: [
        'first' => ['' => warmVariables([])],
        'second' => ['health' => warmVariables([])],
    ]);

    $result = warmCollect(['global_fallback_site_handle' => 'health', 'global_entry_fields_by_collection' => ['pages' => ['first.field', 'second.field']]], site: '');

    expect($result['globals_localization_site_by_global_set'])->toBe(['first' => '', 'second' => 'health'])
        ->and($result['globals_site_handle_used'])->toBe('health');
});
