<?php

declare(strict_types=1);

use App\Rules\AssetMinimumDimensions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Statamic\Assets\AssetContainer as StatamicAssetContainer;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Stache;
use Tests\Support\AssetReferenceSweep;
use Tests\TestCase;

/**
 * End-to-end enforcement of the Assets minimum-dimensions rule (UAMS-Web/uams-statamic-archive#1299) THROUGH
 * Statamic's real validation pipeline against a file-backed container:
 *
 *   1. The custom rule keys to the FIELD'S OWN HANDLE in
 *      `Statamic\Fields\Validator::rules()` — not the numeric "phantom" fields a
 *      flat `extraRules()` list produced (which Laravel never validated).
 *   2. Run the FULL attached rule set through Laravel's Validator, an undersized
 *      real asset is rejected (naming its GD-extracted dimensions), an adequate one
 *      passes, and an unmeasurable one is skipped.
 *   3. Only asset-aware rule OBJECTS are wired — a validly-selected asset on a mime-
 *      and size-restricted field is NOT rejected (the raw `mimetypes:`/`extensions:`/
 *      `between:` file rules are not applied to the reference array; UAMS-Web/uams-statamic-archive#1301).
 *   4. The image-only guard (UAMS-Web/uams-statamic-archive#192) still holds — a mixed field gets no rule.
 *
 * The rule's decision matrix is unit-tested with stubs in
 * tests/Feature/Rules/AssetMinimumDimensionsTest.php; this proves the WIRING (that
 * Statamic actually invokes it on save, and does NOT wire the file-only string
 * rules) and a real dimension read.
 *
 * Runs in the `serial` group: `beforeEach` persists a file-backed asset container
 * whose disk is only `Storage::fake`d in this process (the container-leak
 * convention, UAMS-Web/uams-statamic-archive#1120).
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/AssetsMinimumDimensionsEnforcementTest.php
 */
uses(TestCase::class)->group('serial');

const MIN_DIMS_CONTAINER = 'min_dims_probe';
const MIN_DIMS_DISK = 'min_dims_probe_disk';

/**
 * Write a real baseline JPEG of the given pixel dimensions to the faked disk.
 *
 * @param  positive-int  $width
 * @param  positive-int  $height
 */
function putImage(string $path, int $width, int $height): void
{
    $image = imagecreatetruecolor($width, $height);
    ob_start();
    imagejpeg($image);
    $bytes = (string) ob_get_clean();
    imagedestroy($image);

    Storage::disk(MIN_DIMS_DISK)->put($path, $bytes);
}

/**
 * The full Statamic rule set for a `logo_image` Assets field with the given config,
 * asserting on the way through that NO rule is mis-keyed to a numeric phantom field.
 *
 * @param  array<string, mixed>  $config
 * @return array<array-key, mixed>
 */
function logoRuleset(array $config): array
{
    $blueprint = Blueprint::makeFromFields([
        'logo_image' => array_merge(['type' => 'assets', 'container' => MIN_DIMS_CONTAINER], $config),
    ]);

    /** @var Statamic\Fields\Validator $validator */
    $validator = $blueprint->fields()->validator();
    /** @var array<array-key, mixed> $rules */
    $rules = $validator->rules();

    expect(collect(array_keys($rules))->every(fn ($key): bool => is_string($key)))
        ->toBeTrue('custom Assets rules must key to a field handle, never a numeric phantom field');

    return $rules;
}

/**
 * The rules attached to the `logo_image` handle for the given config.
 *
 * @param  array<string, mixed>  $config
 * @return Collection<int, mixed>
 */
function logoHandleRules(array $config): Collection
{
    /** @var array<int, mixed> $handleRules */
    $handleRules = logoRuleset($config)['logo_image'] ?? [];

    return collect($handleRules);
}

/**
 * Validate a single selected asset id against the FULL attached rule set through
 * Laravel's Validator, returning the error messages for the field.
 *
 * @param  array<string, mixed>  $config
 * @return array<int, string>
 */
function logoErrors(array $config, string $id): array
{
    /** @var array<int, string> $messages */
    $messages = Validator::make(['logo_image' => [$id]], logoRuleset($config))->errors()->get('logo_image');

    return $messages;
}

$imageOnlyMin = [
    'file_type_restrictions' => 'mime_categories',
    'mime_categories' => ['image'],
    'min_width' => 800,
    'min_height' => 600,
];

beforeEach(function (): void {
    Storage::fake(MIN_DIMS_DISK);
    /** @var StatamicAssetContainer $container */
    $container = AssetContainer::make(MIN_DIMS_CONTAINER);
    /** @var StatamicAssetContainer $container */
    $container = $container->disk(MIN_DIMS_DISK);
    /** @var StatamicAssetContainer $container */
    $container = $container->title('Min Dims Probe');
    $container->save();

    putImage('small.jpg', 640, 480);
    putImage('large.jpg', 1000, 800);
    Storage::disk(MIN_DIMS_DISK)->put('broken.jpg', 'this is not a real jpeg');

    /** @var StatamicAssetContainer $container */
    $container = AssetContainer::find(MIN_DIMS_CONTAINER);
    $container->makeAsset('small.jpg')->save();
    $container->makeAsset('large.jpg')->save();
    $container->makeAsset('broken.jpg')->save();
});

afterEach(function (): void {
    $container = AssetContainer::find(MIN_DIMS_CONTAINER);

    if ($container instanceof StatamicAssetContainer) {
        // Delete without Statamic's asset reference sweep, which walks every entry,
        // term and global per delete and was most of this file's time (UAMS-Web/uams-statamic-archive#2500)
        AssetReferenceSweep::withoutSweep(function () use ($container): void {
            foreach (['small.jpg', 'large.jpg', 'broken.jpg'] as $basename) {
                $asset = $container->asset($basename);

                $asset?->delete();
            }

            $container->delete();
        });
    }

    // No Stache clear (UAMS-Web/uams-statamic-archive#2632): deleting the container above removes it from the
    // `asset-containers` store and its file, and assets live in the Eloquent asset
    // table, a `:memory:` database rebuilt for every test, so no later test can see
    // this file's container, assets or faked disk.
});

it('attaches the minimum-dimensions rule to the field handle in the real Validator', function () use ($imageOnlyMin) {
    expect(logoHandleRules($imageOnlyMin)->contains(fn ($r): bool => $r instanceof AssetMinimumDimensions))->toBeTrue();
});

it('does not wire raw file rules to the field handle', function () use ($imageOnlyMin) {
    // mimetypes:/extensions:/between: are Laravel FILE rules; they must not sit on
    // the reference-array handle where they would reject every valid selection.
    $strings = logoHandleRules($imageOnlyMin)->filter(fn ($r): bool => is_string($r));

    expect($strings->contains(fn (string $r): bool => str_starts_with($r, 'mimetypes:')))->toBeFalse();
    expect($strings->contains(fn (string $r): bool => str_starts_with($r, 'extensions:')))->toBeFalse();
});

it('rejects a selected asset below the minimum, naming its real dimensions', function () use ($imageOnlyMin) {
    $errors = logoErrors($imageOnlyMin, MIN_DIMS_CONTAINER.'::small.jpg');

    expect($errors)->toHaveCount(1);
    expect($errors[0])->toContain('640x480');
});

it('passes a selected asset that meets the minimum', function () use ($imageOnlyMin) {
    expect(logoErrors($imageOnlyMin, MIN_DIMS_CONTAINER.'::large.jpg'))->toBe([]);
});

it('skips an unmeasurable asset (driver could not read dimensions) instead of rejecting it', function () use ($imageOnlyMin) {
    // broken.jpg has an image extension but no readable dimensions — the HEIC case.
    expect(logoErrors($imageOnlyMin, MIN_DIMS_CONTAINER.'::broken.jpg'))->toBe([]);
});

it('does not reject a validly-selected asset on a mime- and size-restricted field', function () {
    // The exact regression the review caught: raw file rules on the reference array
    // rejected every valid save. A valid, adequately-sized image on a field that
    // restricts mime type AND file size must pass end-to-end.
    $config = [
        'file_type_restrictions' => 'mime_categories',
        'mime_categories' => ['image'],
        'min_width' => 800,
        'min_height' => 600,
        'min_file_size' => 1,
        'max_file_size' => 5,
    ];

    expect(logoErrors($config, MIN_DIMS_CONTAINER.'::large.jpg'))->toBe([]);
});

it('attaches no minimum-dimensions rule to a mixed field (non-image guard holds)', function () {
    $rules = logoHandleRules([
        'file_type_restrictions' => 'mime_categories',
        'mime_categories' => ['image', 'document'],
        'min_width' => 800,
    ]);

    expect($rules->contains(fn ($r): bool => $r instanceof AssetMinimumDimensions))->toBeFalse();
});
