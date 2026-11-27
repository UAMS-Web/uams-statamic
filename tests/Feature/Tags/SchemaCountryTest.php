<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaJsonLdAtIdDeduplication;
use App\Services\SchemaTemplates\SchemaJsonLdFragmentAtId;
use App\Services\SchemaTemplates\SchemaOutputService;
use App\Services\SchemaTemplates\SchemaTemplateEmitContext;
use App\Services\SchemaTemplates\SchemaTemplateEmitContextRuntimeStack;
use App\Services\SchemaTemplates\SchemaTemplateInstanceWatermarkBag;
use App\Tags\SchemaCountry;
use Statamic\Facades\Antlers;
use Statamic\Fields\Value;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Unit tests for the `schema_country` Antlers tag (`SchemaCountry`).
 *
 * Covers JSON-LD `Country` output: canonical `@id` via
 * `SchemaJsonLdFragmentAtId`, Wikidata-backed `identifier` values, scalar and
 * list `code`, `code_type` resolution, `SchemaTemplateEmitContext` compact
 * references, invalid or empty input, and a JSON encoding failure.
 *
 * Mutation: 100% on App\Tags\SchemaCountry (121 mutants, without --covered-only).
 * Six equivalent mutants are annotated in the class.
 *
 * @command  php artisan test --compact tests/Feature/Tags/SchemaCountryTest.php
 */

/**
 * Build the canonical `@id` string the tag uses for a given alpha-2 code.
 *
 * @param  string  $iso3166Alpha2Uppercase  ISO 3166-1 alpha-2 (e.g., `US`)
 * @return string Same `@id` the production tag emits for that case
 */
function schemaCountryExpectedAtId(string $iso3166Alpha2Uppercase): string
{
    // Build the lowercased path segment used for URL-safe Base64 encoding
    $countryPathSegment = strtolower("country/{$iso3166Alpha2Uppercase}");

    // Match production helper: root-relative path then shared fragment @id
    $path = "/{$countryPathSegment}";

    return SchemaJsonLdFragmentAtId::atIdFromRootRelativePath($path);
}

/**
 * Run the tag directly, so that a parameter can hold a value Antlers syntax
 * cannot express.
 *
 * @param  array<string, mixed>  $parameters  Tag parameters
 * @param  array<string, mixed>  $context  Antlers context
 * @return string Raw tag output
 */
function schemaCountryTagOutput(array $parameters, array $context = []): string
{
    $tag = app(SchemaCountry::class);

    // The parameters are resolved against the context, so the context goes first
    $tag->setContext($context);
    $tag->setParameters($parameters);

    return $tag->index();
}

/**
 * Reduce a decoded `Country` map to its name and its three identifier values.
 *
 * @param  mixed  $country  Decoded `Country` map
 * @return list<mixed> Name, alpha-2, alpha-3 and numeric values
 */
function schemaCountrySummary(mixed $country): array
{
    return [
        data_get($country, 'name'),
        data_get($country, 'identifier.0.value'),
        data_get($country, 'identifier.1.value'),
        data_get($country, 'identifier.2.value'),
    ];
}

/**
 * Reduce a JSON array of `Country` objects to one summary per country.
 *
 * @param  string  $json  Raw tag output
 * @return list<list<mixed>>|null Summaries in output order, or null when the output is not a JSON array
 */
function schemaCountryListSummaries(string $json): ?array
{
    $decoded = json_decode($json, true);

    if (! is_array($decoded)) {
        return null;
    }

    return array_values(array_map(schemaCountrySummary(...), $decoded));
}

/*
 * Clear the tag's process-wide lookup maps, so that every test builds the map it
 * reads. A mutant's child runs only the tests that cover the mutated line, and a
 * map left over from an earlier test hides the build lines from the later ones.
 */
beforeEach(function () {
    foreach (['countryByUpperAlpha3', 'countryByNumericIntKey'] as $property) {
        (new ReflectionProperty(SchemaCountry::class, $property))->setValue(null, null);
    }
});

it('resolves the tag handle to schema_country', function () {
    expect(SchemaCountry::handle())->toBe('schema_country');
});

it('emits the expected json-ld for a default alpha2 US code', function () {
    $output = trim((string) Antlers::parse(
        '{{ schema_country code="US" }}',
        [],
        true
    ));

    $expected = [
        '@id' => schemaCountryExpectedAtId('US'),
        '@type' => 'Country',
        'name' => 'United States of America',
        'alternateName' => 'the United States of America',
        'identifier' => [
            [
                '@type' => 'PropertyValue',
                'propertyID' => 'https://www.wikidata.org/wiki/Property:P297',
                'name' => 'ISO 3166-1 alpha-2 code',
                'value' => 'US',
            ],
            [
                '@type' => 'PropertyValue',
                'propertyID' => 'https://www.wikidata.org/wiki/Property:P298',
                'name' => 'ISO 3166-1 alpha-3 code',
                'value' => 'USA',
            ],
            [
                '@type' => 'PropertyValue',
                'propertyID' => 'https://www.wikidata.org/wiki/Property:P299',
                'name' => 'ISO 3166-1 numeric code',
                'value' => '840',
            ],
        ],
    ];

    expect(json_decode($output, true))->toBe($expected);
});

it('omits alternateName for France with explicit alpha2 code_type', function () {
    $output = trim((string) Antlers::parse(
        '{{ schema_country code="FR" code_type="alpha2" }}',
        [],
        true
    ));

    $decoded = json_decode($output, true);

    expect($decoded)->toBeArray();
    expect($decoded)->not->toHaveKey('alternateName');
    expect(data_get($decoded, 'name'))->toBe('France');
    expect(data_get($decoded, '@id'))->toBe(schemaCountryExpectedAtId('FR'));
    expect(data_get($decoded, 'identifier.0.value'))->toBe('FR');
    expect(data_get($decoded, 'identifier.1.value'))->toBe('FRA');
    expect(data_get($decoded, 'identifier.2.value'))->toBe('250');
});

it('resolves Spain by alpha3 and uses the tag code for the alpha3 slot', function () {
    $output = trim((string) Antlers::parse(
        '{{ schema_country code="ESP" code_type="alpha3" }}',
        [],
        true
    ));

    $expected = [
        '@id' => schemaCountryExpectedAtId('ES'),
        '@type' => 'Country',
        'name' => 'Spain',
        'identifier' => [
            [
                '@type' => 'PropertyValue',
                'propertyID' => 'https://www.wikidata.org/wiki/Property:P297',
                'name' => 'ISO 3166-1 alpha-2 code',
                'value' => 'ES',
            ],
            [
                '@type' => 'PropertyValue',
                'propertyID' => 'https://www.wikidata.org/wiki/Property:P298',
                'name' => 'ISO 3166-1 alpha-3 code',
                'value' => 'ESP',
            ],
            [
                '@type' => 'PropertyValue',
                'propertyID' => 'https://www.wikidata.org/wiki/Property:P299',
                'name' => 'ISO 3166-1 numeric code',
                'value' => '724',
            ],
        ],
    ];

    expect(json_decode($output, true))->toBe($expected);
});

it('resolves the Cayman Islands by numeric code', function () {
    $output = trim((string) Antlers::parse(
        '{{ schema_country code="136" code_type="numeric" }}',
        [],
        true
    ));

    $expected = [
        '@id' => schemaCountryExpectedAtId('KY'),
        '@type' => 'Country',
        'name' => 'Cayman Islands',
        'alternateName' => 'the Cayman Islands',
        'identifier' => [
            [
                '@type' => 'PropertyValue',
                'propertyID' => 'https://www.wikidata.org/wiki/Property:P297',
                'name' => 'ISO 3166-1 alpha-2 code',
                'value' => 'KY',
            ],
            [
                '@type' => 'PropertyValue',
                'propertyID' => 'https://www.wikidata.org/wiki/Property:P298',
                'name' => 'ISO 3166-1 alpha-3 code',
                'value' => 'CYM',
            ],
            [
                '@type' => 'PropertyValue',
                'propertyID' => 'https://www.wikidata.org/wiki/Property:P299',
                'name' => 'ISO 3166-1 numeric code',
                'value' => '136',
            ],
        ],
    ];

    expect(json_decode($output, true))->toBe($expected);
});

it('outputs an empty string for an unknown alpha2 code', function () {
    $output = trim((string) Antlers::parse(
        '{{ schema_country code="__" code_type="alpha2" }}',
        [],
        true
    ));

    expect($output)->toBe('');
});

it('outputs an empty string for an empty scalar code', function () {
    $output = trim((string) Antlers::parse(
        '{{ schema_country code="" }}',
        [],
        true
    ));

    expect($output)->toBe('');
});

it('emits a country list for a bound array code without context', function () {
    $output = trim((string) Antlers::parse(
        '{{ schema_country :code="nationality" }}',
        [
            'nationality' => [
                'US',
                'FR',
            ],
        ],
        true
    ));

    $expected = [
        [
            '@id' => schemaCountryExpectedAtId('US'),
            '@type' => 'Country',
            'name' => 'United States of America',
            'alternateName' => 'the United States of America',
            'identifier' => [
                [
                    '@type' => 'PropertyValue',
                    'propertyID' => 'https://www.wikidata.org/wiki/Property:P297',
                    'name' => 'ISO 3166-1 alpha-2 code',
                    'value' => 'US',
                ],
                [
                    '@type' => 'PropertyValue',
                    'propertyID' => 'https://www.wikidata.org/wiki/Property:P298',
                    'name' => 'ISO 3166-1 alpha-3 code',
                    'value' => 'USA',
                ],
                [
                    '@type' => 'PropertyValue',
                    'propertyID' => 'https://www.wikidata.org/wiki/Property:P299',
                    'name' => 'ISO 3166-1 numeric code',
                    'value' => '840',
                ],
            ],
        ],
        [
            '@id' => schemaCountryExpectedAtId('FR'),
            '@type' => 'Country',
            'name' => 'France',
            'identifier' => [
                [
                    '@type' => 'PropertyValue',
                    'propertyID' => 'https://www.wikidata.org/wiki/Property:P297',
                    'name' => 'ISO 3166-1 alpha-2 code',
                    'value' => 'FR',
                ],
                [
                    '@type' => 'PropertyValue',
                    'propertyID' => 'https://www.wikidata.org/wiki/Property:P298',
                    'name' => 'ISO 3166-1 alpha-3 code',
                    'value' => 'FRA',
                ],
                [
                    '@type' => 'PropertyValue',
                    'propertyID' => 'https://www.wikidata.org/wiki/Property:P299',
                    'name' => 'ISO 3166-1 numeric code',
                    'value' => '250',
                ],
            ],
        ],
    ];

    expect(json_decode($output, true))->toBe($expected);
});

it('emits an empty json array for an empty list code', function () {
    $output = trim((string) Antlers::parse(
        '{{ schema_country :code="nationality" }}',
        [
            'nationality' => [],
        ],
        true
    ));

    expect(json_decode($output, true))->toBe([]);
});

it('skips unknown entries in a list code', function () {
    $output = trim((string) Antlers::parse(
        '{{ schema_country :code="nationality" }}',
        [
            'nationality' => [
                '__',
                'DE',
            ],
        ],
        true
    ));

    $decoded = json_decode($output, true);

    expect($decoded)->toBeArray();
    expect($decoded)->toHaveCount(1);
    expect(data_get($decoded, '0.name'))->toBe('Germany');
    expect(data_get($decoded, '0.@id'))->toBe(schemaCountryExpectedAtId('DE'));
});

it('outputs a compact reference when the @id was already emitted in the context', function () {
    // Initialize empty entry-id tracking for the emit context constructor
    $emittedEntryIds = [];

    // Pre-register the canonical US country @id as already fully emitted
    $usAtId = schemaCountryExpectedAtId('US');
    $emittedNodeIds = [
        $usAtId => true,
    ];

    $materializedFullNodeAtIds = [];

    // Build the same emit context shape `SchemaOutputService` injects
    $emitContext = new SchemaTemplateEmitContext(
        $emittedEntryIds,
        $emittedNodeIds,
        $materializedFullNodeAtIds,
        [],
        null,
        new SchemaTemplateInstanceWatermarkBag
    );

    // Parse the tag with emit context bound in Antlers scope
    $output = trim((string) Antlers::parse(
        '{{ schema_country code="US" }}',
        [
            SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_EMIT_CONTEXT => $emitContext,
        ],
        true
    ));

    // Assert only the compact @id reference is emitted
    expect(json_decode($output, true))->toBe([
        '@id' => $usAtId,
    ]);
});

it('records the full node after emit without persisting new full ids to the shared map', function () {
    // Start with empty shared emit maps
    $emittedEntryIds = [];
    $emittedNodeIds = [];

    $materializedFullNodeAtIds = [];

    // Build emit context bound to those arrays by reference
    $emitContext = new SchemaTemplateEmitContext(
        $emittedEntryIds,
        $emittedNodeIds,
        $materializedFullNodeAtIds,
        [],
        null,
        new SchemaTemplateInstanceWatermarkBag
    );

    // Emit a full US country JSON string inside schema-template scope
    $output = trim((string) Antlers::parse(
        '{{ schema_country code="US" }}',
        [
            SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_EMIT_CONTEXT => $emitContext,
        ],
        true
    ));

    // Decode the tag JSON the same way `SchemaOutputService` does
    $decoded = json_decode($output, true);
    expect($decoded)->toBeArray();

    // Run production decode-time dedupe (shared map is not updated for new full ids)
    $afterDedupe = app(SchemaJsonLdAtIdDeduplication::class)->dedupeWithSharedEmitMapAndDecodeFirstFullRetention(
        $decoded,
        $emittedNodeIds
    );

    // Assert the shared map stays empty for this decode wrapper
    expect($emittedNodeIds)->toBe([]);

    // Assert the first full Country node is retained inline with canonical @id
    $usAtId = schemaCountryExpectedAtId('US');
    expect($afterDedupe)->toBeArray();
    expect(data_get($afterDedupe, '@id'))->toBe($usAtId);
    expect(data_get($afterDedupe, '@type'))->toBe('Country');
    expect(data_get($afterDedupe, 'name'))->toBe('United States of America');
});

it('resolves a code and code_type after normalizing them', function (string $code, mixed $codeType, array $expected) {
    $decoded = json_decode(schemaCountryTagOutput([
        'code' => $code,
        'code_type' => $codeType,
    ]), true);

    expect(schemaCountrySummary($decoded))->toBe($expected);
})->with([
    'lowercase alpha2' => ['us', null, ['United States of America', 'US', 'USA', '840']],
    'padded alpha2' => [' us ', null, ['United States of America', 'US', 'USA', '840']],
    'lowercase alpha3' => ['esp', 'alpha3', ['Spain', 'ES', 'ESP', '724']],
    'alpha-3 alias' => ['ESP', 'alpha-3', ['Spain', 'ES', 'ESP', '724']],
    'uppercase code_type' => ['ESP', 'ALPHA3', ['Spain', 'ES', 'ESP', '724']],
    'padded code_type' => ['ESP', ' alpha3 ', ['Spain', 'ES', 'ESP', '724']],
    'alpha-2 alias' => ['US', 'alpha-2', ['United States of America', 'US', 'USA', '840']],
    'empty code_type' => ['US', '', ['United States of America', 'US', 'USA', '840']],
    'null code_type' => ['US', null, ['United States of America', 'US', 'USA', '840']],
    'non-string code_type' => ['US', 3, ['United States of America', 'US', 'USA', '840']],
    'unknown code_type' => ['US', 'bogus', ['United States of America', 'US', 'USA', '840']],
    'numeric with leading zeros' => ['004', 'numeric', ['Afghanistan', 'AF', 'AFG', '004']],
    'numeric without leading zeros' => ['4', 'numeric', ['Afghanistan', 'AF', 'AFG', '4']],
]);

it('unwraps a Value that is still wrapped after the parameters are resolved', function () {
    // Parameters::make() unwraps one level, so a nested Value reaches the tag
    $fromCode = json_decode(schemaCountryTagOutput([
        'code' => new Value(new Value('US')),
    ]), true);

    $fromCodeType = json_decode(schemaCountryTagOutput([
        'code' => 'ESP',
        'code_type' => new Value(new Value('alpha3')),
    ]), true);

    expect(schemaCountrySummary($fromCode))->toBe(['United States of America', 'US', 'USA', '840']);
    expect(schemaCountrySummary($fromCodeType))->toBe(['Spain', 'ES', 'ESP', '724']);
});

it('outputs an empty string for a code that resolves to no country', function (mixed $code, string $codeType) {
    expect(schemaCountryTagOutput([
        'code' => $code,
        'code_type' => $codeType,
    ]))->toBe('');
})->with([
    'empty code' => ['', 'alpha2'],
    'null code' => [null, 'alpha2'],
    'false code' => [false, 'alpha2'],
    'true code' => [true, 'alpha2'],
    'integer code' => [840, 'numeric'],
    'whitespace alpha2' => ['  ', 'alpha2'],
    'whitespace alpha3' => ['  ', 'alpha3'],
    'whitespace numeric' => ['  ', 'numeric'],
    'unknown alpha3' => ['ZZZ', 'alpha3'],
    'unknown numeric' => ['000', 'numeric'],
    'numeric with trailing letters' => ['840abc', 'numeric'],
]);

it('outputs an empty string when no code parameter is given', function () {
    expect(schemaCountryTagOutput([]))->toBe('');
});

it('keeps the usable codes of a mixed list in order', function () {
    $output = schemaCountryTagOutput([
        'code' => [
            840,
            null,
            '',
            ' ',
            '__',
            new Value('us'),
            ['FR'],
            ' FR ',
        ],
    ]);

    expect(schemaCountryListSummaries($output))->toBe([
        ['United States of America', 'US', 'USA', '840'],
        ['France', 'FR', 'FRA', '250'],
    ]);
});

it('resolves every code of a list by its code_type', function (string $codeType, array $codes, array $expected) {
    $output = schemaCountryTagOutput([
        'code' => $codes,
        'code_type' => $codeType,
    ]);

    expect(schemaCountryListSummaries($output))->toBe($expected);
})->with([
    'alpha3' => ['alpha3', ['usa', 'FRA'], [
        ['United States of America', 'US', 'USA', '840'],
        ['France', 'FR', 'FRA', '250'],
    ]],
    'numeric' => ['numeric', ['004', '840'], [
        ['Afghanistan', 'AF', 'AFG', '004'],
        ['United States of America', 'US', 'USA', '840'],
    ]],
]);

it('outputs an empty json array for a list with no usable code', function () {
    expect(schemaCountryTagOutput(['code' => ['', 5]]))->toBe('[]');
});

it('leaves slashes and non-ascii characters unescaped', function () {
    $output = schemaCountryTagOutput(['code' => 'AX']);

    expect($output)->toContain('"name":"Åland Islands"');
    expect($output)->toContain('"propertyID":"https://www.wikidata.org/wiki/Property:P297"');
});

it('outputs an empty string when the json cannot be encoded', function (string|array $code) {
    // An invalid UTF-8 byte in the @id stem makes json_encode() throw
    config(['app.url' => "http://\xB1"]);

    expect(schemaCountryTagOutput(['code' => $code]))->toBe('');
})->with([
    'scalar' => ['US'],
    'list' => [['US']],
]);

it('ignores a context value that is not an emit context', function () {
    $decoded = json_decode(schemaCountryTagOutput(
        ['code' => 'US'],
        [SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_EMIT_CONTEXT => 'not an emit context']
    ), true);

    expect(schemaCountrySummary($decoded))->toBe(['United States of America', 'US', 'USA', '840']);
});

it('prefers the runtime stack emit context over the Antlers context', function () {
    $emittedEntryIds = [];
    $materializedFullNodeAtIds = [];
    $usAtId = schemaCountryExpectedAtId('US');
    $emittedOnStack = [$usAtId => true];
    $emittedInAntlers = [];

    $onStack = new SchemaTemplateEmitContext(
        $emittedEntryIds,
        $emittedOnStack,
        $materializedFullNodeAtIds,
        [],
        null,
        new SchemaTemplateInstanceWatermarkBag
    );

    $inAntlers = new SchemaTemplateEmitContext(
        $emittedEntryIds,
        $emittedInAntlers,
        $materializedFullNodeAtIds,
        [],
        null,
        new SchemaTemplateInstanceWatermarkBag
    );

    SchemaTemplateEmitContextRuntimeStack::push($onStack);

    try {
        $output = schemaCountryTagOutput(
            ['code' => 'US'],
            [SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_EMIT_CONTEXT => $inAntlers]
        );
    } finally {
        SchemaTemplateEmitContextRuntimeStack::pop();
    }

    expect(json_decode($output, true))->toBe(['@id' => $usAtId]);
});
