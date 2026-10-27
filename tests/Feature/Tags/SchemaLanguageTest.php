<?php

declare(strict_types=1);

use App\Tags\SchemaLanguage;
use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Unit tests for the `schema_language` Antlers tag (`SchemaLanguage`).
 *
 * Asserts JSON-LD-friendly output: `Language` maps for known enum codes, JSON
 * strings for schema.org Text when a code is unknown, single object vs array
 * wrapping, `delimiter` splitting, `:codes` array binding, and
 * `standard="iso639"` vs `bcp47`.
 *
 * Mutation: 100% on App\Tags\SchemaLanguage (115 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Tags/SchemaLanguageTest.php
 */
it('outputs a language array for bcp47 comma-separated codes', function () {
    // Parse the tag with two known BCP 47 codes
    $output = (string) Antlers::parse(
        '{{ schema_language codes="en, es" standard="bcp47" }}',
        [],
        true
    );

    // Decode JSON from the tag output
    $decoded = json_decode($output, true);

    // Assert the payload is a two-element list
    expect($decoded)->toBeArray();
    expect($decoded)->toHaveCount(2);
    expect(data_get($decoded, '0.@type'))->toBe('Language');
    expect(data_get($decoded, '0.alternateName'))->toBe('en');
    expect(data_get($decoded, '1.@type'))->toBe('Language');
    expect(data_get($decoded, '1.alternateName'))->toBe('es');
});

it('outputs a single object for a single bcp47 code', function () {
    // Parse the tag with one BCP 47 code
    $output = (string) Antlers::parse(
        '{{ schema_language codes="en" standard="bcp47" }}',
        [],
        true
    );

    // Decode JSON from the tag output
    $decoded = json_decode($output, true);

    // Assert the payload is a single Language object
    expect($decoded)->toBeArray();
    expect(data_get($decoded, '@type'))->toBe('Language');
    expect(data_get($decoded, 'alternateName'))->toBe('en');
});

it('outputs a json string for an unknown bcp47 code', function () {
    // Parse the tag with a code that has no enum case
    $output = (string) Antlers::parse(
        '{{ schema_language codes="__no_such_bcp47__" standard="bcp47" }}',
        [],
        true
    );

    // Decode JSON from the tag output
    $decoded = json_decode($output, true);

    // Assert the payload is the plain string code
    expect($decoded)->toBe('__no_such_bcp47__');
});

it('emits language objects and a text string for mixed known and unknown bcp47 codes', function () {
    // Parse the tag with one valid and one invalid code
    $output = (string) Antlers::parse(
        '{{ schema_language codes="en,__bad__,es" standard="bcp47" }}',
        [],
        true
    );

    // Decode JSON from the tag output
    $decoded = json_decode($output, true);

    // Assert three entries with Text fallback in the middle
    expect($decoded)->toHaveCount(3);
    expect(data_get($decoded, '0.@type'))->toBe('Language');
    expect(data_get($decoded, '1'))->toBe('__bad__');
    expect(data_get($decoded, '2.@type'))->toBe('Language');
});

it('splits codes using a custom delimiter', function () {
    // Parse the tag with pipe-separated codes
    $output = (string) Antlers::parse(
        '{{ schema_language codes="en|es" delimiter="|" standard="bcp47" }}',
        [],
        true
    );

    // Decode JSON from the tag output
    $decoded = json_decode($output, true);

    // Assert two Language objects were produced
    expect($decoded)->toHaveCount(2);
});

it('accepts a bound array codes parameter', function () {
    // Parse the tag with a bound array of codes
    $output = (string) Antlers::parse(
        '{{ schema_language :codes="langs" standard="bcp47" }}',
        [
            'langs' => ['en', 'es'],
        ],
        true
    );

    // Decode JSON from the tag output
    $decoded = json_decode($output, true);

    // Assert two Language objects were produced
    expect($decoded)->toHaveCount(2);
});

it('uses the language enum for the iso639 standard', function () {
    // Parse the tag with ISO 639-3 style codes
    $output = (string) Antlers::parse(
        '{{ schema_language codes="eng,fra" standard="iso639" }}',
        [],
        true
    );

    // Decode JSON from the tag output
    $decoded = json_decode($output, true);

    // Assert two Language objects reference stored codes
    expect($decoded)->toHaveCount(2);
    expect(data_get($decoded, '0.alternateName'))->toBe('eng');
    expect(data_get($decoded, '1.alternateName'))->toBe('fra');
    expect(data_get($decoded, '0.@type'))->toBe('Language');
});

it('matches tag output via the json ld for single language code helper', function () {
    // Build Language JSON-LD via the static helper for `en`
    $fromHelper = SchemaLanguage::jsonLdForSingleLanguageCode('en', 'bcp47');

    // Parse the tag with the same code for comparison
    $fromTag = json_decode(
        (string) Antlers::parse(
            '{{ schema_language codes="en" standard="bcp47" }}',
            [],
            true
        ),
        true
    );

    // Assert the helper matches the tag payload
    expect($fromHelper)->toBe($fromTag);

    // Assert blank codes yield an empty string sentinel
    expect(SchemaLanguage::jsonLdForSingleLanguageCode('   ', 'bcp47'))->toBe('');
});

it('memoizes the json ld for single language code helper per request', function () {
    // Resolve the same BCP 47 code twice in one request
    $first = SchemaLanguage::jsonLdForSingleLanguageCode(
        'en',
        'bcp47'
    );
    $second = SchemaLanguage::jsonLdForSingleLanguageCode(
        'en',
        'bcp47'
    );

    // Assert identical payloads (including shared array identity when memoized)
    expect($second)->toBe($first);

    // Read the private request-cache attribute name via reflection
    $reflection = new ReflectionClass(SchemaLanguage::class);
    $attrName = $reflection->getConstant('REQUEST_LANGUAGE_JSONLD_CACHE_ATTRIBUTE');

    // Assert one memo key exists for the duplicate resolutions
    $bag = request()->attributes->get(\is_string($attrName) ? $attrName : '');
    expect($bag)->toBeArray();
    expect($bag)->toHaveCount(1);
});
