<?php

declare(strict_types=1);

use App\Tags\SchemaLocationIdentifiers;
use Statamic\Facades\Antlers;
use Statamic\Fields\LabeledValue;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Unit tests for the `schema_location_identifiers` Antlers tag.
 *
 * Engine-path augmented shapes (replicator `Values` rows, `LabeledValue` gate,
 * single-element flattening) are covered end-to-end in SchemaJsonLdLocationTest.
 *
 * @command  php artisan test --compact tests/Feature/Tags/SchemaLocationIdentifiersTest.php
 */
const ID_TAG = '{{ schema_location_identifiers :npi="npi" :npi_enabled="npi_enabled" '
    .':va_substation_id="va_substation_id" :ahaid="ahaid" :hospital_ccn_id="hospital_ccn_id" }}';

/**
 * @param  array<string, mixed>  $vars
 */
function renderLocationIdentifiers(array $vars): mixed
{
    $context = array_merge([
        'npi' => null,
        'npi_enabled' => null,
        'va_substation_id' => null,
        'ahaid' => null,
        'hospital_ccn_id' => null,
    ], $vars);

    return json_decode(trim((string) Antlers::parse(ID_TAG, $context, true)), true);
}

it('exposes schema_location_identifiers as the tag handle', function (): void {
    expect(SchemaLocationIdentifiers::handle())->toBe('schema_location_identifiers');
});

it('emits a National Provider Identifier PropertyValue with its propertyID', function (): void {
    $identifiers = renderLocationIdentifiers([
        'npi_enabled' => true,
        'npi' => [['item' => '1234567890']],
    ]);

    expect($identifiers)->toBe([
        [
            '@type' => 'PropertyValue',
            'name' => 'National Provider Identifier',
            'alternateName' => [
                'NPI number',
                'NPI',
                'National Provider ID number',
                'National Provider ID',
                'National Provider Identifier number',
            ],
            'propertyID' => 'https://www.wikidata.org/wiki/Property:P9450',
            'value' => '1234567890',
        ],
    ]);
});

it('emits one PropertyValue per NPI replicator row', function (): void {
    /** @var array<int, array<string, mixed>> $identifiers */
    $identifiers = renderLocationIdentifiers([
        'npi_enabled' => true,
        'npi' => [['item' => '1234567890'], ['item' => '9876543210']],
    ]);

    expect($identifiers)->toHaveCount(2);
    expect(array_column($identifiers, 'value'))->toBe(['1234567890', '9876543210']);
});

it('suppresses NPI when the npi gate is explicitly false', function (): void {
    $identifiers = renderLocationIdentifiers([
        'npi_enabled' => false,
        'npi' => [['item' => '1234567890']],
    ]);

    expect($identifiers)->toBeNull();
});

it('treats an absent gate as open so NPI emits on presence alone', function (): void {
    /** @var array<int, array<string, mixed>> $identifiers */
    $identifiers = renderLocationIdentifiers([
        'npi' => [['item' => '1234567890']],
    ]);

    expect($identifiers)->toHaveCount(1);
    expect($identifiers[0]['value'])->toBe('1234567890');
});

it('reads a LabeledValue gate', function (): void {
    $identifiers = renderLocationIdentifiers([
        'npi_enabled' => new LabeledValue(true, 'Yes'),
        'npi' => [['item' => '1234567890']],
    ]);

    expect($identifiers)->toHaveCount(1);
});

it('emits the STA6A, AHAID and CCN PropertyValues in source order', function (): void {
    /** @var array<int, array<string, mixed>> $identifiers */
    $identifiers = renderLocationIdentifiers([
        'va_substation_id' => '123ABC',
        'ahaid' => '1234567',
        'hospital_ccn_id' => '044001',
    ]);

    expect(array_column($identifiers, 'name'))->toBe([
        'STA6A',
        'American Hospital Association Hospital Identifier',
        'CMS Certification Number',
    ]);
    expect(array_column($identifiers, 'value'))->toBe(['123ABC', '1234567', '044001']);

    // STA6A and CCN assert no propertyID (no authoritative registry URI).
    expect($identifiers[0])->not->toHaveKey('propertyID');
    expect($identifiers[2])->not->toHaveKey('propertyID');
});

it('emits identifiers in the npi → va → ahaid → ccn order', function (): void {
    /** @var array<int, array<string, mixed>> $identifiers */
    $identifiers = renderLocationIdentifiers([
        'npi_enabled' => true,
        'npi' => [['item' => '1234567890']],
        'va_substation_id' => '123ABC',
        'ahaid' => '1234567',
        'hospital_ccn_id' => '044001',
    ]);

    expect(array_column($identifiers, 'value'))
        ->toBe(['1234567890', '123ABC', '1234567', '044001']);
});

it('strips tags from an identifier value for defense in depth', function (): void {
    /** @var array<int, array<string, mixed>> $identifiers */
    $identifiers = renderLocationIdentifiers([
        'ahaid' => '12<script>alert(1)</script>34567',
    ]);

    expect($identifiers[0]['value'])->toBe('12alert(1)34567');
});

it('skips blank and whitespace-only identifier values', function (): void {
    /** @var array<int, array<string, mixed>> $identifiers */
    $identifiers = renderLocationIdentifiers([
        'npi_enabled' => true,
        'npi' => [['item' => '   '], ['item' => '1234567890']],
        'va_substation_id' => '',
        'ahaid' => '   ',
    ]);

    expect($identifiers)->toHaveCount(1);
    expect($identifiers[0]['value'])->toBe('1234567890');
});

it('emits nothing when no identifier is present', function (): void {
    expect(trim((string) Antlers::parse(ID_TAG, [
        'npi' => null,
        'npi_enabled' => null,
        'va_substation_id' => null,
        'ahaid' => null,
        'hospital_ccn_id' => null,
    ], true)))->toBe('');
});
