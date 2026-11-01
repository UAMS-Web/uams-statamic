<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaObjectFragmentBuilder;
use Statamic\Fields\Value;

covers(SchemaObjectFragmentBuilder::class);

/**
 * Unit tests for JSON-LD fragment encoding from `type` and `properties`.
 *
 * Every mutant of the class is killed by this file alone, without
 * --covered-only (UAMS-Web/uams-statamic#320).
 *
 * @command  php artisan test --compact tests/Unit/Services/SchemaTemplates/SchemaObjectFragmentBuilderTest.php
 */
it('expands a short type name to an HTTPS schema.org URI', function () {
    expect(SchemaObjectFragmentBuilder::normalizedType('Person'))
        ->toBe('https://schema.org/Person');
});

it('preserves an HTTPS schema.org URI', function () {
    expect(SchemaObjectFragmentBuilder::normalizedType('https://schema.org/Person'))
        ->toBe('https://schema.org/Person');
});

it('upgrades an HTTP schema.org URI to HTTPS', function () {
    expect(SchemaObjectFragmentBuilder::normalizedType('http://schema.org/Organization'))
        ->toBe('https://schema.org/Organization');
});

it('returns null for a blank type', function () {
    expect(SchemaObjectFragmentBuilder::normalizedType('   '))->toBeNull();
});

it('builds JSON for a person and an organization', function () {
    $personJson = SchemaObjectFragmentBuilder::buildJson(
        'Person',
        ['name' => 'John Smith']
    );

    expect($personJson)->not->toBeNull();
    expect(json_decode($personJson ?? '', true))->toBe([
        '@type' => 'https://schema.org/Person',
        'name' => 'John Smith',
    ]);

    $orgJson = SchemaObjectFragmentBuilder::buildJson(
        'https://schema.org/Organization',
        ['name' => 'Acme Inc.']
    );

    expect($orgJson)->not->toBeNull();
    expect(json_decode($orgJson ?? '', true))->toBe([
        '@type' => 'https://schema.org/Organization',
        'name' => 'Acme Inc.',
    ]);
});

it('returns null when properties are missing', function () {
    expect(SchemaObjectFragmentBuilder::buildJson('Person', null))->toBeNull();
    expect(SchemaObjectFragmentBuilder::buildJson('Person', []))->toBeNull();
});

it('builds JSON from a properties-only payload', function () {
    $json = SchemaObjectFragmentBuilder::buildJson(
        'QuantitativeValue',
        [
            'value' => 1080,
            'unitCode' => 'E37',
            'unitText' => 'pixel',
        ]
    );

    expect($json)->not->toBeNull();
    expect(json_decode($json ?? '', true))->toBe([
        '@type' => 'https://schema.org/QuantitativeValue',
        'value' => 1080,
        'unitCode' => 'E37',
        'unitText' => 'pixel',
    ]);
});

it('builds JSON from a properties JSON string', function () {
    $json = SchemaObjectFragmentBuilder::buildJson(
        'QuantitativeValue',
        '{"value":720,"unitText":"pixel"}'
    );

    expect($json)->not->toBeNull();
    expect(json_decode($json ?? '', true))->toBe([
        '@type' => 'https://schema.org/QuantitativeValue',
        'value' => 720,
        'unitText' => 'pixel',
    ]);
});

it('builds JSON with multiple properties in the map', function () {
    $json = SchemaObjectFragmentBuilder::buildJson(
        'Person',
        [
            'name' => 'Jane',
            'honorificPrefix' => 'Dr',
        ]
    );

    expect($json)->not->toBeNull();
    expect(json_decode($json ?? '', true))->toBe([
        '@type' => 'https://schema.org/Person',
        'name' => 'Jane',
        'honorificPrefix' => 'Dr',
    ]);
});

it('ignores a top-level @type supplied via properties', function () {
    $json = SchemaObjectFragmentBuilder::buildJson(
        'Person',
        [
            '@type' => 'https://schema.org/Organization',
            'name' => 'Pat',
            'honorificSuffix' => 'PhD',
        ]
    );

    expect($json)->not->toBeNull();

    $decoded = json_decode($json ?? '', true);
    expect(data_get($decoded, '@type'))->toBe('https://schema.org/Person');
    expect(data_get($decoded, 'name'))->toBe('Pat');
    expect(data_get($decoded, 'honorificSuffix'))->toBe('PhD');
});

it('preserves @type on a nested object', function () {
    $json = SchemaObjectFragmentBuilder::buildJson(
        'Thing',
        [
            'subjectOf' => [
                '@type' => 'CreativeWork',
                'name' => 'Article',
            ],
        ]
    );

    expect($json)->not->toBeNull();
    expect(json_decode($json ?? '', true))->toBe([
        '@type' => 'https://schema.org/Thing',
        'subjectOf' => [
            '@type' => 'https://schema.org/CreativeWork',
            'name' => 'Article',
        ],
    ]);
});

it('builds JSON for an occupation with an O*NET category', function () {
    // Occupation with O*NET CategoryCode matches media-rep schema output shape.
    $properties = [
        'name' => 'News Analysts, Reporters, and Journalists',
        'occupationalCategory' => [
            '@type' => 'https://schema.org/CategoryCode',
            'codeValue' => '27-3023.00',
            'name' => 'News Analysts, Reporters, and Journalists',
            'url' => 'https://www.onetonline.org/link/summary/27-3023.00',
            'inCodeSet' => [
                '@type' => 'https://schema.org/CategoryCodeSet',
                'name' => 'O*NET-SOC 2019',
                'url' => 'https://www.onetonline.org/',
            ],
        ],
    ];

    $json = SchemaObjectFragmentBuilder::buildJson(
        'https://schema.org/Occupation',
        $properties
    );

    expect($json)->not->toBeNull();
    expect(json_decode($json ?? '', true))->toBe([
        '@type' => 'https://schema.org/Occupation',
        'name' => 'News Analysts, Reporters, and Journalists',
        'occupationalCategory' => [
            '@type' => 'https://schema.org/CategoryCode',
            'codeValue' => '27-3023.00',
            'name' => 'News Analysts, Reporters, and Journalists',
            'url' => 'https://www.onetonline.org/link/summary/27-3023.00',
            'inCodeSet' => [
                '@type' => 'https://schema.org/CategoryCodeSet',
                'name' => 'O*NET-SOC 2019',
                'url' => 'https://www.onetonline.org/',
            ],
        ],
    ]);
});

it('unwraps a Statamic Value property', function () {
    $json = SchemaObjectFragmentBuilder::buildJson(
        'Person',
        ['name' => new Value('  Jane  ')]
    );

    expect($json)->not->toBeNull();
    expect(data_get(json_decode($json ?? '', true), 'name'))->toBe('Jane');
});

it('strips HTML tags from a string property', function () {
    $json = SchemaObjectFragmentBuilder::buildJson(
        'Thing',
        ['name' => '<b>Safe</b>']
    );

    expect($json)->not->toBeNull();
    expect(data_get(json_decode($json ?? '', true), 'name'))->toBe('Safe');
});

it('normalizes a type token', function (string $type, ?string $expected): void {
    expect(SchemaObjectFragmentBuilder::normalizedType($type))->toBe($expected);
})->with([
    'padded short name' => [' Person ', 'https://schema.org/Person'],
    'padded HTTPS URI' => [' https://schema.org/Person ', 'https://schema.org/Person'],
    'padded HTTP URI' => [' http://schema.org/Place ', 'https://schema.org/Place'],
    'empty' => ['', null],
    'another vocabulary' => ['https://example.test/Person', 'https://schema.org/https://example.test/Person'],
]);

it('coerces the properties parameter to an array', function (mixed $properties, array $expected): void {
    expect(SchemaObjectFragmentBuilder::coercePropertiesToArray($properties))->toBe($expected);
})->with([
    'null' => [null, []],
    'array' => [['name' => 'A'], ['name' => 'A']],
    'padded JSON object' => ['  {"name":"A"}  ', ['name' => 'A']],
    'blank string' => ['   ', []],
    'invalid JSON' => ['{name', []],
    'JSON scalar' => ['5', []],
    'integer' => [5, []],
    'object' => [new stdClass, []],
]);

it('keeps only named, non-empty, non-reserved top-level properties', function (): void {
    expect(SchemaObjectFragmentBuilder::normalizedPropertiesForFragment([
        ' name ' => ' A ',
        '' => 'x',
        '   ' => 'x',
        ' @type ' => 'Organization',
        'empty' => '',
        'missing' => null,
        'zero' => 0,
        'no' => false,
    ]))->toBe(['name' => 'A', 'zero' => 0, 'no' => false]);
});

it('skips list positions in the top-level properties instead of failing', function (mixed $properties): void {
    expect(SchemaObjectFragmentBuilder::normalizedPropertiesForFragment($properties))->toBe(['name' => 'A'])
        ->and(SchemaObjectFragmentBuilder::buildJson('Person', ['x', 'y']))->toBeNull();
})->with([
    'array' => [['x', 'name' => 'A']],
    'JSON object with a numeric key' => ['{"0":"x","name":"A"}'],
]);

it('normalizes property values', function (mixed $value, mixed $expected): void {
    expect(SchemaObjectFragmentBuilder::normalizeJsonLdValuePublic($value))->toBe($expected);
})->with([
    'true' => [true, true],
    'false' => [false, false],
    'zero' => [0, 0],
    'float' => [1.5, 1.5],
    'padded HTML' => ['  <p> A </p>  ', 'A'],
    'empty markup' => [' <p> </p> ', null],
    'null' => [null, null],
    'nested Value' => [new Value(new Value(' A ')), 'A'],
    'Value of null' => [new Value(null), null],
    'object' => [new stdClass, null],
    'empty array' => [[], null],
    'list' => [['a', null, '', ' b ', ['c']], ['a', 'b', ['c']]],
    'list of omitted items' => [[null, '', []], null],
    'map with odd keys' => [[3 => 'x', ' k ' => 'v', '' => 'x', 'z' => 'w', '  ' => 'x', 'y' => 'u', 'gone' => null], ['k' => 'v', 'z' => 'w', 'y' => 'u']],
    'map of omitted values' => [['a' => null, 'b' => ''], null],
    'map with a Value type' => [['@type' => new Value('Person'), 'name' => 'A'], ['@type' => 'https://schema.org/Person', 'name' => 'A']],
    'map with a padded type key' => [[' @type ' => 'Person'], ['@type' => 'https://schema.org/Person']],
    'map with a non-string type' => [['@type' => 5, 'name' => 'A'], ['name' => 'A']],
    'map with an empty type' => [['@type' => ' ', 'name' => 'A'], ['name' => 'A']],
    'map with only an unusable type' => [['@type' => 5], null],
]);

it('encodes slashes and unicode unescaped', function (): void {
    expect(SchemaObjectFragmentBuilder::buildJson('Person', ['url' => 'https://example.test/é']))
        ->toBe('{"@type":"https://schema.org/Person","url":"https://example.test/é"}');
});

it('returns null for a blank type even with properties', function (): void {
    expect(SchemaObjectFragmentBuilder::buildJson(' ', ['name' => 'A']))->toBeNull();
});

it('returns null when every property normalizes away', function (): void {
    expect(SchemaObjectFragmentBuilder::buildJson('Person', ['name' => '', '@type' => 'Thing']))->toBeNull();
});

it('returns null when the payload cannot be encoded', function (mixed $value): void {
    expect(SchemaObjectFragmentBuilder::buildJson('Person', ['name' => $value]))->toBeNull();
})->with([
    'invalid UTF-8' => ["\xB1\x31"],
    'infinity' => [INF],
]);
