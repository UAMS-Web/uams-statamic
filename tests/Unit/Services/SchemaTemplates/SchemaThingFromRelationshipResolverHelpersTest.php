<?php

declare(strict_types=1);

use App\Scopes\CredentialTypeCertification;
use App\Services\SchemaTemplates\SchemaJsonLdAtIdDeduplication;
use App\Services\SchemaTemplates\SchemaOutputService;
use App\Services\SchemaTemplates\SchemaTemplatesCascadeService;
use App\Services\SchemaTemplates\SchemaThingFromRelationshipResolver;
use Statamic\Assets\Asset;
use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Taxonomies\Term as TermContract;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Fields\Value;
use Statamic\Globals\Variables;
use Statamic\Query\OrderedQueryBuilder;
use Statamic\Query\StatusQueryBuilder;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Term as TaxonomyTerm;

covers(SchemaThingFromRelationshipResolver::class);

/**
 * Pins the private helpers of {@see SchemaThingFromRelationshipResolver} that
 * need no booted application, directly through reflection. The helpers that
 * read config or the request are in the feature file of the same name.
 *
 * @command  php artisan test --compact tests/Unit/Services/SchemaTemplates/SchemaThingFromRelationshipResolverHelpersTest.php
 */

/**
 * Call a private resolver method on a resolver over mocked collaborators.
 *
 * @param  string  $method  Method name.
 * @param  mixed  ...$args  Arguments.
 */
function thingUnitCall(string $method, mixed ...$args): mixed
{
    $cascade = Mockery::mock(SchemaTemplatesCascadeService::class);
    $cascade->shouldIgnoreMissing();

    $resolver = new SchemaThingFromRelationshipResolver(
        Mockery::mock(SchemaOutputService::class),
        $cascade,
        new SchemaJsonLdAtIdDeduplication
    );

    return (new ReflectionMethod(SchemaThingFromRelationshipResolver::class, $method))->invoke($resolver, ...$args);
}

// ---
// Small parsers and normalizers
// ---

it('parses an optional positive integer', function (mixed $value, ?int $expected): void {
    expect(thingUnitCall('parseOptionalPositiveInt', $value))->toBe($expected);
})->with([
    'integer' => [3, 3],
    'one' => [1, 1],
    'zero' => [0, null],
    'negative' => [-2, null],
    'digit string' => ['4', 4],
    'one string' => ['1', 1],
    'zero string' => ['0', null],
    'signed string' => ['-1', null],
    'empty string' => ['', null],
    'null' => [null, null],
    'float' => [2.0, null],
]);

it('parses a boolean parameter', function (mixed $value, bool $expected): void {
    expect(thingUnitCall('parseBooleanParam', $value))->toBe($expected);
})->with([
    'true' => [true, true],
    'false' => [false, false],
    'one' => [1, true],
    'zero' => [0, false],
    'negative' => [-1, true],
    'padded TRUE' => [' TRUE ', true],
    'yes' => ['yes', true],
    'on' => ['On', true],
    'one string' => ['1', true],
    'false string' => ['false', false],
    'other string' => ['maybe', false],
    'null' => [null, false],
    'float' => [1.0, false],
]);

it('normalizes a context type against the allowed list', function (mixed $raw, ?string $expected): void {
    expect(thingUnitCall('normalizeContextType', $raw, ['entries', 'terms']))->toBe($expected);
})->with([
    'allowed' => ['entries', 'entries'],
    'padded upper case' => [' TERMS ', 'terms'],
    'not allowed' => ['assets', null],
    'empty' => ['', null],
    'not a string' => [5, null],
]);

it('treats null, empty strings, empty arrays and empty collections as empty relationship values', function (mixed $value, bool $expected): void {
    expect(thingUnitCall('isEmptyRelationshipValue', $value))->toBe($expected);
})->with([
    'null' => [null, true],
    'empty string' => ['', true],
    'empty array' => [[], true],
    'empty collection' => [collect(), true],
    'collection' => [collect(['a']), false],
    'array' => [['a'], false],
    'zero' => [0, false],
    'string' => ['a', false],
    'false' => [false, false],
]);

it('normalizes a raw relationship value to a list', function (Closure $raw, array $expected): void {
    expect(thingUnitCall('normalizeRawToList', $raw()))->toBe($expected);
})->with([
    'null' => [fn (): mixed => null, []],
    'Value of an empty string' => [fn (): mixed => new Value(''), []],
    'scalar' => [fn (): mixed => 'a', ['a']],
    'keyed array' => [fn (): mixed => ['x' => 'a', 'y' => 'b'], ['a', 'b']],
    'Value of a list' => [fn (): mixed => new Value(['a', 'b']), ['a', 'b']],
    'keyed collection' => [fn (): mixed => collect(['x' => 'a', 'y' => 'b']), ['a', 'b']],
    'ordered query' => [fn (): mixed => tap(Mockery::mock(OrderedQueryBuilder::class), fn ($q) => $q->shouldReceive('get')->andReturn(collect(['x' => 'a']))), ['a']],
    'status query' => [fn (): mixed => tap(Mockery::mock(StatusQueryBuilder::class), fn ($q) => $q->shouldReceive('get')->andReturn(collect(['b']))), ['b']],
]);

it('normalizes a target to an entity and its template context', function (mixed $target, array $expected): void {
    expect(thingUnitCall('normalizeResolvedTarget', $target))->toBe($expected);
})->with([
    'plain value' => ['a', ['entity' => 'a', 'template_context' => []]],
    'array without an entity' => [['x' => 1], ['entity' => ['x' => 1], 'template_context' => []]],
    'payload' => [['entity' => 'a', 'template_context' => ['k' => 'v']], ['entity' => 'a', 'template_context' => ['k' => 'v']]],
    'payload with an unusable context' => [['entity' => null, 'template_context' => 'x'], ['entity' => null, 'template_context' => []]],
    'payload without a context' => [['entity' => 'a'], ['entity' => 'a', 'template_context' => []]],
]);

it('treats a replicator row as enabled unless its flag says otherwise', function (array $row, bool $expected): void {
    expect(thingUnitCall('isReplicatorRowEnabled', $row))->toBe($expected);
})->with([
    'no flag' => [['type' => 'x'], true],
    'enabled' => [['enabled' => true], true],
    'disabled' => [['enabled' => false], false],
    'string flag' => [['enabled' => 'yes'], true],
    'null flag' => [['enabled' => null], false],
]);

it('drops row metadata from a flat replicator row', function (): void {
    expect(thingUnitCall('extractFlatReplicatorRowValue', ['type' => 't', 'enabled' => true, '_id' => 'i', 'file' => 'f', 'title' => 'x']))
        ->toBe(['file' => 'f', 'title' => 'x']);
});

it('encodes nodes with slashes and unicode unescaped, or an empty array when it cannot', function (): void {
    expect(thingUnitCall('jsonEncodeNodes', [['@id' => 'https://example.test/é']]))->toBe('[{"@id":"https://example.test/é"}]')
        ->and(thingUnitCall('jsonEncodeNodes', [['name' => "\xB1\x31"]]))->toBe('[]');
});

it('reads field handles from a piped parameter', function (): void {
    expect(thingUnitCall('resolveFieldHandles', ['f' => ' a | b |'], 'f'))->toBe(['a', 'b'])
        ->and(thingUnitCall('resolveFieldHandles', [], 'f'))->toBe([]);
});

// ---
// Direct-mode term data filter
// ---

/**
 * Build a mocked taxonomy term whose default-locale data is fixed.
 *
 * @param  array<string, mixed>  $data  Default-locale term data.
 */
function thingUnitTermWithData(array $data): TaxonomyTerm
{
    $localized = Mockery::mock(LocalizedTerm::class);
    $localized->shouldReceive('data')->andReturn(collect($data));

    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('inDefaultLocale')->andReturn($localized);

    return $term;
}

/**
 * An object with one public property, for path descent.
 */
function thingUnitObjectWith(string $property, mixed $value): object
{
    $object = new stdClass;
    $object->{$property} = $value;

    return $object;
}

it('reads term data by a dotted field path', function (array $data, string $field, mixed $expected): void {
    $localized = Mockery::mock(LocalizedTerm::class);
    $localized->shouldReceive('data')->andReturn(collect($data));

    expect(thingUnitCall('getLocalizedTermDataValueByFieldPath', $localized, $field))->toBe($expected);
})->with([
    'top-level' => [['type' => 'Credential'], 'type', 'Credential'],
    'nested array' => [['ctdl' => ['ceterms_type' => 'Credential']], 'ctdl.ceterms_type', 'Credential'],
    'nested Value' => [['ctdl' => new Value(['ceterms_type' => 'Credential'])], 'ctdl.ceterms_type', 'Credential'],
    'nested collection' => [['ctdl' => collect(['ceterms_type' => 'Credential'])], 'ctdl.ceterms_type', 'Credential'],
    'nested object' => [['ctdl' => thingUnitObjectWith('ceterms_type', 'Credential')], 'ctdl.ceterms_type', 'Credential'],
    'leaf Value' => [['type' => new Value('Credential')], 'type', 'Credential'],
    'leaf null' => [['type' => null], 'type', null],
    'missing key' => [['other' => 'x'], 'type', null],
    'missing nested key' => [['ctdl' => ['other' => 'x']], 'ctdl.ceterms_type', null],
    'missing object property' => [['ctdl' => new stdClass], 'ctdl.ceterms_type', null],
    'scalar in the path' => [['ctdl' => 'x'], 'ctdl.ceterms_type', null],
    'null in the path' => [['ctdl' => null], 'ctdl.ceterms_type', null],
]);

it('reads no term data from something without data', function (mixed $localized): void {
    expect(thingUnitCall('getLocalizedTermDataValueByFieldPath', $localized, 'type'))->toBeNull();
})->with([
    'null' => [null],
    'object without data()' => [new stdClass],
]);

it('compares a term default-locale field with the expected value', function (array $data, string $field, string $expected, bool $matches): void {
    expect(thingUnitCall('taxonomyTermDefaultLocaleDataEquals', thingUnitTermWithData($data), $field, $expected))->toBe($matches);
})->with([
    'equal after trimming' => [['type' => ' Credential '], 'type', 'Credential', true],
    'different' => [['type' => 'Badge'], 'type', 'Credential', false],
    'integer' => [['level' => 3], 'level', '3', true],
    'non-scalar against empty' => [['type' => ['x']], 'type', '', true],
    'missing against empty' => [[], 'type', '', true],
    'missing' => [[], 'type', 'Credential', false],
]);

it('compares a localized term through its default locale', function (): void {
    $default = Mockery::mock(LocalizedTerm::class);
    $default->shouldReceive('data')->andReturn(collect(['type' => 'Credential']));
    $localized = Mockery::mock(LocalizedTerm::class);
    $localized->shouldReceive('inDefaultLocale')->andReturn($default);
    $localized->shouldNotReceive('data');

    expect(thingUnitCall('taxonomyTermDefaultLocaleDataEquals', $localized, 'type', 'Credential'))->toBeTrue();
});

it('never matches something that is not a term', function (Closure $entity): void {
    expect(thingUnitCall('taxonomyTermDefaultLocaleDataEquals', $entity(), 'type', ''))->toBeFalse();
})->with([
    'entry' => [fn (): EntryModel => new EntryModel],
    'term contract without inDefaultLocale()' => [fn (): TermContract => Mockery::mock(TermContract::class)],
    'term whose default locale is missing' => [fn (): TaxonomyTerm => tap(Mockery::mock(TaxonomyTerm::class), fn ($t) => $t->shouldReceive('inDefaultLocale')->andReturn(null))],
    'null' => [fn (): mixed => null],
]);

it('recognizes a CTDL credential subtype field', function (string $field, bool $expected): void {
    expect(thingUnitCall('directTermDataEqualsComparesCtdlCredentialSubtype', $field))->toBe($expected);
})->with([
    'the subtype field' => [CredentialTypeCertification::CTDL_CREDENTIAL_TYPE_FIELD, true],
    'a nested subtype path' => ['other.ceterms_credential_type', true],
    'the root type field' => ['ctdl.ceterms_type', false],
    'a bare subtype key' => ['ceterms_credential_type', false],
]);

it('leaves direct targets unchanged without a usable filter', function (array $params): void {
    $targets = ['a', thingUnitTermWithData(['type' => 'x'])];

    expect(thingUnitCall('applyDirectTermDataEqualsFilter', $targets, $params))->toBe($targets);
})->with([
    'no field' => [['direct_term_data_equals_value' => 'x']],
    'blank field' => [['direct_term_data_equals_field' => ' ', 'direct_term_data_equals_value' => 'x']],
    'non-string field' => [['direct_term_data_equals_field' => 5, 'direct_term_data_equals_value' => 'x']],
    'no value' => [['direct_term_data_equals_field' => 'type']],
    'non-string value' => [['direct_term_data_equals_field' => 'type', 'direct_term_data_equals_value' => 5]],
]);

it('keeps the direct targets whose term field matches, padded parameters trimmed', function (): void {
    $match = thingUnitTermWithData(['type' => 'Badge']);
    $payload = ['entity' => thingUnitTermWithData(['type' => 'Badge']), 'template_context' => ['k' => 'v']];
    $other = thingUnitTermWithData(['type' => 'Credential']);

    $filtered = thingUnitCall('applyDirectTermDataEqualsFilter', ['entry', $match, $other, $payload], [
        'direct_term_data_equals_field' => ' type ',
        'direct_term_data_equals_value' => ' Badge ',
    ]);

    expect($filtered)->toBe([$match, $payload]);
});

it('compares a credential subtype only on CTDL credentials', function (): void {
    $credential = thingUnitTermWithData(['ctdl' => ['ceterms_type' => 'Credential', 'ceterms_credential_type' => 'Certificate']]);
    $notCredential = thingUnitTermWithData(['ctdl' => ['ceterms_type' => 'Badge', 'ceterms_credential_type' => 'Certificate']]);
    $otherSubtype = thingUnitTermWithData(['ctdl' => ['ceterms_type' => 'Credential', 'ceterms_credential_type' => 'Degree']]);

    $filtered = thingUnitCall('applyDirectTermDataEqualsFilter', [$notCredential, $credential, $otherSubtype], [
        'direct_term_data_equals_field' => CredentialTypeCertification::CTDL_CREDENTIAL_TYPE_FIELD,
        'direct_term_data_equals_value' => 'Certificate',
    ]);

    expect($filtered)->toBe([$credential]);
});

// ---
// Field handles and row URLs
// ---

it('strips the source_entry prefix from relationship field handles', function (): void {
    expect(thingUnitCall('stripSourceEntryPrefixFromRelationshipFieldHandles', ['about', 'source_entry.topics', 'source_entry.', 'xsource_entry.y']))
        ->toBe(['about', 'topics', 'xsource_entry.y']);
});

it('reads a row URL usable as a canonical @id', function (array $row, ?string $expected): void {
    expect(thingUnitCall('resolveExternalUrlFromRowValuePayloadForCanonicalIdentity', $row))->toBe($expected);
})->with([
    'padded URL' => [['url' => ' https://example.test/a '], 'https://example.test/a'],
    'Value of a URL' => [['url' => new Value('https://example.test/b')], 'https://example.test/b'],
    'not a URL' => [['url' => 'example'], null],
    'blank' => [['url' => '  '], null],
    'integer' => [['url' => 5], null],
    'array' => [['url' => ['https://example.test/c']], null],
    'not an absolute URL' => [['url' => '/relative/path'], null],
    'null' => [['url' => null], null],
    'no url field' => [['title' => 'x'], null],
]);

it('expands direct-mode raw targets as a list or as replicator rows', function (): void {
    expect(thingUnitCall('expandDirectModeRawTargetsToItems', ['x' => 'a'], false, []))->toBe(['a']);
});

// ---
// Field readers
// ---

/**
 * A mock of the given class whose method returns a value by handle.
 *
 * @param  class-string  $class  Class to mock.
 * @param  string  $method  Accessor to stub.
 * @param  array<string, mixed>  $values  Values by handle.
 */
function thingUnitAccessor(string $class, string $method, array $values): object
{
    $mock = Mockery::mock($class);
    $mock->shouldReceive($method)->andReturnUsing(fn (string $handle): mixed => $values[$handle] ?? null);

    return $mock;
}

it('reads the first non-empty field from the context', function (array $context, array $handles, mixed $expected): void {
    expect(thingUnitCall('readFirstNonEmptyFieldValueFromContext', $context, $handles))->toBe($expected);
})->with([
    'first non-empty' => [['a' => '', 'b' => 'x', 'c' => 'y'], ['a', 'b', 'c'], 'x'],
    'dotted path' => [['outer' => ['inner' => 'x']], ['outer.inner'], 'x'],
    'Value' => [['a' => new Value(''), 'b' => new Value('x')], ['a', 'b'], 'x'],
    'replicator row field' => [['replicator_row' => ['f' => 'x']], ['replicator_row.f'], 'x'],
    'nested replicator row Value' => [['replicator_row' => ['f' => new Value(['x'])]], ['replicator_row.f'], ['x']],
    'replicator row that is not an array' => [['replicator_row' => 'x', 'b' => 'y'], ['replicator_row.f', 'b'], 'y'],
    'replicator row without a field' => [['replicator_row' => ['' => 'x']], ['replicator_row.'], null],
    'nothing' => [['a' => null, 'b' => []], ['a', 'b', 'c'], null],
]);

it('reads a source_entry field from the source entry', function (): void {
    // The entry also answers the empty handle, so a handle with no field name must not be read
    $entry = thingUnitAccessor(EntryModel::class, 'value', ['topics' => new Value(['t1']), '' => 'blank handle']);

    expect(thingUnitCall('readFirstNonEmptyFieldValueFromContext', ['source_entry' => $entry], ['source_entry.missing', 'source_entry.topics']))->toBe(['t1'])
        ->and(thingUnitCall('readFirstNonEmptyFieldValueFromContext', ['source_entry' => $entry, '' => 'x'], ['source_entry.']))->toBeNull()
        ->and(thingUnitCall('readFirstNonEmptyFieldValueFromContext', ['source_entry' => ['topics' => 'x']], ['source_entry.topics']))->toBeNull()
        ->and(thingUnitCall('readFirstNonEmptyFieldValueFromContext', [], ['source_entry.topics']))->toBeNull();
});

it('reads the first non-empty field from each kind of entity', function (Closure $entity): void {
    expect(thingUnitCall('readFirstNonEmptyFieldValueFromEntity', $entity(), ['empty', 'wrapped', 'plain']))->toBe('x');
})->with([
    'entry' => [fn (): object => thingUnitAccessor(EntryModel::class, 'value', ['empty' => '', 'wrapped' => new Value('x')])],
    'entry contract' => [fn (): object => thingUnitAccessor(Entry::class, 'value', ['wrapped' => 'x'])],
    'localized term' => [fn (): object => thingUnitAccessor(LocalizedTerm::class, 'value', ['plain' => 'x'])],
    'taxonomy term' => [fn (): object => tap(Mockery::mock(TaxonomyTerm::class), fn ($t) => $t->shouldReceive('inDefaultLocale')->andReturn(thingUnitAccessor(LocalizedTerm::class, 'value', ['wrapped' => 'x'])))],
    'global variables' => [fn (): object => thingUnitAccessor(Variables::class, 'get', ['empty' => [], 'plain' => 'x'])],
    'asset' => [fn (): object => tap(Mockery::mock(Asset::class), fn ($a) => $a->shouldReceive('data')->andReturn(collect(['wrapped' => 'x'])))],
]);

it('reads no field from something that is not an entity', function (): void {
    expect(thingUnitCall('readFirstNonEmptyFieldValueFromEntity', new stdClass, ['a']))->toBeNull()
        ->and(thingUnitCall('readFirstNonEmptyFieldValueFromEntity', thingUnitAccessor(EntryModel::class, 'value', []), ['a', 'b']))->toBeNull();
});

// ---
// Replicator rows
// ---

it('turns each enabled replicator row into a target when no relationship field is named', function (): void {
    $rows = [
        'not a row',
        ['type' => 'video', 'enabled' => false, 'value' => ['url' => 'https://example.test/off']],
        ['type' => 'video', '_id' => 'r2', 'value' => ['url' => ' https://example.test/v ', 'title' => 'V']],
        ['type' => 'clip', 'id' => 'r3', 'enabled' => true, 'title' => 'Flat'],
    ];

    expect(thingUnitCall('extractReplicatorTargets', $rows, []))->toBe([
        [
            'entity' => 'https://example.test/v',
            'template_context' => [
                'replicator_row' => ['url' => ' https://example.test/v ', 'title' => 'V'],
                'replicator_row_id' => 'r2',
                'replicator_row_index' => 2,
                'replicator_row_position' => 3,
                'replicator_row_type' => 'video',
            ],
        ],
        [
            'entity' => null,
            'template_context' => [
                'replicator_row' => ['id' => 'r3', 'title' => 'Flat'],
                'replicator_row_id' => 'r3',
                'replicator_row_index' => 3,
                'replicator_row_position' => 4,
                'replicator_row_type' => 'clip',
            ],
        ],
    ]);
});

it('reads a row without an id or a type as null in its template context', function (): void {
    $targets = thingUnitCall('extractReplicatorTargets', [['value' => ['title' => 'x']]], []);

    expect($targets[0]['template_context']['replicator_row_id'])->toBeNull()
        ->and($targets[0]['template_context']['replicator_row_type'])->toBeNull();
});

it('turns each relationship item of each enabled row into a target', function (): void {
    $rows = collect([
        'x' => ['type' => 'people', '_id' => 'r0', 'value' => ['people' => ['p1', 'p2']]],
        'y' => 'not a row',
        'z' => ['type' => 'people', 'enabled' => '0', 'value' => ['people' => ['off']]],
        'w' => ['type' => 'people', 'id' => 'r3', 'members' => 'p3'],
        'v' => ['type' => 'people', 'value' => ['people' => []]],
    ]);

    $context = fn (array $value, ?string $id, int $index): array => [
        'replicator_row' => $value,
        'replicator_row_id' => $id,
        'replicator_row_index' => $index,
        'replicator_row_position' => $index + 1,
        'replicator_row_type' => 'people',
    ];

    expect(thingUnitCall('extractReplicatorTargets', $rows, ['people', 'members']))->toBe([
        ['entity' => 'p1', 'template_context' => $context(['people' => ['p1', 'p2']], 'r0', 0)],
        ['entity' => 'p2', 'template_context' => $context(['people' => ['p1', 'p2']], 'r0', 0)],
        ['entity' => 'p3', 'template_context' => $context(['id' => 'r3', 'members' => 'p3'], 'r3', 3)],
    ]);
});

// ---
// Subject matter keywords
// ---

it('reads keywords from a title, alternate names and same-as URLs', function (array $data, array $expected): void {
    expect(thingUnitCall('keywordStringsFromData', $data))->toBe($expected);
})->with([
    'all three' => [[
        'title' => 'Cardiology',
        'alternate_name' => [['text' => 'Heart'], 'not a row', ['text' => ''], ['text' => 5], ['other' => 'x'], ['text' => 'Cardio']],
        'same_as' => [['url' => 'https://www.wikidata.org/wiki/Q10379']],
    ], ['Cardiology', 'Heart', 'Cardio', 'https://www.wikidata.org/wiki/Q10379']],
    'empty title' => [['title' => '', 'same_as' => [['url' => 'u']]], ['u']],
    'non-string title' => [['title' => 5], []],
    'rows that are not a list' => [['title' => 'T', 'alternate_name' => 'Heart', 'same_as' => null], ['T']],
    'nothing' => [[], []],
]);

it('reads keywords from a term default locale, or none without one', function (): void {
    $localized = Mockery::mock(LocalizedTerm::class);
    $localized->shouldReceive('data')->andReturn(collect(['title' => 'Topic']));
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('inDefaultLocale')->andReturn($localized);
    $missing = Mockery::mock(TaxonomyTerm::class);
    $missing->shouldReceive('inDefaultLocale')->andReturnNull();

    expect(thingUnitCall('keywordStringsFromTaxonomyTermDefaultLocale', $term))->toBe(['Topic'])
        ->and(thingUnitCall('keywordStringsFromTaxonomyTermDefaultLocale', $missing))->toBe([]);
});
