<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaClassificationsIdentifiersMerger;
use App\Services\SchemaTemplates\SchemaOutputService;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Entries\Entry as EntryModel;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Unit tests for SchemaClassificationsIdentifiersMerger.
 *
 * Mutation: not recorded. An earlier figure here (100% on App\Services\SchemaTemplates\SchemaClassificationsIdentifiersMerger,
 * 142 mutants, --covered-only) named no command or revision and was removed (UAMS-Web/uams-statamic-archive#2915).
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaClassificationsIdentifiersMergerTest.php
 */

/**
 * Create a minimal entry stub that returns one replicator value for the given key.
 *
 * @param  string|null  $value  Value to return from first row, or null for empty
 * @return EntryModel&MockInterface
 */
function schemaClassificationsIdentifiersMergerEntryWithReplicatorValue(string $key, ?string $value): EntryModel
{
    return schemaClassificationsIdentifiersMergerEntryWithReplicatorValueAndId($key, $value, null);
}

/**
 * Create a minimal entry stub with replicator value and optional id() stub.
 *
 * @param  string|null  $value  Value to return from first row, or null for empty
 * @param  string|null  $entryId  Entry id for id() (null to leave unstubbed)
 * @return EntryModel&MockInterface
 */
function schemaClassificationsIdentifiersMergerEntryWithReplicatorValueAndId(
    string $key,
    ?string $value,
    ?string $entryId
): EntryModel {
    $rows = $value !== null
        ? [['type' => $key, 'value' => $value, 'enabled' => true]]
        : [];

    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldIgnoreMissing();
    $entry->shouldReceive('get')->andReturnUsing(
        fn (string $k) => $k === $key ? $rows : null
    );
    if ($entryId !== null) {
        $entry->shouldReceive('id')->andReturn($entryId);
    }

    return $entry;
}

/**
 * Create a minimal entry stub that returns replicator values for multiple keys.
 *
 * @param  array<string, string>  $keyToValue  Map of field key => first row value
 * @return EntryModel&MockInterface
 */
function schemaClassificationsIdentifiersMergerEntryWithMultipleReplicatorValues(array $keyToValue): EntryModel
{
    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldIgnoreMissing();
    $entry->shouldReceive('get')->andReturnUsing(
        function (string $k) use ($keyToValue) {
            if (! array_key_exists($k, $keyToValue)) {
                return null;
            }

            $v = $keyToValue[$k];

            return [['type' => $k, 'value' => $v, 'enabled' => true]];
        }
    );

    return $entry;
}

/**
 * Create a minimal entry stub that returns multiple replicator rows for one key.
 *
 * @param  array<int, string>  $values  One row per value (all enabled)
 * @return EntryModel&MockInterface
 */
function schemaClassificationsIdentifiersMergerEntryWithReplicatorRows(string $key, array $values): EntryModel
{
    $rows = array_map(
        fn (string $v) => ['type' => $key, 'value' => $v, 'enabled' => true],
        $values
    );

    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldIgnoreMissing();
    $entry->shouldReceive('get')->andReturnUsing(
        fn (string $k) => $k === $key ? $rows : null
    );

    return $entry;
}

it('adds the duns fragment when the entry has a duns value', function () {
    $entry = schemaClassificationsIdentifiersMergerEntryWithReplicatorValue('duns', '122452563');
    $merger = app(SchemaClassificationsIdentifiersMerger::class);
    $node = ['@id' => '#test', '@type' => 'Organization', 'name' => 'Test'];

    $result = $merger->merge($entry, $node);

    expect(data_get($result, 'duns'))->toBe('122452563');
    expect(data_get($result, 'iso6523Code'))->toBe('0060:122452563');
    expect($result)->toHaveKey('identifier');
    expect(data_get($result, 'identifier'))->toBeArray();
    expect(data_get($result, 'identifier.@type'))->toBe('PropertyValue');
    expect(data_get($result, 'identifier.name'))->toBe('D-U-N-S');
    expect(data_get($result, 'identifier.value'))->toBe('122452563');
});

it('adds the naics fragment when the entry has a naics value', function () {
    $entry = schemaClassificationsIdentifiersMergerEntryWithReplicatorValue('naics', '611310');
    $merger = app(SchemaClassificationsIdentifiersMerger::class);
    $node = ['@id' => '#test', '@type' => 'Organization'];

    $result = $merger->merge($entry, $node);

    expect(data_get($result, 'naics'))->toBe('611310');
    expect($result)->toHaveKey('identifier');
    expect(data_get($result, 'identifier.name'))->toBe('NAICS code');
    expect(data_get($result, 'identifier.value'))->toBe('611310');
});

it('combines identifiers from multiple tabs', function () {
    $entry = schemaClassificationsIdentifiersMergerEntryWithMultipleReplicatorValues([
        'duns' => '122452563',
        'naics' => '611310',
    ]);
    $merger = app(SchemaClassificationsIdentifiersMerger::class);
    $node = ['@id' => '#test', '@type' => 'Organization'];

    $result = $merger->merge($entry, $node);

    expect(data_get($result, 'duns'))->toBe('122452563');
    expect(data_get($result, 'naics'))->toBe('611310');
    expect(data_get($result, 'identifier'))->toBeArray();
    expect(data_get($result, 'identifier'))->toHaveCount(2);
    $identifier = data_get($result, 'identifier');
    $names = array_column(is_array($identifier) ? $identifier : [], 'name');
    expect($names)->toContain('D-U-N-S');
    expect($names)->toContain('NAICS code');
});

it('leaves the node unchanged when there are no classification values', function () {
    $entry = schemaClassificationsIdentifiersMergerEntryWithReplicatorValue('duns', null);
    $merger = app(SchemaClassificationsIdentifiersMerger::class);
    $node = ['@id' => '#test', '@type' => 'Organization', 'name' => 'Test'];

    $result = $merger->merge($entry, $node);

    expect(data_get($result, 'name'))->toBe($node['name']);
    expect($result)->not->toHaveKey('duns');
    expect($result)->not->toHaveKey('identifier');
});

it('adds an @id to each property value using the entry id and field handle', function () {
    $entry = schemaClassificationsIdentifiersMergerEntryWithReplicatorValueAndId('duns', '122452563', 'schema-entry-uuid');
    $merger = app(SchemaClassificationsIdentifiersMerger::class);
    $node = ['@id' => 'https://uams.edu/#schema-entry-uuid', '@type' => 'Organization'];

    $result = $merger->merge($entry, $node);

    expect($result)->toHaveKey('identifier');
    expect(data_get($result, 'identifier.@id'))->toBe('https://uams.edu/#schema-entry-uuid-duns');
});

it('uses a fragment-only @id when the node @id has no base url', function () {
    $entry = schemaClassificationsIdentifiersMergerEntryWithReplicatorValueAndId('naics', '611310', 'entry-123');
    $merger = app(SchemaClassificationsIdentifiersMerger::class);
    $node = ['@id' => '#entry-123', '@type' => 'Organization'];

    $result = $merger->merge($entry, $node);

    expect(data_get($result, 'identifier.@id'))->toBe('#entry-123-naics');
});

it('resolves an antlers replicator row for the identifier field', function () {
    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldIgnoreMissing();
    $entry->shouldReceive('get')->andReturnUsing(
        fn (string $k) => $k === 'duns'
            ? [
                [
                    'type' => 'antlers',
                    'enabled' => true,
                    'value' => ['code' => '{{ title }}'],
                ],
            ]
            : null
    );
    $entry->shouldReceive('values')->andReturn(collect(['title' => '122452563']));
    $entry->shouldReceive('blueprint')->andReturn(null);

    $merger = app(SchemaClassificationsIdentifiersMerger::class);
    $node = ['@id' => '#test', '@type' => 'Organization'];

    $result = $merger->merge($entry, $node);

    expect(data_get($result, 'duns'))->toBe('122452563');
    expect(data_get($result, 'iso6523Code'))->toBe('0060:122452563');
    expect($result)->toHaveKey('identifier');
    expect(data_get($result, 'identifier.value'))->toBe('122452563');
});

it('puts multiple replicator values per key into one property value as an array', function () {
    $entry = schemaClassificationsIdentifiersMergerEntryWithReplicatorRows('naics', ['611310', '621111', '541712']);
    $merger = app(SchemaClassificationsIdentifiersMerger::class);
    $node = ['@id' => '#test', '@type' => 'Organization'];

    $result = $merger->merge($entry, $node);

    expect(data_get($result, 'naics'))->toBeArray();
    expect(data_get($result, 'naics'))->toBe(['611310', '621111', '541712']);
    // Single PropertyValue per field; value is array of strings when multiple.
    expect(data_get($result, 'identifier.@type'))->toBe('PropertyValue');
    expect(data_get($result, 'identifier.value'))->toBe(['611310', '621111', '541712']);
});

/**
 * An entry stub whose `duns` replicator holds exactly `$rows`, with the given id.
 *
 * @param  mixed  $rows  Raw replicator value for `duns`
 * @return EntryModel&MockInterface
 */
function schemaClassificationsIdentifiersMergerEntryWithRawDuns(mixed $rows, ?string $entryId = 'entry-1'): EntryModel
{
    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldIgnoreMissing();
    $entry->shouldReceive('get')->andReturnUsing(fn (string $k) => $k === 'duns' ? $rows : null);
    $entry->shouldReceive('id')->andReturn($entryId);

    return $entry;
}

it('keeps an identifier already on the node, ahead of the merged ones', function (mixed $existing, array $expectedFirst) {
    $entry = schemaClassificationsIdentifiersMergerEntryWithRawDuns([['type' => 'duns', 'value' => '122452563']]);

    $result = app(SchemaClassificationsIdentifiersMerger::class)->merge($entry, ['@id' => '#n', 'identifier' => $existing]);

    /** @var list<mixed> $identifiers */
    $identifiers = data_get($result, 'identifier');

    expect(array_slice($identifiers, 0, count($expectedFirst)))->toBe($expectedFirst)
        ->and(data_get($identifiers[count($expectedFirst)], 'name'))->toBe('D-U-N-S');
})->with([
    'a list, with blanks dropped' => [['ID-1', null, '', ['@type' => 'PropertyValue', 'value' => 'x']], ['ID-1', ['@type' => 'PropertyValue', 'value' => 'x']]],
    'one typed value' => [['@type' => 'PropertyValue', 'value' => 'x'], [['@type' => 'PropertyValue', 'value' => 'x']]],
    'a scalar' => [42, ['42']],
]);

it('drops an existing identifier that is empty or not usable', function (mixed $existing) {
    $entry = schemaClassificationsIdentifiersMergerEntryWithRawDuns([['type' => 'duns', 'value' => '122452563']]);

    $result = app(SchemaClassificationsIdentifiersMerger::class)->merge($entry, ['@id' => '#n', 'identifier' => $existing]);

    // Only the merged PropertyValue remains, as a single value rather than a list.
    expect(data_get($result, 'identifier.name'))->toBe('D-U-N-S');
})->with([
    'empty string' => [''],
    'null' => [null],
    'a map without a type' => [['value' => 'x']],
    'an object' => [new stdClass],
]);

it('keeps an existing identifier on a node with nothing to merge', function () {
    $entry = schemaClassificationsIdentifiersMergerEntryWithRawDuns(null);

    expect(app(SchemaClassificationsIdentifiersMerger::class)->merge($entry, ['@id' => '#n', 'identifier' => 'ID-1'])['identifier'])
        ->toBe('ID-1');
});

it('uses a fragment-only @id when the node @id is missing, blank, not a string, or has no fragment', function (array $node) {
    $entry = schemaClassificationsIdentifiersMergerEntryWithRawDuns([['type' => 'duns', 'value' => '122452563']], 'abc');

    /** @var array<string, mixed> $node */
    expect(data_get(app(SchemaClassificationsIdentifiersMerger::class)->merge($entry, $node), 'identifier.@id'))->toBe('#abc-duns');
})->with([
    'missing' => [[]],
    'blank' => [['@id' => '']],
    'not a string' => [['@id' => 5]],
    'no fragment' => [['@id' => 'https://uams.edu/page']],
]);

it('keeps the base url before the fragment of the node @id', function () {
    $entry = schemaClassificationsIdentifiersMergerEntryWithRawDuns([['type' => 'duns', 'value' => '122452563']], 'abc');

    expect(data_get(app(SchemaClassificationsIdentifiersMerger::class)->merge($entry, ['@id' => 'https://uams.edu/x#node']), 'identifier.@id'))
        ->toBe('https://uams.edu/x#abc-duns');
});

it('falls back to an unknown entry id in the @id', function (?string $id) {
    $entry = schemaClassificationsIdentifiersMergerEntryWithRawDuns([['type' => 'duns', 'value' => '122452563']], $id);

    expect(data_get(app(SchemaClassificationsIdentifiersMerger::class)->merge($entry, ['@id' => '#n']), 'identifier.@id'))->toBe('#unknown-duns');
})->with([
    'no id' => [null],
    'empty id' => [''],
]);

it('skips replicator rows that are not rows, disabled, empty or not scalar', function () {
    $entry = schemaClassificationsIdentifiersMergerEntryWithRawDuns([
        'not a row',
        ['type' => 'duns', 'value' => '111111111', 'enabled' => false],
        ['type' => 'duns', 'value' => null],
        ['type' => 'duns'],
        ['type' => 'duns', 'value' => ''],
        ['type' => 'duns', 'value' => ['122452563']],
        ['type' => 'duns', 'value' => 122452563],
    ]);

    $result = app(SchemaClassificationsIdentifiersMerger::class)->merge($entry, ['@id' => '#n']);

    // Only the integer row is read, as a string; a row with no enabled key is enabled.
    expect(data_get($result, 'duns'))->toBe('122452563')
        ->and(data_get($result, 'identifier.value'))->toBe('122452563');
});

it('merges nothing from a replicator value that is not a list of rows', function () {
    $entry = schemaClassificationsIdentifiersMergerEntryWithRawDuns('122452563');

    expect(app(SchemaClassificationsIdentifiersMerger::class)->merge($entry, ['@id' => '#n', 'name' => 'X']))->toBe(['@id' => '#n', 'name' => 'X']);
});

it('merges an identifier from every registered merger', function (string $sourceKey) {
    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldIgnoreMissing();
    $entry->shouldReceive('get')->andReturnUsing(
        fn (string $k) => $k === $sourceKey ? [['type' => $sourceKey, 'value' => '123456789']] : null
    );
    $entry->shouldReceive('id')->andReturn('e');

    $result = app(SchemaClassificationsIdentifiersMerger::class)->merge($entry, ['@id' => '#n']);

    expect(data_get($result, 'identifier.@type'))->toBe('PropertyValue')
        ->and(data_get($result, 'identifier.@id'))->toBe('#e-'.$sourceKey);
})->with([
    'ahaid', 'cage', 'hospital_ccn_id', 'cms_specialty_code', 'duns', 'ein', 'gln',
    'google_business_profile_cid', 'google_scholar_author_id', 'google_tag_manager_container_id',
    'health_care_provider_taxonomy_code', 'ipeds', 'isco_08_code', 'isic_rev_4_code', 'iso_6523_code',
    'legal_entity_identifier', 'naics', 'npi', 'onet_soc_occupation_code', 'ope_id', 'orcid', 'pmid',
    'pubmed_author_id', 'researcherid', 'rxcui', 'scopus_author_id', 'ticker_symbol', 'uei',
    'va_station_id', 'va_substation_id',
]);

it('keeps the scalar properties a fragment lists after its identifier', function () {
    // isco_08_code's fragment puts `identifier` before `occupationalCategory`.
    $entry = schemaClassificationsIdentifiersMergerEntryWithReplicatorValue('isco_08_code', '2512');

    expect(app(SchemaClassificationsIdentifiersMerger::class)->merge($entry, ['@id' => '#n']))->toHaveKey('occupationalCategory');
});

it('casts a numeric entry id into the property value @id', function () {
    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldIgnoreMissing();
    $entry->shouldReceive('get')->andReturnUsing(fn (string $k) => $k === 'duns' ? [['type' => 'duns', 'value' => '122452563']] : null);
    $entry->shouldReceive('id')->andReturn(42);

    expect(data_get(app(SchemaClassificationsIdentifiersMerger::class)->merge($entry, ['@id' => '#n']), 'identifier.@id'))->toBe('#42-duns');
});

it('skips an antlers row that resolves to nothing, and keeps reading the rows after it', function (mixed $resolved) {
    $antlers = ['type' => 'antlers', 'enabled' => true, 'value' => ['code' => '{{ title }}']];
    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldIgnoreMissing();
    $entry->shouldReceive('get')->andReturnUsing(fn (string $k) => $k === 'duns'
        ? [$antlers, ['type' => 'duns', 'value' => '122452563']]
        : null);

    $output = Mockery::mock(SchemaOutputService::class);
    $output->shouldReceive('resolveIdentifiersTabAntlersRow')->once()->andReturn($resolved);
    app()->instance(SchemaOutputService::class, $output);

    $result = app(SchemaClassificationsIdentifiersMerger::class)->merge($entry, ['@id' => '#n']);

    expect(data_get($result, 'duns'))->toBe('122452563')
        ->and(data_get($result, 'identifier.value'))->toBe('122452563');
})->with([
    'null' => [null],
    'empty' => [''],
]);

it('reads every row after an antlers row that resolves', function () {
    $antlers = ['type' => 'antlers', 'enabled' => true, 'value' => ['code' => '{{ title }}']];
    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldIgnoreMissing();
    $entry->shouldReceive('get')->andReturnUsing(fn (string $k) => $k === 'naics'
        ? [$antlers, ['type' => 'naics', 'value' => '611310']]
        : null);

    $output = Mockery::mock(SchemaOutputService::class);
    $output->shouldReceive('resolveIdentifiersTabAntlersRow')->once()->andReturn('541511');
    app()->instance(SchemaOutputService::class, $output);

    expect(data_get(app(SchemaClassificationsIdentifiersMerger::class)->merge($entry, ['@id' => '#n']), 'identifier.value'))->toBe(['541511', '611310']);
});
