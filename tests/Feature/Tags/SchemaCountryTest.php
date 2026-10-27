<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaJsonLdAtIdDeduplication;
use App\Services\SchemaTemplates\SchemaJsonLdFragmentAtId;
use App\Services\SchemaTemplates\SchemaOutputService;
use App\Services\SchemaTemplates\SchemaTemplateEmitContext;
use App\Services\SchemaTemplates\SchemaTemplateInstanceWatermarkBag;
use App\Tags\SchemaCountry;
use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Unit tests for the `schema_country` Antlers tag (`SchemaCountry`).
 *
 * Covers JSON-LD `Country` output: canonical `@id` via
 * `SchemaJsonLdFragmentAtId`, Wikidata-backed `identifier` values, scalar and
 * list `code`, `code_type` resolution, `SchemaTemplateEmitContext` compact
 * references, and invalid or empty input.
 *
 * Mutation: 100% on App\Tags\SchemaCountry (143 mutants, --covered-only).
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
