<?php

declare(strict_types=1);

use App\Tags\SchemaPlace;
use Statamic\Facades\Antlers;
use Statamic\Fields\Value;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Unit tests for the `schema_place` Antlers tag.
 *
 * Mutation: 100% on App\Tags\SchemaPlace (123 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Tags/SchemaPlaceTest.php
 */
it('exposes schema_place as the tag handle', function () {
    expect(SchemaPlace::handle())->toBe('schema_place');
});

it('emits a place with string address fields', function () {
    $template = <<<'ANTLERS'
{{ schema_place street_address="4301 W. Markham St." post_office_box_number="12345" extended_address="Apt. 123" locality="Little Rock" region="AR" country="US" postal_code="72205" }}
ANTLERS;

    $out = trim((string) Antlers::parse(
        $template,
        [],
        true
    ));

    $expected = [
        '@type' => 'https://schema.org/Place',
        'address' => [
            '@type' => 'https://schema.org/PostalAddress',
            'streetAddress' => '4301 W. Markham St.',
            'postOfficeBoxNumber' => '12345',
            'extendedAddress' => 'Apt. 123',
            'addressLocality' => 'Little Rock',
            'addressRegion' => 'AR',
            'addressCountry' => 'US',
            'postalCode' => '72205',
        ],
    ];

    expect(json_decode($out, true))->toBe($expected);
});

it('emits structured region and country', function () {
    $template = <<<'ANTLERS'
{{ schema_place street_address="4301 W. Markham St." post_office_box_number="12345" extended_address="Apt. 123" locality="Little Rock" :region="schema_place_region" :country="schema_place_country" postal_code="72205" }}
ANTLERS;

    $out = trim((string) Antlers::parse(
        $template,
        [
            'schema_place_region' => [
                '@type' => 'State',
                'name' => 'Arkansas',
            ],
            'schema_place_country' => [
                '@type' => 'Country',
                'name' => 'United States of America',
            ],
        ],
        true
    ));

    $expected = [
        '@type' => 'https://schema.org/Place',
        'address' => [
            '@type' => 'https://schema.org/PostalAddress',
            'streetAddress' => '4301 W. Markham St.',
            'postOfficeBoxNumber' => '12345',
            'extendedAddress' => 'Apt. 123',
            'addressLocality' => 'Little Rock',
            'addressRegion' => [
                '@type' => 'https://schema.org/State',
                'name' => 'Arkansas',
            ],
            'addressCountry' => [
                '@type' => 'https://schema.org/Country',
                'name' => 'United States of America',
            ],
            'postalCode' => '72205',
        ],
    ];

    expect(json_decode($out, true))->toBe($expected);
});

it('outputs empty when no address fields', function () {
    $out = trim((string) Antlers::parse(
        '{{ schema_place }}',
        [],
        true
    ));

    expect($out)->toBe('');
});

it('omits empty string parameters', function () {
    $out = trim((string) Antlers::parse(
        '{{ schema_place locality="Little Rock" street_address="" region="" }}',
        [],
        true
    ));

    $expected = [
        '@type' => 'https://schema.org/Place',
        'address' => [
            '@type' => 'https://schema.org/PostalAddress',
            'addressLocality' => 'Little Rock',
        ],
    ];

    expect(json_decode($out, true))->toBe($expected);
});

it('emits from an address field flat associative value', function () {
    // Address fieldtype flat value (canonical region key) maps to PostalAddress.
    $template = <<<'ANTLERS'
{{ schema_place :address="org_address" }}
ANTLERS;

    $out = trim((string) Antlers::parse(
        $template,
        [
            'org_address' => [
                'street_address' => '4301 W. Markham St.',
                'address_line_2' => 'Suite 100',
                'city' => 'Little Rock',
                'state_province_region' => 'AR',
                'postal_code' => '72205',
                'country' => 'US',
            ],
        ],
        true
    ));

    $expected = [
        '@type' => 'https://schema.org/Place',
        'address' => [
            '@type' => 'https://schema.org/PostalAddress',
            'streetAddress' => '4301 W. Markham St.',
            'extendedAddress' => 'Suite 100',
            'addressLocality' => 'Little Rock',
            'addressRegion' => 'AR',
            'addressCountry' => 'US',
            'postalCode' => '72205',
        ],
    ];

    expect(json_decode($out, true))->toBe($expected);
});

it('emits from an address field with the american region key', function () {
    // American preset stores region under state_province_region_american.
    $template = <<<'ANTLERS'
{{ schema_place :address="org_address" }}
ANTLERS;

    $out = trim((string) Antlers::parse(
        $template,
        [
            'org_address' => [
                'preset' => 'american',
                'street_address' => '1 Main St',
                'city' => 'Little Rock',
                'state_province_region_american' => 'AR',
                'postal_code' => '72205',
            ],
        ],
        true
    ));

    $expected = [
        '@type' => 'https://schema.org/Place',
        'address' => [
            '@type' => 'https://schema.org/PostalAddress',
            'streetAddress' => '1 Main St',
            'addressLocality' => 'Little Rock',
            'addressRegion' => [
                '@type' => 'https://schema.org/State',
                'name' => 'Arkansas',
            ],
            'addressCountry' => 'US',
            'postalCode' => '72205',
        ],
    ];

    expect(json_decode($out, true))->toBe($expected);
});

it('emits from an address field with the canadian preset', function () {
    // Canadian preset emits State for province and fixed CA country.
    $template = <<<'ANTLERS'
{{ schema_place :address="org_address" }}
ANTLERS;

    $out = trim((string) Antlers::parse(
        $template,
        [
            'org_address' => [
                'preset' => 'canadian',
                'street_address' => '100 Queen St W',
                'city' => 'Toronto',
                'state_province_region_canadian' => 'ON',
                'postal_code' => 'M5H 2N2',
            ],
        ],
        true
    ));

    $expected = [
        '@type' => 'https://schema.org/Place',
        'address' => [
            '@type' => 'https://schema.org/PostalAddress',
            'streetAddress' => '100 Queen St W',
            'addressLocality' => 'Toronto',
            'addressRegion' => [
                '@type' => 'https://schema.org/State',
                'name' => 'Ontario',
            ],
            'addressCountry' => 'CA',
            'postalCode' => 'M5H 2N2',
        ],
    ];

    expect(json_decode($out, true))->toBe($expected);
});

it('sets the american preset country without a region', function () {
    // American preset still sets US when region subfield is empty.
    $template = <<<'ANTLERS'
{{ schema_place :address="org_address" }}
ANTLERS;

    $out = trim((string) Antlers::parse(
        $template,
        [
            'org_address' => [
                'preset' => 'american',
                'city' => 'Little Rock',
            ],
        ],
        true
    ));

    $expected = [
        '@type' => 'https://schema.org/Place',
        'address' => [
            '@type' => 'https://schema.org/PostalAddress',
            'addressLocality' => 'Little Rock',
            'addressCountry' => 'US',
        ],
    ];

    expect(json_decode($out, true))->toBe($expected);
});

it('lets explicit params override address field defaults', function () {
    // Explicit tag parameters override Address field defaults.
    $template = <<<'ANTLERS'
{{ schema_place :address="org_address" locality="North Little Rock" }}
ANTLERS;

    $out = trim((string) Antlers::parse(
        $template,
        [
            'org_address' => [
                'city' => 'Little Rock',
                'state_province_region' => 'AR',
            ],
        ],
        true
    ));

    $expected = [
        '@type' => 'https://schema.org/Place',
        'address' => [
            '@type' => 'https://schema.org/PostalAddress',
            'addressLocality' => 'North Little Rock',
            'addressRegion' => 'AR',
        ],
    ];

    expect(json_decode($out, true))->toBe($expected);
});

it('emits from an address field stored as a key/value list', function () {
    // Statamic key/value list storage from HasSubfields is accepted.
    $template = <<<'ANTLERS'
{{ schema_place :address="org_address" }}
ANTLERS;

    $out = trim((string) Antlers::parse(
        $template,
        [
            'org_address' => [
                [
                    'key' => 'street_address',
                    'value' => '9 Elm St',
                ],
                [
                    'key' => 'city',
                    'value' => 'Conway',
                ],
                [
                    'key' => 'postal_code',
                    'value' => '72032',
                ],
            ],
        ],
        true
    ));

    $expected = [
        '@type' => 'https://schema.org/Place',
        'address' => [
            '@type' => 'https://schema.org/PostalAddress',
            'streetAddress' => '9 Elm St',
            'addressLocality' => 'Conway',
            'postalCode' => '72032',
        ],
    ];

    expect(json_decode($out, true))->toBe($expected);
});

it('outputs empty when no address input is given', function () {
    // No address binding and no address-related params yields empty output early.
    $out = trim((string) Antlers::parse(
        '{{ schema_place }}',
        [],
        true
    ));

    expect($out)->toBe('');
});

/**
 * Run the tag directly on an Address field value and return the decoded
 * `address` object, or null when the tag emits nothing.
 *
 * @param  array<array-key, mixed>  $params
 * @return array<array-key, mixed>|null
 */
function schemaPlaceAddress(array $params): ?array
{
    $tag = new SchemaPlace;
    $tag->setContext([]);
    $tag->setParameters($params);

    $json = $tag->index();
    if ($json === '') {
        return null;
    }

    $decoded = mustBeArray(json_decode($json, true), 'schema_place JSON');

    return mustBeArray($decoded['address'] ?? null, 'schema_place address');
}

it('resolves a region given by full name, or keeps one it does not know', function (string $preset, string $region, string $expected) {
    $address = schemaPlaceAddress(['address' => [
        'preset' => $preset,
        'city' => 'Somewhere',
        'state_province_region' => $region,
    ]]);

    expect($address['addressRegion'] ?? null)->toBe(['@type' => 'https://schema.org/State', 'name' => $expected]);
})->with([
    'a US state by its lowercased name' => ['american', 'arkansas', 'Arkansas'],
    'a US state by its uppercased, padded name' => ['american', '  ARKANSAS ', 'Arkansas'],
    'another US state by its lowercased name' => ['american', 'texas', 'Texas'],
    'an unknown US region, trimmed' => ['american', ' Narnia ', 'Narnia'],
    'a Canadian province by its lowercased name' => ['canadian', 'ontario', 'Ontario'],
    'another Canadian province by its lowercased name' => ['canadian', 'quebec', 'Quebec'],
    'an unknown Canadian region, trimmed' => ['canadian', ' Atlantis ', 'Atlantis'],
]);

it('leaves out a preset region that is not a non-empty string', function (string $preset, mixed $region, string $country) {
    $address = schemaPlaceAddress(['address' => [
        'preset' => $preset,
        'city' => 'Somewhere',
        'state_province_region' => $region,
    ]]);

    expect($address)->toBe([
        '@type' => 'https://schema.org/PostalAddress',
        'addressLocality' => 'Somewhere',
        'addressCountry' => $country,
    ]);
})->with([
    'a number, american' => ['american', 5, 'US'],
    'a blank string, american' => ['american', '   ', 'US'],
    'a number, canadian' => ['canadian', 5, 'CA'],
    'a blank string, canadian' => ['canadian', '   ', 'CA'],
]);

it('reads the preset case-insensitively and ignores a blank or non-string one', function (mixed $preset, array $expectedTail) {
    $address = schemaPlaceAddress(['address' => [
        'preset' => $preset,
        'city' => 'Somewhere',
        'state_province_region' => 'AR',
        'country' => 'XX',
    ]]);

    expect($address)->toBe(['@type' => 'https://schema.org/PostalAddress', 'addressLocality' => 'Somewhere'] + $expectedTail);
})->with([
    'an uppercase, padded preset' => [' AMERICAN ', ['addressRegion' => ['@type' => 'https://schema.org/State', 'name' => 'Arkansas'], 'addressCountry' => 'US']],
    'a blank preset' => ['  ', ['addressRegion' => 'AR', 'addressCountry' => 'XX']],
    'a non-string preset' => [['american'], ['addressRegion' => 'AR', 'addressCountry' => 'XX']],
]);

it('emits nothing when the payload cannot be encoded as JSON', function (mixed $street) {
    expect(schemaPlaceAddress(['street_address' => $street, 'locality' => 'Little Rock']))->toBeNull();
})->with([
    'an invalid UTF-8 byte' => ["Bad \xB1 byte"],
    'an infinite number' => [INF],
]);

it('emits nothing when every given parameter normalizes away', function () {
    expect(schemaPlaceAddress(['street_address' => '', 'locality' => '   ']))->toBeNull();
});

it('emits unescaped slashes and unicode', function () {
    $tag = new SchemaPlace;
    $tag->setContext([]);
    $tag->setParameters(['locality' => 'Café Row']);

    expect($tag->index())->toBe('{"@type":"https://schema.org/Place","address":{"@type":"https://schema.org/PostalAddress","addressLocality":"Café Row"}}');
});

it('reads an Address field passed as a Statamic Value, and ignores one that is not an array', function (mixed $value, ?array $expected) {
    expect(schemaPlaceAddress(['address' => $value]))->toBe($expected);
})->with([
    'a Value' => [new Value(['city' => 'Little Rock']), ['@type' => 'https://schema.org/PostalAddress', 'addressLocality' => 'Little Rock']],
    'a string' => ['Little Rock', null],
    'an empty array' => [[], null],
]);

it('reads a list holding one key/value pair', function () {
    expect(schemaPlaceAddress(['address' => [['key' => 'city', 'value' => 'Little Rock']]]))
        ->toBe(['@type' => 'https://schema.org/PostalAddress', 'addressLocality' => 'Little Rock']);
});

it('reads a key/value pair list, skipping items without a string key', function () {
    expect(schemaPlaceAddress(['address' => [
        ['key' => 'city', 'value' => 'Little Rock'],
        'junk',
        ['value' => 'orphan'],
        ['key' => 5, 'value' => 'numbered'],
    ]]))->toBe(['@type' => 'https://schema.org/PostalAddress', 'addressLocality' => 'Little Rock']);
});

it('reads the international region key when no other region key is set', function () {
    expect(schemaPlaceAddress(['address' => [
        'city' => 'Munich',
        'state_province_region_international' => 'Bavaria',
        'country' => 'DE',
    ]]))->toBe([
        '@type' => 'https://schema.org/PostalAddress',
        'addressLocality' => 'Munich',
        'addressRegion' => 'Bavaria',
        'addressCountry' => 'DE',
    ]);
});

it('resolves a lowercase region abbreviation and an uppercase full name', function (string $preset, string $region, string $expected) {
    $address = schemaPlaceAddress(['address' => ['preset' => $preset, 'city' => 'Somewhere', 'state_province_region' => $region]]);

    expect($address['addressRegion'] ?? null)->toBe(['@type' => 'https://schema.org/State', 'name' => $expected]);
})->with([
    'a lowercase US abbreviation' => ['american', 'ar', 'Arkansas'],
    'a lowercase Canadian abbreviation' => ['canadian', 'on', 'Ontario'],
    'an uppercase Canadian full name' => ['canadian', 'ONTARIO', 'Ontario'],
]);
