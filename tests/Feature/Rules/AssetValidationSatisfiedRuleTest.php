<?php

declare(strict_types=1);

use App\Models\Asset as AssetModel;
use App\Rules\AssetValidationSatisfied;
use App\Statamic\Eloquent\Assets\Asset;
use Illuminate\Validation\Validator;
use Tests\TestCase;

/**
 * Unit tests for AssetValidationSatisfied rule.
 *
 * @command  php artisan test --compact tests/Feature/Rules/AssetValidationSatisfiedRuleTest.php
 *
 * Mutation: 100% on App\Rules\AssetValidationSatisfied (45 mutants, --covered-only).
 */
uses(TestCase::class);

it('passes for asset that satisfies validation', function (): void {
    // Build an images asset model with required metadata populated
    $model = new AssetModel([
        'container' => 'images',
        'folder' => 'default',
        'basename' => 'example.jpg',
        'filename' => 'example',
        'extension' => 'jpg',
        'path' => 'default/example.jpg',
        'meta' => [
            'data' => [
                'exempt_from_alt' => false,
                'alt' => 'A descriptive alt text.',
                'country_of_origin_query' => true,
                'digital_source_type' => [
                    'preset' => 'humanEdits_image_photo_edited',
                ],
            ],
        ],
    ]);

    // Hydrate a Statamic Eloquent asset from the backing model
    $asset = Asset::fromModel($model);

    // Validate using the custom rule and assert validation succeeds
    /** @var Validator $validator */
    $validator = validator(
        ['asset' => $asset],
        ['asset' => [new AssetValidationSatisfied]]
    );
    expect($validator->passes())->toBeTrue();
});

it('fails for asset that does not satisfy validation', function (): void {
    // Build an images asset model with missing required metadata
    $model = new AssetModel([
        'container' => 'images',
        'folder' => 'default',
        'basename' => 'example.jpg',
        'filename' => 'example',
        'extension' => 'jpg',
        'path' => 'default/example.jpg',
        'meta' => [
            'data' => [],
        ],
    ]);

    // Hydrate a Statamic Eloquent asset from the backing model
    $asset = Asset::fromModel($model);

    // Validate using the custom rule and assert validation fails
    /** @var Validator $validator */
    $validator = validator(
        ['asset' => $asset],
        ['asset' => [new AssetValidationSatisfied]]
    );
    expect($validator->passes())->toBeFalse();
});

it('passes for empty asset value', function (): void {
    // Validate using an empty array value and assert validation succeeds
    /** @var Validator $validator */
    $validator = validator(
        ['asset' => []],
        ['asset' => [new AssetValidationSatisfied]]
    );
    expect($validator->passes())->toBeTrue();
});

it('fails for invalid asset value', function (): void {
    // Validate using an invalid non-empty scalar and assert validation fails
    /** @var Validator $validator */
    $validator = validator(
        ['asset' => 123],
        ['asset' => [new AssetValidationSatisfied]]
    );
    expect($validator->passes())->toBeFalse();
});

it('passes for array-wrapped asset value', function (): void {
    // Build an images asset model with required metadata populated
    $model = new AssetModel([
        'container' => 'images',
        'folder' => 'default',
        'basename' => 'example.jpg',
        'filename' => 'example',
        'extension' => 'jpg',
        'path' => 'default/example.jpg',
        'meta' => [
            'data' => [
                'exempt_from_alt' => false,
                'alt' => 'A descriptive alt text.',
                'country_of_origin_query' => true,
                'digital_source_type' => [
                    'preset' => 'humanEdits_image_photo_edited',
                ],
            ],
        ],
    ]);

    // Hydrate a Statamic Eloquent asset from the backing model
    $asset = Asset::fromModel($model);

    // Validate using an array-wrapped value and assert validation succeeds
    /** @var Validator $validator */
    $validator = validator(
        ['asset' => [$asset]],
        ['asset' => [new AssetValidationSatisfied]]
    );
    expect($validator->passes())->toBeTrue();
});

it('passes for audio asset when series is scalar', function (): void {
    // Build an audio asset model with scalar single-select terms value
    $model = new AssetModel([
        'container' => 'audio',
        'folder' => 'default',
        'basename' => 'example.mp3',
        'filename' => 'example',
        'extension' => 'mp3',
        'path' => 'default/example.mp3',
        'meta' => [
            'data' => [
                'title' => 'Skin infection',
                'series' => 'heres-to-your-health',
                'about' => [
                    'medicine',
                ],
                'language' => [
                    'en',
                ],
                'hide_creator_query' => false,
                'excerpt' => 'Audio segment summary.',
                'transcript' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Transcript text.',
                            ],
                        ],
                    ],
                ],
                'creator_organization' => 'UAMS',
                'country_of_origin_query' => true,
                'geographic_location' => [
                    'little-rock',
                ],
                'geographic_location_content' => [
                    'little-rock',
                ],
                'digital_source_type' => [
                    'preset' => 'humanEdits_audio_mastered',
                ],
            ],
        ],
    ]);

    // Hydrate a Statamic Eloquent asset from the backing model
    $asset = Asset::fromModel($model);

    // Validate using the custom rule and assert validation succeeds
    /** @var Validator $validator */
    $validator = validator(
        ['asset' => $asset],
        ['asset' => [new AssetValidationSatisfied]]
    );
    expect($validator->passes())->toBeTrue();
});
