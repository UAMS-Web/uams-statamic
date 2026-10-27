<?php

declare(strict_types=1);

use App\Models\Asset as AssetModel;
use App\Statamic\Eloquent\Assets\Asset;
use App\Support\Statamic\Assets\LogosUnrestricted\AugmentedAsset as LogosUnrestrictedAugmentedAsset;
use Statamic\Fields\Value;
use Tests\TestCase;

/**
 * Integration tests for `logos_unrestricted` computed `alt` from blueprint
 * `title` and the custom augmented asset class.
 *
 * @command  php artisan test --compact tests/Feature/Support/Statamic/Assets/LogosUnrestricted/LogosUnrestrictedAugmentedAltTest.php
 *
 * Mutation: 100% on App\Support\Statamic\Assets\LogosUnrestricted\AugmentedAsset (1 mutant, --covered-only).
 */
uses(TestCase::class);

covers(LogosUnrestrictedAugmentedAsset::class);

/**
 * Asserts `Asset::augmented()` returns the logos-unrestricted augmented
 * class and that computed `alt` matches `AltFormatter::fromTitle()` for
 * the model meta title.
 *
 * @command  php artisan test --compact tests/Feature/Support/Statamic/Assets/LogosUnrestricted/LogosUnrestrictedAugmentedAltTest.php --filter=test_logos_unrestricted_uses_custom_augmented_and_computed_alt_from_title
 */
it('uses custom augmented and computed alt from title', function (): void {
    // Build an Eloquent asset row shaped like production metadata
    $model = new AssetModel([
        'container' => 'logos_unrestricted',
        'folder' => 'default',
        'basename' => 'ac-hosp_res-found_p_4c.png',
        'filename' => 'ac-hosp_res-found_p_4c',
        'extension' => 'png',
        'path' => 'default/ac-hosp_res-found_p_4c.png',
        'meta' => [
            'data' => [
                'title' => "Arkansas Children's",
            ],
        ],
    ]);

    // Hydrate the Statamic Eloquent asset from the backing model
    /** @var Asset $asset */
    $asset = Asset::fromModel($model);

    // ---
    // Assert augmented data uses the container-specific augmented
    // implementation (hook-selected), not only the default Statamic class
    // ---
    expect($asset->augmented())->toBeInstanceOf(LogosUnrestrictedAugmentedAsset::class);

    // Resolve the computed `alt` field from augmented blueprint data
    $alt = $asset->augmented()->get('alt')->value();

    // Assert `alt` matches the formatter output for the stored title
    expect($alt)->toBe("Logo of Arkansas Children's");
});

/**
 * Asserts assets compute IPTC digital source value/schema fields from
 * group data on asset blueprints.
 *
 * @command  php artisan test --compact tests/Feature/Support/Statamic/Assets/LogosUnrestricted/LogosUnrestrictedAugmentedAltTest.php --filter=test_logos_unrestricted_computes_digital_source_value_and_schema_fields
 */
it('computes digital source value and schema fields', function (): void {
    // Build an Eloquent asset row with digital source group payload
    $model = new AssetModel([
        'container' => 'logos_unrestricted',
        'folder' => 'default',
        'basename' => 'ac-hosp_res-found_p_4c.png',
        'filename' => 'ac-hosp_res-found_p_4c',
        'extension' => 'png',
        'path' => 'default/ac-hosp_res-found_p_4c.png',
        'meta' => [
            'data' => [
                'digital_source_type' => [
                    'preset' => 'digitalCapture_none',
                ],
                'digital_source_type_audio' => [
                    'preset' => 'digitalCapture_none',
                ],
            ],
        ],
    ]);

    // Hydrate the Statamic Eloquent asset from the backing model
    /** @var Asset $asset */
    $asset = Asset::fromModel($model);

    // Resolve the primary computed value key from the augmented asset
    $resolvedPrimaryType = $asset
        ->augmented()
        ->get('digital_source_type_value')
        ->value();

    // Resolve the primary computed schema key from the augmented asset
    $resolvedPrimarySchema = $asset
        ->augmented()
        ->get('digital_source_type_value_schema')
        ->value();

    // Resolve the audio-group computed value key from the augmented asset
    $resolvedAudioType = $asset
        ->augmented()
        ->get('digital_source_type_audio_digital_source_type_value')
        ->value();

    // Resolve the audio-group computed schema key from the augmented asset
    $resolvedAudioSchema = $asset
        ->augmented()
        ->get('digital_source_type_audio_digital_source_type_value_schema')
        ->value();

    // Resolve all augmented values to verify computed keys are exposed
    /** @var array<string, mixed> $resolvedAugmentedValues */
    $resolvedAugmentedValues = $asset->augmented()->all();

    // Resolve schema key from all augmented values and unwrap Value wrappers
    $resolvedSchemaFromAll = $resolvedAugmentedValues['digital_source_type_value_schema'] ?? null;
    if ($resolvedSchemaFromAll instanceof Value) {
        $resolvedSchemaFromAll = $resolvedSchemaFromAll->value();
    }

    // Assert the primary resolved type key was computed
    expect($resolvedPrimaryType)->toBe('digitalCapture');

    // Assert the primary schema URL was computed
    expect($resolvedPrimarySchema)->toBe('https://schema.org/DigitalCaptureDigitalSource');

    // Assert the namespaced audio resolved type key was computed
    expect($resolvedAudioType)->toBe('digitalCapture');

    // Assert the namespaced audio schema URL was computed
    expect($resolvedAudioSchema)->toBe('https://schema.org/DigitalCaptureDigitalSource');

    // Assert computed schema key is included when reading all augmented values
    expect($resolvedSchemaFromAll)->toBe('https://schema.org/DigitalCaptureDigitalSource');
});
