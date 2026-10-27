<?php

declare(strict_types=1);

use App\Fieldtypes\Assets;
use App\Rules\AssetMinimumDimensions;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class);

/**
 * The Assets fieldtype's minimum image-dimension support: the image-only guard
 * that decides whether getValidationRules() attaches an AssetMinimumDimensions
 * rule (UAMS-Web/uams-statamic-archive#192 guard), and that the rule is configured with the right minimums and
 * container(s) (UAMS-Web/uams-statamic-archive#1299).
 *
 * The rule reads STORED asset dimension meta on save (not getimagesize() on the
 * upload), so it enforces the minimum on selection as well as upload and is
 * HEIC-safe. It must only be attached when the field is restricted to images
 * exclusively — never false-rejecting a valid non-image on a mixed field. The
 * rule's own reject/pass/skip behavior is exercised in
 * tests/Feature/Rules/AssetMinimumDimensionsTest.php.
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/AssetsMinDimensionsValidationTest.php
 */
function assetsMinDimensionsFieldtype(): Assets
{
    $fieldtype = new class extends Assets
    {
        /** @param  array<string, mixed>  $config */
        public function restrictsToImagesOnlyForTest(array $config): bool
        {
            return $this->restrictsToImagesOnly($config);
        }
    };

    // A minimal field so parent::rules() has context; the dimension rule is
    // driven by the $config passed to getValidationRules(), not the field.
    $fieldtype->setField(new Field('test_assets', ['type' => 'assets', 'container' => 'assets']));

    return $fieldtype;
}

/**
 * The AssetMinimumDimensions rules produced for a given config.
 *
 * @param  array<string, mixed>  $config
 * @return list<AssetMinimumDimensions>
 */
function assetsMinDimensionRules(array $config): array
{
    return array_values(
        collect(assetsMinDimensionsFieldtype()->getValidationRules(null, $config))
            ->filter(fn ($rule): bool => $rule instanceof AssetMinimumDimensions)
            ->values()
            ->all()
    );
}

it('attaches a minimum-dimensions rule for an image-only field with both minimums set', function () {
    $rules = assetsMinDimensionRules([
        'file_type_restrictions' => 'mime_categories',
        'mime_categories' => ['image'],
        'min_width' => 800,
        'min_height' => 600,
    ]);

    expect($rules)->toHaveCount(1);
    expect($rules[0]->minWidth)->toBe(800);
    expect($rules[0]->minHeight)->toBe(600);
});

it('attaches a width-only or height-only minimum rule', function () {
    $widthOnly = assetsMinDimensionRules([
        'file_type_restrictions' => 'mime_categories',
        'mime_categories' => ['image'],
        'min_width' => 1024,
    ]);

    expect($widthOnly)->toHaveCount(1);
    expect($widthOnly[0]->minWidth)->toBe(1024);
    expect($widthOnly[0]->minHeight)->toBe(0);

    $heightOnly = assetsMinDimensionRules([
        'file_type_restrictions' => 'mime_categories',
        'mime_categories' => ['image'],
        'min_height' => 720,
    ]);

    expect($heightOnly)->toHaveCount(1);
    expect($heightOnly[0]->minWidth)->toBe(0);
    expect($heightOnly[0]->minHeight)->toBe(720);
});

it('supports an image-only mime_types restriction', function () {
    $rules = assetsMinDimensionRules([
        'file_type_restrictions' => 'mime_types',
        'mime_types' => ['image/jpeg', 'image/png'],
        'min_width' => 500,
    ]);

    expect($rules)->toHaveCount(1);
    expect($rules[0]->minWidth)->toBe(500);
    expect($rules[0]->minHeight)->toBe(0);
});

it('passes the configured container(s) to the rule for bare-path resolution', function () {
    $single = assetsMinDimensionRules([
        'file_type_restrictions' => 'mime_categories',
        'mime_categories' => ['image'],
        'min_width' => 800,
        'container' => 'logos',
    ]);

    expect($single[0]->containers)->toBe(['logos']);

    $multiple = assetsMinDimensionRules([
        'file_type_restrictions' => 'mime_categories',
        'mime_categories' => ['image'],
        'min_width' => 800,
        'container' => ['logos', '', 'logos_unrestricted'],
    ]);

    // Empty handles are filtered out; order preserved.
    expect($multiple[0]->containers)->toBe(['logos', 'logos_unrestricted']);
});

it('attaches NO rule when the field is not image-only (footgun guard)', function (array $config) {
    /** @var array<string, mixed> $config */
    expect(assetsMinDimensionRules($config))->toBe([]);
})->with([
    'unrestricted (all)' => [['min_width' => 800]],
    'image + document category' => [[
        'file_type_restrictions' => 'mime_categories',
        'mime_categories' => ['image', 'document'],
        'min_width' => 800,
    ]],
    'image + video category' => [[
        'file_type_restrictions' => 'mime_categories',
        'mime_categories' => ['image', 'video'],
        'min_height' => 720,
    ]],
    'mime_types with a non-image' => [[
        'file_type_restrictions' => 'mime_types',
        'mime_types' => ['image/png', 'application/pdf'],
        'min_width' => 800,
    ]],
]);

it('attaches NO rule when no minimum, or a non-positive/non-numeric one, is set', function (array $config) {
    /** @var array<string, mixed> $config */
    expect(assetsMinDimensionRules($config))->toBe([]);
})->with([
    'no minimum' => [['file_type_restrictions' => 'mime_categories', 'mime_categories' => ['image']]],
    'zero width' => [['file_type_restrictions' => 'mime_categories', 'mime_categories' => ['image'], 'min_width' => 0]],
    'non-numeric' => [['file_type_restrictions' => 'mime_categories', 'mime_categories' => ['image'], 'min_width' => 'wide']],
]);

it('recognizes image-only restrictions and rejects mixed or unrestricted ones', function () {
    $fieldtype = assetsMinDimensionsFieldtype();
    // @phpstan-ignore-next-line method.notFound (anonymous subclass test accessor)
    $isImageOnly = fn (array $config): bool => $fieldtype->restrictsToImagesOnlyForTest($config);

    expect($isImageOnly(['file_type_restrictions' => 'mime_categories', 'mime_categories' => ['image']]))->toBeTrue();
    expect($isImageOnly(['file_type_restrictions' => 'mime_types', 'mime_types' => ['image/jpeg', 'image/png']]))->toBeTrue();

    expect($isImageOnly([]))->toBeFalse();
    expect($isImageOnly(['file_type_restrictions' => 'mime_categories', 'mime_categories' => ['image', 'video']]))->toBeFalse();
    expect($isImageOnly(['file_type_restrictions' => 'mime_categories', 'mime_categories' => []]))->toBeFalse();
    expect($isImageOnly(['file_type_restrictions' => 'mime_types', 'mime_types' => ['image/png', 'application/pdf']]))->toBeFalse();
});
