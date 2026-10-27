<?php

declare(strict_types=1);

use App\Tags\SchemaAmenityFeatures;
use Statamic\Facades\Antlers;
use Statamic\Fields\LabeledValue;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Unit tests for the `schema_amenity_features` Antlers tag.
 *
 * @command  php artisan test --compact tests/Feature/Tags/SchemaAmenityFeaturesTest.php
 */
const AMENITY_TEMPLATE = <<<'ANTLERS'
{{ schema_amenity_features
    :hearing_loop="hearing_loop"
    :auracast="auracast"
    :wheelchair_entrance="wheelchair_entrance"
    :wheelchair_parking="wheelchair_parking"
    :wheelchair_restroom="wheelchair_restroom" }}
ANTLERS;

/**
 * @param  array<string, mixed>  $context
 */
function renderAmenities(array $context): mixed
{
    return json_decode(trim((string) Antlers::parse(AMENITY_TEMPLATE, $context, true)), true);
}

it('exposes schema_amenity_features as the tag handle', function (): void {
    expect(SchemaAmenityFeatures::handle())->toBe('schema_amenity_features');
});

it('emits a LocationFeatureSpecification with a true value for an affirmative toggle', function (): void {
    expect(renderAmenities(['wheelchair_entrance' => true]))->toBe([
        [
            '@type' => 'https://schema.org/LocationFeatureSpecification',
            'name' => 'Wheelchair accessible entrance',
            'value' => true,
        ],
    ]);
});

it('emits a false value for a negative toggle', function (): void {
    expect(renderAmenities(['wheelchair_parking' => false]))->toBe([
        [
            '@type' => 'https://schema.org/LocationFeatureSpecification',
            'name' => 'Wheelchair accessible parking',
            'value' => false,
        ],
    ]);
});

it('reads the cast_booleans string keys defensively', function (): void {
    expect(renderAmenities(['hearing_loop' => 'true', 'auracast' => 'false']))->toBe([
        [
            '@type' => 'https://schema.org/LocationFeatureSpecification',
            'name' => 'Assistive hearing loop',
            'value' => true,
        ],
        [
            '@type' => 'https://schema.org/LocationFeatureSpecification',
            'name' => 'Auracast broadcast audio',
            'value' => false,
        ],
    ]);
});

it('omits Not Applicable toggles', function (): void {
    expect(trim((string) Antlers::parse(
        AMENITY_TEMPLATE,
        ['wheelchair_restroom' => 'notapplicable'],
        true
    )))->toBe('');
});

it('omits unanswered toggles but keeps answered ones', function (): void {
    $out = renderAmenities([
        'wheelchair_entrance' => true,
        'wheelchair_parking' => 'notapplicable',
        'wheelchair_restroom' => false,
    ]);

    expect($out)->toBe([
        [
            '@type' => 'https://schema.org/LocationFeatureSpecification',
            'name' => 'Wheelchair accessible entrance',
            'value' => true,
        ],
        [
            '@type' => 'https://schema.org/LocationFeatureSpecification',
            'name' => 'Wheelchair accessible restroom',
            'value' => false,
        ],
    ]);
});

it('emits the toggles in the canonical order regardless of how they are set', function (): void {
    /** @var array<int, array<string, mixed>> $out */
    $out = renderAmenities([
        'wheelchair_restroom' => true,
        'hearing_loop' => true,
        'auracast' => true,
    ]);

    expect(array_column($out, 'name'))->toBe([
        'Assistive hearing loop',
        'Auracast broadcast audio',
        'Wheelchair accessible restroom',
    ]);
});

it('emits nothing when no toggle is answered', function (): void {
    expect(trim((string) Antlers::parse(AMENITY_TEMPLATE, [], true)))->toBe('');
});

it('unwraps the augmented LabeledValue shape the schema engine passes', function (): void {
    // The cast_booleans radio augments to a LabeledValue wrapping the underlying
    // boolean / `notapplicable` string — the tag must read through it.
    $out = renderAmenities([
        'wheelchair_entrance' => new LabeledValue(true, 'Yes'),
        'wheelchair_parking' => new LabeledValue(false, 'No'),
        'wheelchair_restroom' => new LabeledValue('notapplicable', 'Not Applicable'),
    ]);

    expect($out)->toBe([
        [
            '@type' => 'https://schema.org/LocationFeatureSpecification',
            'name' => 'Wheelchair accessible entrance',
            'value' => true,
        ],
        [
            '@type' => 'https://schema.org/LocationFeatureSpecification',
            'name' => 'Wheelchair accessible parking',
            'value' => false,
        ],
    ]);
});
