<?php

declare(strict_types=1);

use App\Models\Asset as AssetModel;
use App\Statamic\Eloquent\Assets\Asset;
use App\Support\Statamic\Assets\Logos\AugmentedAsset as LogosAugmentedAsset;
use App\Support\Statamic\Assets\Logos\TitleAltResolver;
use Tests\TestCase;

uses(TestCase::class);

/**
 * The `logos` container's augmented asset hands every blueprint field the title and alt
 * resolver reads to `TitleAltResolver` (#2834 coverage batch 12).
 *
 * Each data set makes a different group of fields decide the result, so a field the
 * augmented asset failed to pass would change the title or the alt. The expected title is
 * the resolver's own answer for the full data; `TitleAltResolverTest` pins those strings.
 *
 * @command  php artisan test --compact tests/Feature/Support/Statamic/Assets/Logos/AugmentedAssetTest.php
 */

/**
 * A `logos` asset carrying the given blueprint data, hydrated as the app hydrates one.
 *
 * @param  array<string, mixed>  $data
 */
function logosAugmentedAsset(array $data): Asset
{
    $model = new AssetModel([
        'container' => 'logos',
        'folder' => 'default',
        'basename' => 'probe-logo.png',
        'filename' => 'probe-logo',
        'extension' => 'png',
        'path' => 'default/probe-logo.png',
        'meta' => ['data' => $data],
    ]);

    /** @var Asset $asset */
    $asset = Asset::fromModel($model);

    return $asset;
}

/**
 * One of the data sets the resolution test runs, by name.
 *
 * @return array<string, mixed>
 */
function logosAugmentedData(string $case): array
{
    return match ($case) {
        'external' => [
            'uams_logo_query' => false,
            'external_organization_name' => "Children's Health",
        ],
        'application' => [
            'uams_logo_query' => true,
            'base_logo' => 'master',
            'application_query' => true,
            'application' => 'For a Better State of Health',
            'orientation' => 'horizontal',
            'color_scheme' => 'dark',
        ],
        default => [
            'uams_logo_query' => true,
            'base_logo' => 'master-clinical',
            'application_query' => false,
            'major_entity_query' => true,
            'major_entity' => 'College of Medicine',
            'sub_entity_query' => true,
            'sub_entity' => 'Department of Pediatrics',
            'orientation' => 'vertical',
            'color_scheme' => 'light',
        ],
    };
}

it('resolves the title and alt from every field the resolver reads', function (string $case, string $alt): void {
    $data = logosAugmentedData($case);
    $augmented = logosAugmentedAsset($data)->augmented();
    $title = TitleAltResolver::title($data);

    expect($augmented)->toBeInstanceOf(LogosAugmentedAsset::class)
        ->and($title)->toBeString()->not->toBe('')
        ->and($augmented->get('title')->value())->toBe($title)
        ->and($augmented->get('alt')->value())->toBe($alt);
})->with([
    'an external organization' => ['external', "Logo of Children's Health"],
    'a UAMS logo with an application' => ['application', 'Logo of UAMS, For a Better State of Health'],
    'a UAMS Health logo with a major and a sub entity' => ['entities', 'Logo of UAMS Health College of Medicine Department of Pediatrics'],
]);

it('reads each field it passes: dropping any one changes the resolved title', function (string $field): void {
    $full = $field === 'external_organization_name'
        ? ['uams_logo_query' => false, 'external_organization_name' => "Children's Health"]
        : [
            'uams_logo_query' => true,
            'base_logo' => 'master-clinical',
            'application_query' => in_array($field, ['application_query', 'application'], true),
            'application' => 'For a Better State of Health',
            'major_entity_query' => true,
            'major_entity' => 'College of Medicine',
            'sub_entity_query' => true,
            'sub_entity' => 'Department of Pediatrics',
            'orientation' => 'vertical',
            'color_scheme' => 'light',
        ];
    $without = $full;
    unset($without[$field]);

    // The data set is one where this field decides the title...
    expect(TitleAltResolver::title($without))->not->toBe(TitleAltResolver::title($full))
        // ...so the augmented title can only match if the field was passed
        ->and(logosAugmentedAsset($full)->augmented()->get('title')->value())->toBe(TitleAltResolver::title($full));
})->with([
    'uams_logo_query', 'external_organization_name', 'base_logo', 'application_query', 'application',
    'major_entity_query', 'major_entity', 'sub_entity_query', 'sub_entity', 'orientation', 'color_scheme',
]);
