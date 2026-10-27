<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaObjectFragmentBuilder;
use Statamic\Fields\Value;

/**
 * Unit tests for JSON-LD fragment encoding from `type` and `properties`.
 *
 * Mutation: 100% on App\Services\SchemaTemplates\SchemaObjectFragmentBuilder (99 mutants, --covered-only).
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
