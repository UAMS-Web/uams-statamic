<?php

declare(strict_types=1);

use App\Support\Statamic\Assets\AssetValidationSatisfiedResolver;
use Statamic\Assets\Asset as StatamicAsset;
use Statamic\Facades\Blueprint;
use Statamic\Fields\Blueprint as BlueprintInstance;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Whether an asset's metadata passes its blueprint, with a single-select terms value stored as
 * a bare slug read as the one-item list the terms field validates (#3343 coverage).
 *
 * Each asset carries a blueprint made for the test, so the field order and configuration are
 * exactly what each case needs.
 *
 * @command  php artisan test --compact tests/Feature/Support/Statamic/Assets/AssetValidationSatisfiedResolverTest.php
 */

/**
 * An in-memory asset whose blueprint has the given fields, carrying the given data.
 *
 * @param  array<array-key, mixed>  $fields  Field config by handle, in blueprint order
 * @param  array<array-key, mixed>  $data
 */
function validationResolverAsset(array $fields, array $data): StatamicAsset
{
    $blueprint = Blueprint::makeFromFields($fields);

    $asset = new class extends StatamicAsset
    {
        public ?BlueprintInstance $testBlueprint = null;

        public function blueprint()
        {
            return mustBe(BlueprintInstance::class, $this->testBlueprint, 'the test blueprint');
        }
    };
    $asset->testBlueprint = $blueprint;
    $asset->data($data);

    return $asset;
}

/**
 * A required single-select terms field with the given `max_items`.
 *
 * @return array<string, mixed>
 */
function validationResolverSingleTerm(mixed $maxItems = 1): array
{
    return ['type' => 'terms', 'taxonomies' => ['tags'], 'max_items' => $maxItems, 'validate' => ['required']];
}

it('reads a bare slug in a single-select terms field as a one-item list', function (mixed $maxItems): void {
    $asset = validationResolverAsset(['topic' => validationResolverSingleTerm($maxItems)], ['topic' => 'medicine']);

    expect(AssetValidationSatisfiedResolver::resolve($asset))->toBeTrue();
})->with([
    'max_items as a number' => [1],
    'max_items as a string' => ['1'],
]);

it('keeps normalizing after a field it skips', function (array $fields, array $data): void {
    expect(AssetValidationSatisfiedResolver::resolve(validationResolverAsset($fields, $data)))->toBeTrue();
})->with([
    'a field the asset has no value for' => [
        ['caption' => ['type' => 'text'], 'topic' => validationResolverSingleTerm()],
        ['topic' => 'medicine'],
    ],
    'a field that is not terms' => [
        ['caption' => ['type' => 'text'], 'topic' => validationResolverSingleTerm()],
        ['caption' => 'A caption', 'topic' => 'medicine'],
    ],
    'a single-select terms field with no value' => [
        ['series' => ['type' => 'terms', 'taxonomies' => ['tags'], 'max_items' => 1], 'topic' => validationResolverSingleTerm()],
        ['series' => null, 'topic' => 'medicine'],
    ],
    'a single-select terms field already stored as a list' => [
        ['series' => ['type' => 'terms', 'taxonomies' => ['tags'], 'max_items' => 1], 'topic' => validationResolverSingleTerm()],
        ['series' => ['podcasts'], 'topic' => 'medicine'],
    ],
    'a multi-select terms field' => [
        ['about' => ['type' => 'terms', 'taxonomies' => ['tags'], 'max_items' => 3], 'topic' => validationResolverSingleTerm()],
        ['about' => ['medicine', 'research'], 'topic' => 'medicine'],
    ],
]);

it('leaves a bare slug alone in a multi-select terms field, which then fails', function (): void {
    $asset = validationResolverAsset(['topic' => ['type' => 'terms', 'taxonomies' => ['tags'], 'max_items' => 3]], ['topic' => 'medicine']);

    expect(AssetValidationSatisfiedResolver::resolve($asset))->toBeFalse();
});

it('fails a required single-select terms field with no value', function (mixed $value): void {
    $asset = validationResolverAsset(['topic' => validationResolverSingleTerm()], ['topic' => $value]);

    expect(AssetValidationSatisfiedResolver::resolve($asset))->toBeFalse();
})->with([
    'null' => [null],
    'an empty list' => [[]],
]);

it('passes a single-select terms field already stored as a list', function (): void {
    $asset = validationResolverAsset(['topic' => validationResolverSingleTerm()], ['topic' => ['medicine']]);

    expect(AssetValidationSatisfiedResolver::resolve($asset))->toBeTrue();
});

it('validates fields other than terms as stored', function (): void {
    $fields = ['caption' => ['type' => 'text', 'validate' => ['required']]];

    expect(AssetValidationSatisfiedResolver::resolve(validationResolverAsset($fields, ['caption' => 'A caption'])))->toBeTrue()
        ->and(AssetValidationSatisfiedResolver::resolve(validationResolverAsset($fields, [])))->toBeFalse();
});
